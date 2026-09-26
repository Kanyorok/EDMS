<?php

namespace App\Support\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EndpointContext
{
    protected static ?array $localInterfaceCache = null;

    public static function resolve(?Request $request = null): array
    {
        $request ??= app()->bound('request') ? request() : null;

        $resolved = [
            'ip' => null,
            'mac' => null,
            'source' => null,
            'hostname' => null,
        ];

        if (! $request instanceof Request) {
            return $resolved;
        }

        $headerResolution = self::resolveFromHeaders($request);
        if ($headerResolution['ip'] !== null) {
            $resolved['ip'] = $headerResolution['ip'];
            $resolved['source'] = $headerResolution['source'];
        }

        if ($resolved['ip'] === null) {
            $requestClientIp = self::normalizeIp($request->getClientIp())
                ?? self::normalizeIp($request->ip());

            if ($requestClientIp !== null) {
                $resolved['ip'] = $requestClientIp;
                $resolved['source'] = 'request';
            }
        }

        $resolved['mac'] = self::resolveMacFromHeaders($request);

        if ($resolved['ip'] === null || self::isLoopbackAddress($resolved['ip'])) {
            $localInterface = self::resolveLocalInterface();

            if (($localInterface['ip'] ?? null) !== null) {
                $resolved['ip'] = $localInterface['ip'];
                $resolved['source'] = 'local_interface';
            }

            if (($resolved['mac'] === null || $resolved['mac'] === '') && ($localInterface['mac'] ?? null) !== null) {
                $resolved['mac'] = $localInterface['mac'];
            }

            if (($localInterface['hostname'] ?? null) !== null) {
                $resolved['hostname'] = $localInterface['hostname'];
            }
        }

        if ($resolved['hostname'] === null) {
            $resolved['hostname'] = self::resolveHostName();
        }

        return $resolved;
    }

    protected static function resolveFromHeaders(Request $request): array
    {
        $headers = config('activitylog.endpoint_ip_headers', [
            'X-Forwarded-For',
            'X-Real-IP',
            'Client-IP',
            'X-Client-IP',
            'Forwarded',
        ]);

        foreach ($headers as $header) {
            if (! is_string($header) || trim($header) === '') {
                continue;
            }

            $headerName = trim($header);
            $rawValue = $request->headers->get($headerName);

            if (! is_string($rawValue) || trim($rawValue) === '') {
                continue;
            }

            $candidates = self::extractIpCandidates($headerName, $rawValue);

            foreach ($candidates as $candidate) {
                $normalizedIp = self::normalizeIp($candidate);

                if ($normalizedIp === null || self::isLoopbackAddress($normalizedIp)) {
                    continue;
                }

                return [
                    'ip' => $normalizedIp,
                    'source' => 'header:' . $headerName,
                ];
            }
        }

        return ['ip' => null, 'source' => null];
    }

    protected static function resolveMacFromHeaders(Request $request): ?string
    {
        $headers = config('activitylog.endpoint_mac_headers', [
            'X-Client-Mac-Address',
            'X-Mac-Address',
            'X-Device-Mac',
        ]);

        foreach ($headers as $header) {
            if (! is_string($header) || trim($header) === '') {
                continue;
            }

            $rawValue = $request->headers->get(trim($header));

            if (! is_string($rawValue) || trim($rawValue) === '') {
                continue;
            }

            $normalizedMac = self::normalizeMac($rawValue);
            if ($normalizedMac !== null) {
                return $normalizedMac;
            }
        }

        return null;
    }

    protected static function extractIpCandidates(string $headerName, string $rawValue): array
    {
        if (Str::lower($headerName) === 'forwarded') {
            preg_match_all('/for=(?:"?\[?)([^;,"\]]+)/i', $rawValue, $matches);

            return array_values(array_filter($matches[1] ?? []));
        }

        return array_map('trim', explode(',', $rawValue));
    }

    protected static function resolveLocalInterface(): array
    {
        if (self::$localInterfaceCache !== null) {
            return self::$localInterfaceCache;
        }

        $candidates = [];
        $hostname = self::resolveHostName();

        if (function_exists('net_get_interfaces')) {
            try {
                $interfaces = @net_get_interfaces();

                if (is_array($interfaces)) {
                    foreach ($interfaces as $name => $interface) {
                        $macAddress = self::extractInterfaceMac($interface);
                        $addresses = Arr::wrap(data_get($interface, 'unicast', []));

                        foreach ($addresses as $addressData) {
                            $address = self::normalizeIp(data_get($addressData, 'address'));

                            if (! self::isUsableLocalAddress($address)) {
                                continue;
                            }

                            $candidates[] = [
                                'ip' => $address,
                                'mac' => $macAddress,
                                'hostname' => $hostname,
                                'score' => self::scoreInterface((string) $name, (array) $interface, $address),
                            ];
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore interface inspection failures and fall back below.
            }
        }

        if ($candidates === []) {
            $hostIps = gethostbynamel(gethostname()) ?: [];

            foreach ($hostIps as $hostIp) {
                $normalizedIp = self::normalizeIp($hostIp);

                if (! self::isUsableLocalAddress($normalizedIp)) {
                    continue;
                }

                $candidates[] = [
                    'ip' => $normalizedIp,
                    'mac' => null,
                    'hostname' => $hostname,
                    'score' => 1,
                ];
            }
        }

        if ($candidates !== []) {
            usort($candidates, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
            self::$localInterfaceCache = Arr::except($candidates[0], ['score']);

            return self::$localInterfaceCache;
        }

        self::$localInterfaceCache = [
            'ip' => null,
            'mac' => null,
            'hostname' => $hostname,
        ];

        return self::$localInterfaceCache;
    }

    protected static function extractInterfaceMac(array $interface): ?string
    {
        foreach (['mac', 'hardware_addr', 'hardwareAddress', 'physical_address'] as $key) {
            $value = data_get($interface, $key);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $normalized = self::normalizeMac($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    protected static function scoreInterface(string $name, array $interface, string $address): int
    {
        $score = 0;
        $normalizedName = Str::lower($name);

        if ((bool) data_get($interface, 'up', false)) {
            $score += 10;
        }

        if (! Str::contains($normalizedName, [
            'loopback',
            'docker',
            'vmware',
            'virtual',
            'hyper-v',
            'vethernet',
            'bluetooth',
            'npcap',
            'tunnel',
            'tap',
            'hamachi',
        ])) {
            $score += 6;
        }

        if (self::isPrivateAddress($address)) {
            $score += 4;
        }

        if (str_starts_with($address, '169.254.')) {
            $score -= 10;
        }

        return $score;
    }

    protected static function normalizeIp(mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        if (Str::startsWith($candidate, '[') && Str::contains($candidate, ']')) {
            $candidate = Str::before(Str::after($candidate, '['), ']');
        }

        if (Str::contains($candidate, ':') && substr_count($candidate, ':') === 1 && str_contains($candidate, '.')) {
            [$candidate] = explode(':', $candidate, 2);
        }

        $candidate = trim($candidate, "\"' ");

        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : null;
    }

    protected static function normalizeMac(mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $normalized = strtoupper(trim($candidate));
        $normalized = str_replace(['-', '.'], ':', $normalized);

        if (preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    protected static function isUsableLocalAddress(?string $address): bool
    {
        if ($address === null || self::isLoopbackAddress($address)) {
            return false;
        }

        return ! str_starts_with($address, '169.254.');
    }

    protected static function isLoopbackAddress(?string $address): bool
    {
        if ($address === null) {
            return false;
        }

        return $address === '::1'
            || str_starts_with($address, '127.');
    }

    protected static function isPrivateAddress(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    }

    protected static function resolveHostName(): ?string
    {
        $hostname = gethostname();

        return is_string($hostname) && trim($hostname) !== ''
            ? $hostname
            : null;
    }
}

<?php

namespace App\Services\ThirdParty;

use App\Enums\Core\IntegrationsEnum;
use App\Exceptions\ErroredException;
use App\Models\Settings\APICredential;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use SensitiveParameter;
use stdClass;

class iTrackService
{
    private string $_baseUrl;
    private string $_password;
    private string $_username;

    /**
     * @throws ErroredException
     */
    public function __construct()
    {
        $iTrack = APICredential::query()->where('Integration', IntegrationsEnum::iTrack->value)->latest('Id')->first();
        if (! $iTrack instanceof APICredential) {
            throw new ErroredException('there are no iTrack configuration.');
        }

        $config = $iTrack?->Configuration;
        if (! $config instanceof stdClass) {
            throw new ErroredException('invalid iTrack configuration.');
        }

        if (! property_exists($config, 'password') || ! property_exists($config, 'username') || ! property_exists($config, 'host')) {
            throw new ErroredException('invalid iTrack configuration.');
        }

        try {
            $password = Crypt::decryptString($config->password);
        } catch (DecryptException) {
            throw new ErroredException('invalid iTrack configuration.');
        }
        $this->_username = $config->username;
        $this->_password = (string)$password;
        $this->_baseUrl = Str::rtrim($config->host, '/');
    }

    public static function testConfig(string $url, string $username, #[SensitiveParameter] string $password): bool
    {
        try {
            return (is_string(self::_token($url, $username, $password)));
        } catch (ErroredException $e) {
            return false;
        }
    }

    /**
     * @throws ErroredException
     */
    protected static function _token(string $url, string $username, #[SensitiveParameter] string $password): string
    {
        $cacheKey = 'itrack_token_' . md5($url . $username);

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $token = self::_fetchToken($url, $username, $password);
        Cache::put($cacheKey, $token, now()->addMinutes(50));

        return $token;
    }

    /**
     * Force-refresh the cached token (called when a downstream API returns a token-expired error).
     */
    protected function _refreshToken(): string
    {
        $cacheKey = 'itrack_token_' . md5($this->_baseUrl . $this->_username);
        Cache::forget($cacheKey);
        $token = self::_fetchToken($this->_baseUrl, $this->_username, $this->_password);
        Cache::put($cacheKey, $token, now()->addMinutes(50));

        return $token;
    }

    /**
     * @throws ErroredException
     */
    private static function _fetchToken(string $url, string $username, #[SensitiveParameter] string $password): string
    {
        $timestamp = time();
        $signature = md5(md5($password) . $timestamp);

        try {
            $client = new Client(['base_uri' => $url]);
            $response = $client->get('api/authorization', [
                'query' => [
                    'time' => $timestamp,
                    'account' => $username,
                    'signature' => $signature,
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            Log::error('iTrack Get Token failed !' . $e->getMessage());

            throw new ErroredException('Get Token failed !');
        } catch (JsonException $e) {
            throw new ErroredException('Decode token json failed !');
        }

        if (isset($data['code']) && $data['code'] === 0) {
            return $data['record']['access_token'];
        }

        if (isset($data['code']) && $data['code'] === 10014) {
            $errorMsg = $data['record'] ?? implode(',', $data);
            Log::warning('iTrack Time sync error: ' . $errorMsg);

            if (preg_match('/current\s+server\s+time\s+is\s+(\d+)/', $errorMsg, $matches)) {
                $serverTime = (int) $matches[1];
                $timeGap = $serverTime - time();
                $correctedTimestamp = $serverTime;
                $correctedSignature = md5(md5($password) . $correctedTimestamp);

                Log::warning("iTrack time gap detected: {$timeGap}s. Retrying with corrected timestamp.");

                try {
                    $response = $client->get('api/authorization', [
                        'query' => [
                            'time' => $correctedTimestamp,
                            'account' => $username,
                            'signature' => $correctedSignature,
                        ],
                    ]);
                    $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

                    if (isset($data['code']) && $data['code'] === 0) {
                        return $data['record']['access_token'];
                    }
                } catch (GuzzleException | JsonException $e) {
                    Log::error('iTrack retry with corrected time failed: ' . $e->getMessage());
                }
            }
        }

        Log::error('iTrack Get Token failed !' . implode(',', $data));

        throw new ErroredException('Get Token failed !');
    }

    public function validateIMEI(int $imei): bool
    {
        try {
            $this->findTrack($imei);

            return true;
        } catch (ErroredException) {
        }

        return false;
    }

    /**
     * @throws ErroredException
     */
    public function findTrack(int $imei): Collection
    {
        return collect($this->_findTrack([$imei])[0]);
    }

    /**
     * @throws ErroredException
     */
    protected function _findTrack(array $imeis): array
    {
        $token = self::_token($this->_baseUrl, $this->_username, $this->_password);

        $client = new Client([
            'base_uri' => $this->_baseUrl,
        ]);

        try {
            $response = $client->get('/api/track', [
                'query' => [
                    'access_token' => $token,
                    'imeis' => implode(',', $imeis),
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            \Log::error('iTrack Track api failed ! ' . $e->getMessage());

            throw new ErroredException('Track api failed !');
        } catch (JsonException $e) {
            \Log::error('iTrack Decode track json failed ! ' . $e->getMessage());

            throw new ErroredException('Decode track json failed !');
        }

        if (isset($data['code']) && $data['code'] === 0) {
            return $data['record'];
        }

        // Token may have expired — refresh and retry once
        if (isset($data['code']) && in_array($data['code'], [10008, 10009, 10010], true)) {
            $token = $this->_refreshToken();

            try {
                $response = $client->get('/api/track', [
                    'query' => [
                        'access_token' => $token,
                        'imeis' => implode(',', $imeis),
                    ],
                ]);
                $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                if (isset($data['code']) && $data['code'] === 0) {
                    return $data['record'];
                }
            } catch (GuzzleException | JsonException $e) {
                Log::error('iTrack Track retry failed: ' . $e->getMessage());
            }
        }

        Log::error('iTrack Track failed !' . implode(',', $data));

        throw new ErroredException('Track failed !');
    }

    /**
     * @throws ErroredException
     */
    public function findMultipleTrack(array|string $imeis): Collection
    {
        $devices = is_array($imeis) ? $imeis : explode(',', $imeis);
        $filtered = collect($devices)
            ->filter(fn ($imei) => ! empty($imei) && is_numeric($imei) && (int)$imei > 0)
            ->values();

        if ($filtered->count() !== count($devices)) {
            $skipped = collect($devices)
                ->filter(fn ($imei) => empty($imei) || ! is_numeric($imei) || (int)$imei <= 0)
                ->values();
            Log::warning('iTrackService: Skipping null/empty/invalid IMEIs: ' . $skipped->implode(", "));
        }

        if ($filtered->isEmpty()) {
            return collect();
        }

        return collect($this->_findTrack($filtered->all()));
    }

    public function getPlayback(int $imei, Carbon $beginTime, Carbon $endTime): Collection
    {
        [$start, $end] = $beginTime->lt($endTime)
            ? [(int) $beginTime->format('U'), (int) $endTime->format('U')]
            : [(int) $endTime->format('U'),   (int) $beginTime->format('U')];

        $token = self::_token($this->_baseUrl, $this->_username, $this->_password);

        $client = new Client(['base_uri' => $this->_baseUrl]);
        $query = [
            'access_token' => $token,
            'imei' => $imei,
            'begintime' => $start,
            'endtime' => $end,
        ];

        try {
            $response = $client->get('/api/playback', ['query' => $query]);
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            Log::error('iTrack Playback api failed ! ' . $e->getMessage());

            throw new ErroredException('Playback api failed !');
        } catch (JsonException $e) {
            Log::error('iTrack Decode playback json failed ! ' . $e->getMessage());

            throw new ErroredException('Decode playback json failed !');
        }

        // Token may have expired — refresh and retry once
        if (isset($data['code']) && in_array($data['code'], [10008, 10009, 10010], true)) {
            $token = $this->_refreshToken();
            $query['access_token'] = $token;

            try {
                $response = $client->get('/api/playback', ['query' => $query]);
                $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            } catch (GuzzleException | JsonException $e) {
                Log::error('iTrack Playback retry failed: ' . $e->getMessage());

                throw new ErroredException('Playback api failed !');
            }
        }

        if (isset($data['code']) && $data['code'] === 0) {
            $raw = '';
            if (isset($data['record']) && is_array($data['record'])) {
                $raw = (string) ($data['record'][0] ?? '');
            } elseif (isset($data['record']) && is_string($data['record'])) {
                $raw = $data['record'];
            }

            if (empty(trim($raw))) {
                return collect();
            }

            return collect(explode(';', $raw))
                ->filter(fn (string $s) => str_contains($s, ','))
                ->map(function (string $point): ?array {
                    $parts = explode(',', $point);
                    if (count($parts) < 5) {
                        return null;
                    }

                    return [
                        'latitude' => (float) $parts[1],
                        'longitude' => (float) $parts[0],
                        'time' => (int)   $parts[2],
                        'speed' => (float) $parts[3],
                        'course' => (float) $parts[4],
                    ];
                })
                ->filter()
                ->values();
        }

        Log::error('iTrack Playback failed ! code=' . ($data['code'] ?? 'unknown') . ' record=' . json_encode($data['record'] ?? null));

        throw new ErroredException('Playback failed !');
    }
}

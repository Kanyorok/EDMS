<?php

namespace App\Services\Audit;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;

class AuditLogger
{
    /**
     * Record an explicit view or business event. Ordinary Eloquent CRUD is
     * recorded by the model activity hooks and should not be duplicated here.
     */
    public function record(
        string $action,
        string $description,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        array $context = [],
        string $status = 'SUCCESS',
        ?string $failureReason = null,
        ?Model $causer = null,
    ): ?Activity {
        $action = $this->normalizeAction($action);
        $changedFields = $this->changedFields($oldValues, $newValues);
        $oldValues = $this->sanitize($oldValues);
        $newValues = $this->sanitize($newValues);

        $audit = array_filter([
            ...$context,
            'action_type' => $action,
            'status' => Str::upper($status),
            'failure_reason' => $failureReason,
            'old_values' => $oldValues !== [] ? $oldValues : null,
            'new_values' => $newValues !== [] ? $newValues : null,
            'changed_fields' => $changedFields !== [] ? $changedFields : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $fingerprint = $this->fingerprint($action, $description, $subject, $oldValues, $newValues, $audit);
        if ($this->alreadyRecorded($fingerprint)) {
            return null;
        }

        $logger = activity()
            ->useLog('audit')
            ->event(Str::lower($action))
            ->withProperties([
                'old' => $oldValues,
                'attributes' => $newValues,
                'audit' => $audit,
            ]);

        if ($subject instanceof Model) {
            $logger->performedOn($subject);
        }
        if ($causer instanceof Model) {
            $logger->causedBy($causer);
        }

        $activity = $logger->log($description);
        if ($activity !== null) {
            $this->remember($fingerprint);
        }

        return $activity;
    }

    public function view(?Model $subject, string $description, array $context = []): ?Activity
    {
        return $this->record('VIEW', $description, $subject, context: $context);
    }

    private function normalizeAction(string $action): string
    {
        return match (Str::upper(trim($action))) {
            'CREATED' => 'CREATE',
            'UPDATED' => 'UPDATE',
            'DELETED' => 'DELETE',
            'RESTORED' => 'RESTORE',
            'VIEWED' => 'VIEW',
            default => Str::upper(trim($action)),
        };
    }

    private function changedFields(array $oldValues, array $newValues): array
    {
        $fields = [];

        foreach (array_unique([...array_keys($oldValues), ...array_keys($newValues)]) as $field) {
            if (json_encode($oldValues[$field] ?? null) !== json_encode($newValues[$field] ?? null)) {
                $fields[] = (string) $field;
            }
        }

        return $fields;
    }

    private function sanitize(array $values): array
    {
        $sensitiveFragments = [
            'password', 'token', 'secret', 'otp', 'pin', 'api_key', 'apikey',
            'authorization', 'private_key', 'card_number', 'cvv', 'signature',
            'session_id', 'currentsessionid',
        ];

        foreach ($values as $key => $value) {
            $normalizedKey = Str::snake((string) $key);
            $compactKey = str_replace('_', '', $normalizedKey);
            $isSensitive = in_array($normalizedKey, ['key', 'pass', 'credential', 'credentials'], true)
                || Str::endsWith($normalizedKey, ['_key', '_pass', '_credential', '_credentials'])
                || collect($sensitiveFragments)->contains(
                    static fn (string $fragment): bool => Str::contains(
                        $compactKey,
                        str_replace('_', '', $fragment)
                    )
                );

            if ($isSensitive) {
                $values[$key] = '********';

                continue;
            }

            $values[$key] = match (true) {
                is_array($value) => $this->sanitize($value),
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                $value instanceof Model => $this->sanitize($value->toArray()),
                is_object($value) && method_exists($value, 'toArray') => $this->sanitize($value->toArray()),
                is_object($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                default => $value,
            };
        }

        return $values;
    }

    private function fingerprint(
        string $action,
        string $description,
        ?Model $subject,
        array $oldValues,
        array $newValues,
        array $audit,
    ): string {
        return hash('sha256', json_encode([
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old' => $oldValues,
            'new' => $newValues,
            'table' => $audit['table_name'] ?? $subject?->getTable(),
            'record' => $audit['record_id'] ?? $subject?->getKey(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function alreadyRecorded(string $fingerprint): bool
    {
        if (! app()->bound('request')) {
            return false;
        }

        return in_array($fingerprint, (array) request()->attributes->get('audit_fingerprints', []), true);
    }

    private function remember(string $fingerprint): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $fingerprints = (array) request()->attributes->get('audit_fingerprints', []);
        $fingerprints[] = $fingerprint;
        request()->attributes->set('audit_fingerprints', array_values(array_unique($fingerprints)));
    }
}

<?php

namespace App\Models\Audit;

use App\Enums\Core\ModulesEnum;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Support\Audit\EndpointContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity as BaseActivity;

class ActivityLog extends BaseActivity
{
    protected $table = 't_SYSActivityLog';

    protected $casts = [
        'properties' => 'collection',
        'OldValues' => 'array',
        'NewValues' => 'array',
        'ChangedFields' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $activity->hydrateAuditFields();
        });
    }

    public function hydrateAuditFields(): void
    {
        $properties = $this->properties instanceof Collection
            ? $this->properties
            : collect($this->properties ?? []);

        $explicitAudit = collect($properties->get('audit', []));

        $actor = $this->resolveActor();
        $subject = $this->resolveSubjectModel();

        $moduleContext = $this->resolveModuleContext($subject, $explicitAudit);
        $valuePayload = $this->resolveValuePayloads($properties, $explicitAudit);

        $this->Status = $this->Status
            ?? $explicitAudit->get('status')
            ?? 'SUCCESS';

        $this->FailureReason = $this->FailureReason
            ?? $explicitAudit->get('failure_reason');

        $this->DescriptionText = $this->DescriptionText
            ?? $explicitAudit->get('description')
            ?? $this->description;

        $this->ActionType = $this->ActionType
            ?? Str::upper((string) ($explicitAudit->get('action_type') ?: $this->event ?: 'ACTION'));

        $this->ModuleID = $this->ModuleID
            ?? $explicitAudit->get('module_id')
            ?? $moduleContext['id'];

        $this->ModuleName = $this->ModuleName
            ?? $explicitAudit->get('module_name')
            ?? $moduleContext['name'];

        $this->FeatureName = $this->FeatureName
            ?? $explicitAudit->get('feature_name')
            ?? $moduleContext['feature'];

        $this->EntityName = $this->EntityName
            ?? $explicitAudit->get('entity_name')
            ?? $this->resolveEntityName($subject);

        $this->TableName = $this->TableName
            ?? $explicitAudit->get('table_name')
            ?? ($subject instanceof Model ? $subject->getTable() : null);

        $this->RecordID = $this->RecordID
            ?? $explicitAudit->get('record_id')
            ?? ($subject instanceof Model ? (string) $subject->getKey() : ($this->subject_id ? (string) $this->subject_id : null));

        $this->ReferenceNo = $this->ReferenceNo
            ?? $explicitAudit->get('reference_no')
            ?? $this->resolveReferenceNo($subject, $properties);

        if ($actor instanceof User) {
            $this->CreatedBy = $this->CreatedBy ?? $actor->Id;
            $this->UserName = $this->UserName ?? $actor->Name;

            $role = $actor->role();
            if ($role instanceof Role) {
                $this->RoleID = $this->RoleID ?? $role->id;
                $this->RoleName = $this->RoleName ?? $role->name;
            } else {
                $this->RoleName = $this->RoleName ?? session('LoginRoleName');
            }

            $this->BranchID = $this->BranchID
                ?? session('LoginBranchId')
                ?? $actor->BranchId
                ?? $actor->employee?->BranchID;

            $this->DepartmentID = $this->DepartmentID
                ?? $actor->employee?->DepartmentID;
        } elseif (empty($this->CreatedBy) && is_numeric($this->causer_id)) {
            $this->CreatedBy = (int) $this->causer_id;
        }

        if (app()->bound('request')) {
            $request = request();
            $requestId = $request->headers->get('X-Request-ID')
                ?: $request->attributes->get('audit_request_id');

            if (! $requestId) {
                $requestId = (string) Str::uuid();
                $request->attributes->set('audit_request_id', $requestId);
            }

            $endpointContext = EndpointContext::resolve($request);

            $this->IPAddress = $this->IPAddress ?? $endpointContext['ip'];
            $this->UserAgent = $this->UserAgent ?? (string) $request->userAgent();
            $this->DeviceName = $this->DeviceName ?? $this->detectDeviceName((string) $request->userAgent());
            $this->SessionID = $this->SessionID ?? session()->getId();
            $this->RequestID = $this->RequestID ?? $requestId;

            $properties = $properties->merge([
                'audit' => array_filter([
                    ...$explicitAudit->toArray(),
                    'endpoint_ip' => $endpointContext['ip'],
                    'endpoint_source' => $endpointContext['source'],
                    'mac_address' => $endpointContext['mac'],
                    'host_name' => $endpointContext['hostname'],
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ]);
        }

        $this->OldValues = $this->OldValues ?? $valuePayload['old'];
        $this->NewValues = $this->NewValues ?? $valuePayload['new'];
        $this->ChangedFields = $this->ChangedFields ?? $valuePayload['changed'];
        $this->properties = $properties;
    }

    public function oldValuesPayload(): array
    {
        if (is_array($this->OldValues) && $this->OldValues !== []) {
            return $this->OldValues;
        }

        return $this->maskSensitiveValues($this->normalizePayload(
            data_get($this->properties, 'old', [])
        ));
    }

    public function newValuesPayload(): array
    {
        if (is_array($this->NewValues) && $this->NewValues !== []) {
            return $this->NewValues;
        }

        return $this->maskSensitiveValues($this->normalizePayload(
            data_get($this->properties, 'attributes', [])
        ));
    }

    public function changedFieldsPayload(): array
    {
        if (is_array($this->ChangedFields) && $this->ChangedFields !== []) {
            return $this->ChangedFields;
        }

        return $this->calculateChangedFields(
            $this->oldValuesPayload(),
            $this->newValuesPayload()
        );
    }

    public function macAddressPayload(): ?string
    {
        $macAddress = data_get($this->properties?->toArray() ?? [], 'audit.mac_address');

        return is_string($macAddress) && trim($macAddress) !== ''
            ? $macAddress
            : null;
    }

    public function endpointSourcePayload(): ?string
    {
        $endpointSource = data_get($this->properties?->toArray() ?? [], 'audit.endpoint_source');

        return is_string($endpointSource) && trim($endpointSource) !== ''
            ? $endpointSource
            : null;
    }

    public function endpointHostNamePayload(): ?string
    {
        $hostName = data_get($this->properties?->toArray() ?? [], 'audit.host_name');

        return is_string($hostName) && trim($hostName) !== ''
            ? $hostName
            : null;
    }

    public function getUserDisplayNameAttribute(): string
    {
        return $this->UserName
            ?: $this->causer?->Name
            ?: 'System';
    }

    public function getModuleDisplayNameAttribute(): string
    {
        return $this->ModuleName
            ?: ($this->resolveModuleContext($this->resolveSubjectModel(), collect())['name'] ?? 'General');
    }

    public function getEntityDisplayNameAttribute(): string
    {
        return $this->EntityName
            ?: $this->resolveEntityName($this->resolveSubjectModel())
            ?: 'System Event';
    }

    public function getActionBadgeAttribute(): array
    {
        return match (Str::upper((string) ($this->ActionType ?: $this->event))) {
            'CREATE', 'STORE', 'SUBMIT', 'LOGIN', 'AUTHENTICATION' => ['class' => 'success', 'text' => $this->ActionType ?: $this->event],
            'UPDATE', 'EDIT', 'PATCH' => ['class' => 'warning', 'text' => $this->ActionType ?: $this->event],
            'DELETE', 'DESTROY', 'REMOVE', 'DEACTIVATE' => ['class' => 'danger', 'text' => $this->ActionType ?: $this->event],
            'APPROVE', 'POST', 'PROCESS', 'COMPLETE' => ['class' => 'primary', 'text' => $this->ActionType ?: $this->event],
            'REJECT', 'RETURN', 'CANCEL', 'REVERSE', 'FAILED' => ['class' => 'danger', 'text' => $this->ActionType ?: $this->event],
            'EXPORT', 'DOWNLOAD', 'PRINT' => ['class' => 'info', 'text' => $this->ActionType ?: $this->event],
            default => ['class' => 'secondary', 'text' => $this->ActionType ?: $this->event ?: 'ACTION'],
        };
    }

    public function getStatusBadgeAttribute(): array
    {
        return match (Str::upper((string) $this->Status)) {
            'SUCCESS' => ['class' => 'success', 'text' => 'Success'],
            'FAILED' => ['class' => 'danger', 'text' => 'Failed'],
            'PENDING' => ['class' => 'warning', 'text' => 'Pending'],
            default => ['class' => 'secondary', 'text' => $this->Status ?: 'Unknown'],
        };
    }

    protected function resolveActor(): ?User
    {
        if ($this->causer instanceof User) {
            return $this->causer;
        }

        $authUser = auth()->user();
        if ($authUser instanceof User) {
            return $authUser;
        }

        if (is_numeric($this->causer_id) && $this->causer_type && Str::contains((string) $this->causer_type, 'User')) {
            return User::query()->find((int) $this->causer_id);
        }

        return null;
    }

    protected function resolveSubjectModel(): ?Model
    {
        if ($this->relationLoaded('subject')) {
            $loadedSubject = $this->getRelation('subject');

            return $loadedSubject instanceof Model ? $loadedSubject : null;
        }

        $subjectType = $this->subject_type;
        if (! is_string($subjectType) || $subjectType === '' || ! class_exists($subjectType)) {
            return null;
        }

        if ($subjectType === self::class || ! is_subclass_of($subjectType, Model::class)) {
            return null;
        }

        if ($this->subject_id === null || $this->subject_id === '') {
            return null;
        }

        try {
            /** @var class-string<Model> $subjectType */
            return $subjectType::query()->find($this->subject_id);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveModuleContext(?Model $subject, Collection $explicitAudit): array
    {
        $explicitName = $explicitAudit->get('module_name');
        $explicitId = $explicitAudit->get('module_id');
        $explicitFeature = $explicitAudit->get('feature_name');

        if ($explicitName || $explicitId || $explicitFeature) {
            return [
                'id' => $explicitId,
                'name' => $explicitName,
                'feature' => $explicitFeature,
            ];
        }

        $route = app()->bound('request') ? request()->route() : null;
        $controller = is_array($route?->getAction()) ? ($route->getAction()['controller'] ?? null) : null;
        $routeName = $route?->getName();
        $segment = app()->bound('request') ? request()->segment(1) : null;

        $subjectNamespace = $subject ? get_class($subject) : (string) $this->subject_type;
        $controllerNamespace = is_string($controller) ? $controller : '';
        $source = trim($segment . ' ' . $routeName . ' ' . $controllerNamespace . ' ' . $subjectNamespace);

        [$moduleId, $moduleName] = $this->mapSourceToModule($source);

        return [
            'id' => $moduleId,
            'name' => $moduleName,
            'feature' => $this->resolveFeatureName($routeName, $controllerNamespace),
        ];
    }

    protected function resolveFeatureName(?string $routeName, string $controllerNamespace): ?string
    {
        if (is_string($routeName) && $routeName !== '') {
            $humanized = str($routeName)
                ->replace(['hr.', 'crm.', 'procurement.', 'inventory.', 'property.', 'fleet.', 'dms.', 'legal.', 'assets.', 'settings.', 'budget.'], '')
                ->replace('.', ' / ')
                ->replace('-', ' ')
                ->title()
                ->toString();

            return $humanized !== '' ? $humanized : null;
        }

        if ($controllerNamespace !== '') {
            $parts = explode('@', $controllerNamespace);
            $controllerName = class_basename($parts[0] ?? '');
            $method = $parts[1] ?? null;

            return trim(str_replace('Controller', '', $controllerName) . ($method ? ' / ' . Str::headline($method) : ''));
        }

        return null;
    }

    protected function resolveEntityName(?Model $subject): ?string
    {
        if ($subject instanceof Model) {
            return Str::headline(class_basename($subject));
        }

        if (is_string($this->subject_type) && $this->subject_type !== '') {
            return Str::headline(class_basename($this->subject_type));
        }

        return null;
    }

    protected function resolveReferenceNo(?Model $subject, Collection $properties): ?string
    {
        $candidates = [
            'ReferenceNo',
            'ReferenceNO',
            'Reference',
            'RefNo',
            'Ref',
            'Number',
            'DocumentNo',
            'DocumentNumber',
            'VoucherNo',
            'PaymentVoucherNo',
            'JournalNo',
            'JournalNumber',
            'InvoiceNo',
            'ReceiptNo',
            'TenderNo',
            'TenderID',
            'RFQNumber',
            'RFQNo',
            'RequisitionNo',
            'PlanRef',
            'PlanNumber',
            'SupplierID',
            'ItemCode',
        ];

        foreach ($candidates as $field) {
            $value = null;

            if ($subject instanceof Model) {
                $value = data_get($subject, $field);
            }

            if (($value === null || $value === '') && $properties->isNotEmpty()) {
                $value = data_get($properties, "attributes.{$field}")
                    ?? data_get($properties, "old.{$field}")
                    ?? data_get($properties, $field);
            }

            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : json_encode($value);
            }
        }

        return null;
    }

    protected function resolveValuePayloads(Collection $properties, Collection $explicitAudit): array
    {
        $old = $explicitAudit->get('old_values', data_get($properties, 'old', []));
        $new = $explicitAudit->get('new_values', data_get($properties, 'attributes', []));

        $normalizedOld = $this->maskSensitiveValues($this->normalizePayload($old));
        $normalizedNew = $this->maskSensitiveValues($this->normalizePayload($new));

        return [
            'old' => $normalizedOld !== [] ? $normalizedOld : null,
            'new' => $normalizedNew !== [] ? $normalizedNew : null,
            'changed' => $explicitAudit->get('changed_fields')
                ?? ($normalizedOld !== [] || $normalizedNew !== []
                    ? $this->calculateChangedFields($normalizedOld, $normalizedNew)
                    : null),
        ];
    }

    protected function normalizePayload(mixed $payload): array
    {
        if ($payload instanceof Collection) {
            $payload = $payload->toArray();
        }

        if ($payload instanceof Model) {
            $payload = $payload->toArray();
        }

        if (! is_array($payload)) {
            return [];
        }

        $normalized = [];

        foreach ($payload as $key => $value) {
            if ($value instanceof Collection) {
                $normalized[$key] = $this->normalizePayload($value->toArray());
            } elseif ($value instanceof Model) {
                $normalized[$key] = $this->normalizePayload($value->toArray());
            } elseif ($value instanceof \DateTimeInterface) {
                $normalized[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalizePayload($value);
            } elseif (is_object($value)) {
                $normalized[$key] = method_exists($value, 'toArray')
                    ? $this->normalizePayload($value->toArray())
                    : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    protected function maskSensitiveValues(array $payload): array
    {
        $sensitiveFragments = [
            'password',
            'token',
            'secret',
            'otp',
            'pin',
            'api_key',
            'apikey',
            'access_token',
            'refresh_token',
            'remember_token',
            'private_key',
            'authorization',
            'card_number',
            'cvv',
        ];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->maskSensitiveValues($value);

                continue;
            }

            $normalizedKey = Str::snake((string) $key);
            $isSensitive = collect($sensitiveFragments)
                ->contains(static fn (string $fragment): bool => Str::contains($normalizedKey, $fragment));

            if (! $isSensitive) {
                continue;
            }

            $payload[$key] = $this->maskScalarValue($value);
        }

        return $payload;
    }

    protected function maskScalarValue(mixed $value): string
    {
        $stringValue = is_scalar($value) ? (string) $value : '********';

        if ($stringValue === '') {
            return '********';
        }

        if (mb_strlen($stringValue) <= 4) {
            return '********';
        }

        return str_repeat('*', max(mb_strlen($stringValue) - 4, 4)) . mb_substr($stringValue, -4);
    }

    protected function calculateChangedFields(array $old, array $new): array
    {
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $changed = [];

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if (json_encode($oldValue) !== json_encode($newValue)) {
                $changed[] = (string) $key;
            }
        }

        return $changed !== [] ? array_values($changed) : [];
    }

    protected function mapSourceToModule(string $source): array
    {
        $source = Str::lower($source);

        $map = [
            [ModulesEnum::HRM, [' hr', 'app\\http\\controllers\\hr\\', 'app\\http\\controllers\\hrm\\']],
            [ModulesEnum::DMS, ['dms', 'document management', 'app\\http\\controllers\\dms\\']],
            [ModulesEnum::Settings, ['settings', 'app\\http\\controllers\\settings\\']],
        ];

        foreach ($map as [$module, $needles]) {
            foreach ($needles as $needle) {
                if (Str::contains($source, Str::lower($needle))) {
                    return [$module->value, $module->description()];
                }
            }
        }

        return [null, 'General'];
    }

    protected function detectDeviceName(?string $userAgent): ?string
    {
        $userAgent = Str::lower((string) $userAgent);

        if ($userAgent === '') {
            return null;
        }

        return match (true) {
            Str::contains($userAgent, ['android']) => 'Android Device',
            Str::contains($userAgent, ['iphone', 'ipad', 'ios']) => 'iOS Device',
            Str::contains($userAgent, ['windows']) => 'Windows Device',
            Str::contains($userAgent, ['macintosh', 'mac os']) => 'Mac Device',
            Str::contains($userAgent, ['linux']) => 'Linux Device',
            default => 'Unknown Device',
        };
    }
}

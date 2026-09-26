<?php

namespace App\Traits\Model;

use App\Enums\Core\ModulesEnum;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsHrTalentActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => ucfirst($event).' '.Str::headline(class_basename($this)));
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $properties = collect($activity->properties ?? []);
        $entity = Str::headline(class_basename($this));
        $reference = $this->PlanNo ?? $this->NeedNo ?? $this->RequestNo ?? $this->CaseNo ?? $this->MemberNo ?? $this->ApplicationNo ?? $this->Code ?? (string) $this->getKey();
        $feature = match (true) {
            Str::contains($entity, ['Learning', 'Training']) => 'Training, Learning & Development',
            Str::contains($entity, 'Probation') => 'Probation and Confirmation',
            Str::contains($entity, 'Pension') => 'Pension Administration',
            Str::contains($entity, 'Leave Encashment') => 'Leave Encashment',
            Str::contains($entity, 'Staff Loan') => 'Staff Loans',
            default => 'Performance Improvement',
        };
        $properties->put('audit', [
            'module_id' => ModulesEnum::HRM->value,
            'module_name' => ModulesEnum::HRM->description(),
            'feature_name' => $feature,
            'entity_name' => $entity,
            'table_name' => $this->getTable(),
            'record_id' => (string) $this->getKey(),
            'reference_no' => $reference,
            ...((array) $properties->get('audit', [])),
        ]);
        $activity->properties = $properties;
    }
}

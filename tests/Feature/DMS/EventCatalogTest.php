<?php

namespace Tests\Feature\DMS;

use App\Enums\Core\DataTypesEnum;
use App\Models\Auth\User;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\EventType;
use App\Models\DMS\SourceSystem;
use App\Services\DMS\EventCatalogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers what an external system is offered from a given screen: the events mapped to its
 * module, with the document type rules and the property form definition.
 *
 * Plain inserts only (no stored procedures), so everything runs inside a rolled-back
 * transaction. Needs a SQL Server connection with the t_DMSSourceSystems / t_DMSEvent*
 * migrations applied.
 */
class EventCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $id = 'TU' . Str::upper(Str::random(8));
        $this->actor = User::create([
            'UserID' => $id,
            'Name' => 'Test ' . $id,
            'Email' => Str::lower($id) . '@example.test',
            'Phone' => '9' . random_int(100000000, 999999999) . Str::upper(Str::random(4)),
            'Linked' => false,
            'Notes' => 'created by EventCatalogTest',
            'Password' => 'test-account',
            'IsActive' => true,
            'CreatedBy' => 1,
            'ModifiedBy' => 1,
        ]);
    }

    public function test_module_lists_only_its_own_active_events_with_the_form_definition(): void
    {
        $system = $this->makeSystem('CBS');
        $event = $this->makeEvent($system, 'ACC_ID', [1330, 4032]);
        $this->addProperty($event, 'cbs.class', 'Document class', DataTypesEnum::List, true, [['value' => 'IDEN', 'label' => 'Identification']]);
        $this->addProperty($event, 'cbs.remarks', 'Remarks', DataTypesEnum::String, false);

        $catalog = app(EventCatalogService::class)->forModule($system, 1330);

        $this->assertCount(1, $catalog);
        $this->assertSame('ACC_ID', $catalog[0]['code']);
        $this->assertSame('ACCOUNT', $catalog[0]['externalEntityType']);
        $this->assertSame(['name' => 'cbs.class', 'label' => 'Document class', 'type' => 'list', 'required' => true, 'options' => [['value' => 'IDEN', 'label' => 'Identification']]], $catalog[0]['properties'][0]);
        $this->assertSame('string', $catalog[0]['properties'][1]['type']);
        $this->assertNull($catalog[0]['properties'][1]['options']);
        $this->assertSame(['pdf', 'jpg'], $catalog[0]['documentTypes'][0]['allowedExtensions']);

        $this->assertSame([], app(EventCatalogService::class)->forModule($system, 9999), 'a module with no mapping gets nothing');
    }

    public function test_events_can_be_narrowed_by_record_type(): void
    {
        $system = $this->makeSystem('CBS');
        $this->makeEvent($system, 'ACC_ID', [1330], 'ACCOUNT');
        $this->makeEvent($system, 'LOAN_DOC', [1330], 'LOAN');

        $catalog = app(EventCatalogService::class)->forModule($system, 1330, 'loan');

        $this->assertSame(['LOAN_DOC'], array_column($catalog, 'code'));
    }

    public function test_inactive_event_or_document_type_or_system_drops_out_of_the_catalog(): void
    {
        $system = $this->makeSystem('CBS');
        $off = $this->makeEvent($system, 'OFF', [1330]);
        $off->update(['IsActive' => false, 'ModifiedBy' => $this->actor->Id]);

        $typeOff = $this->makeEvent($system, 'TYPE_OFF', [1330]);
        $typeOff->documentTypes()->first()->update(['IsActive' => false, 'ModifiedBy' => $this->actor->Id]);

        $live = $this->makeEvent($system, 'LIVE', [1330]);

        $service = app(EventCatalogService::class);
        $this->assertSame(['LIVE'], array_column($service->forModule($system, 1330), 'code'));

        $system->update(['IsActive' => false, 'ModifiedBy' => $this->actor->Id]);
        $this->assertSame([], $service->forModule($system->fresh(), 1330));
        $this->assertNull($service->resolve($system->fresh(), $live->Code, 1330));
    }

    public function test_a_system_never_sees_another_systems_events(): void
    {
        $cbs = $this->makeSystem('CBS');
        $other = $this->makeSystem('OTHER');
        $this->makeEvent($other, 'THEIRS', [1330]);

        $this->assertSame([], app(EventCatalogService::class)->forModule($cbs, 1330));
    }

    public function test_resolve_only_returns_an_event_the_module_may_raise(): void
    {
        $system = $this->makeSystem('CBS');
        $this->makeEvent($system, 'ACC_ID', [1330]);

        $service = app(EventCatalogService::class);
        $this->assertSame('ACC_ID', $service->resolve($system, 'ACC_ID', 1330)?->Code);
        $this->assertNull($service->resolve($system, 'ACC_ID', 1094), 'raised from a screen it is not mapped to');
        $this->assertNull($service->resolve($system, 'NOPE', 1330));
    }

    public function test_list_data_type_accepts_scalars_and_formats_as_text(): void
    {
        $this->assertTrue(DataTypesEnum::List->isValid('IDEN'));
        $this->assertFalse(DataTypesEnum::List->isValid(['IDEN']));
        $this->assertSame('IDEN', DataTypesEnum::List->val('IDEN'));
    }

    private function makeSystem(string $code): SourceSystem
    {
        return SourceSystem::create([
            'Code' => $code . '_' . Str::upper(Str::random(5)),
            'Name' => $code . ' test system',
            'ServiceUserId' => $this->actor->Id,
            'IsActive' => true,
            'CreatedBy' => $this->actor->Id,
            'ModifiedBy' => $this->actor->Id,
        ]);
    }

    /**
     * @param array<int, int> $modules
     */
    private function makeEvent(SourceSystem $system, string $code, array $modules, string $entityType = 'ACCOUNT'): EventType
    {
        $type = DMSDocumentType::create([
            'Code' => 'T_' . Str::upper(Str::random(8)),
            'Name' => 'Test type ' . $code,
            'AllowedExtensions' => 'pdf,jpg',
            'MaxSizeMB' => 5,
            'ExpiryRequired' => false,
            'IsActive' => true,
            'CreatedBy' => $this->actor->Id,
            'ModifiedBy' => $this->actor->Id,
        ]);

        $event = EventType::create([
            'SourceSystemId' => $system->Id,
            'Code' => $code,
            'Name' => 'Event ' . $code,
            'ExternalEntityType' => $entityType,
            'AllowReplace' => true,
            'IsActive' => true,
            'CreatedBy' => $this->actor->Id,
            'ModifiedBy' => $this->actor->Id,
        ]);
        $event->documentTypes()->attach($type->Id, ['CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);

        foreach ($modules as $moduleId) {
            $event->modules()->create(['SourceModuleId' => $moduleId, 'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);
        }

        return $event;
    }

    /**
     * @param array<int, array{value: string, label: string}>|null $options
     */
    private function addProperty(EventType $event, string $name, string $label, DataTypesEnum $type, bool $required, ?array $options = null): void
    {
        $event->properties()->create([
            'Name' => $name,
            'Label' => $label,
            'DataType' => $type->value,
            'IsRequired' => $required,
            'Options' => $options,
            'SortOrder' => $event->properties()->count(),
            'CreatedBy' => $this->actor->Id,
            'ModifiedBy' => $this->actor->Id,
        ]);
    }
}

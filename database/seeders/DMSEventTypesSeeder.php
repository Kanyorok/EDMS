<?php

namespace Database\Seeders;

use App\Enums\Core\DataTypesEnum;
use App\Helpers\SystemHelper;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\EventType;
use App\Models\DMS\SourceSystem;
use Illuminate\Database\Seeder;

class DMSEventTypesSeeder extends Seeder
{
    /**
     * Seeds core banking (CBS) as a source system and a starter set of upload events for
     * the CBS document screens, so an admin edits rather than builds from nothing. An event is a
     * business occasion ("Account documents") that offers a LIST of document types; the person filing
     * in CBS picks which document it is, and that document type's own rules then apply.
     *
     * The events are seeded INACTIVE: which document types belong on which CBS screen is
     * a business decision, and an active event is immediately offered to CBS users. The CBS
     * source system is attributed to the system user until an admin points it at a
     * dedicated service account (Source Systems screen).
     *
     * Module IDs are CBS's own: 1330 Account Documents, 4032 Overdraft Maintenance
     * documents, 4063 Overdraft Application documents, 1097 Loan Application documents,
     * 1095 Loan Sanction documents, 1094 Loan Maintenance documents, 1098 Loan Disbursement.
     *
     * Idempotent on (source system, Code): an existing event, even a soft-deleted one, is left
     * untouched. A document type that does not exist is left out of the list; an event with none
     * of its types present is skipped rather than failing the run.
     */
    public function run(): void
    {
        $actor = SystemHelper::user();

        $system = SourceSystem::withTrashed()->where('Code', 'CBS')->first()
            ?? SourceSystem::create([
                'Code' => 'CBS',
                'Name' => 'Core Banking System',
                'Description' => 'Nimble core banking, reached through the API gateway.',
                'ServiceUserId' => $actor->Id,
                'IsActive' => true,
                'CreatedBy' => $actor->Id,
                'ModifiedBy' => $actor->Id,
            ]);

        $documentTypes = DMSDocumentType::query()->whereNull('DeletedOn')->get()->keyBy('Code');

        foreach ($this->events() as [$code, $name, $documentTypeCodes, $entityType, $modules]) {
            $types = collect($documentTypeCodes)->map(fn ($typeCode) => $documentTypes[$typeCode] ?? null)->filter()->values();
            if ($types->isEmpty() || EventType::withTrashed()->where('SourceSystemId', $system->Id)->where('Code', $code)->exists()) {
                continue;
            }

            $event = EventType::create([
                'SourceSystemId' => $system->Id,
                'Code' => $code,
                'Name' => $name,
                // Left empty: each document type has its own linked-to type and the upload uses that one.
                'LinkedToTypeId' => null,
                'ExternalEntityType' => $entityType,
                'AllowReplace' => true,
                'IsActive' => false,
                'CreatedBy' => $actor->Id,
                'ModifiedBy' => $actor->Id,
            ]);

            $event->documentTypes()->sync($types->mapWithKeys(fn ($type) => [$type->Id => ['CreatedBy' => $actor->Id, 'ModifiedBy' => $actor->Id]])->all());

            foreach ($modules as $moduleId) {
                $event->modules()->create(['SourceModuleId' => $moduleId, 'CreatedBy' => $actor->Id, 'ModifiedBy' => $actor->Id]);
            }

            foreach ($this->properties() as $order => $property) {
                $event->properties()->create($property + ['SortOrder' => $order, 'CreatedBy' => $actor->Id, 'ModifiedBy' => $actor->Id]);
            }
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: array<int, string>, 3: string, 4: array<int, int>}>
     */
    private function events(): array
    {
        return [
            // Documents held against a deposit account (the Account Maintenance > Documents screen and its overdraft variants).
            ['CBS_ACCOUNT_DOCUMENTS', 'Account documents',
                ['NATIONAL_ID', 'PASSPORT_PHOTO', 'PROOF_OF_RESIDENCE', 'PAYSLIP'],
                'ACCOUNT', [1330, 4032, 4063]],

            // Documents that support a loan application (application and sanction screens).
            ['CBS_APPLICATION_DOCUMENTS', 'Loan application documents',
                ['LOAN_APP_FORM', 'EMPLOYER_CONFIRMATION', 'LOAN_OFFER_LETTER', 'NATIONAL_ID', 'PAYSLIP', 'PROOF_OF_RESIDENCE',
                    'VEHICLE_LOGBOOK', 'VEHICLE_INSURANCE', 'TITLE_DEED', 'VALUATION_REPORT'],
                'APPLICATION', [1097, 1095]],

            // Documents of a disbursed loan (loan maintenance and disbursement screens).
            ['CBS_LOAN_DOCUMENTS', 'Loan documents',
                ['LOAN_AGREEMENT', 'PAYROLL_DEDUCTION_FORM', 'CREDIT_LIFE_CERT', 'DEMAND_LETTER'],
                'LOAN', [1094, 1098]],
        ];
    }

    /**
     * CBS's own document fields, kept as DMS properties so a document is complete in DMS
     * without the CBS row. Names are what land on the document as attributes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function properties(): array
    {
        return [
            ['Name' => 'cbs.class', 'Label' => 'Document class', 'DataType' => DataTypesEnum::List->value, 'IsRequired' => true, 'Options' => [
                ['value' => 'IDEN', 'label' => 'Identification'],
                ['value' => 'ADDR', 'label' => 'Address'],
                ['value' => 'INCM', 'label' => 'Income'],
                ['value' => 'SECU', 'label' => 'Security'],
                ['value' => 'OTHE', 'label' => 'Other'],
            ]],
            ['Name' => 'cbs.location', 'Label' => 'Location', 'DataType' => DataTypesEnum::String->value, 'IsRequired' => false, 'Options' => null],
            ['Name' => 'cbs.received_by', 'Label' => 'Received by', 'DataType' => DataTypesEnum::String->value, 'IsRequired' => true, 'Options' => null],
            ['Name' => 'cbs.received_date', 'Label' => 'Received date', 'DataType' => DataTypesEnum::DateTime->value, 'IsRequired' => true, 'Options' => null],
            ['Name' => 'cbs.remarks', 'Label' => 'Remarks', 'DataType' => DataTypesEnum::String->value, 'IsRequired' => false, 'Options' => null],
        ];
    }
}

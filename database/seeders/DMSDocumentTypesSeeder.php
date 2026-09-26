<?php

namespace Database\Seeders;

use App\Helpers\SystemHelper;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\DMSTags;
use App\Models\DMS\LinkedToType;
use App\Models\DMS\Repository;
use Illuminate\Database\Seeder;

class DMSDocumentTypesSeeder extends Seeder
{
    /**
     * Seeds upload rules for the document kinds PSMFC actually handles: KYC
     * on a borrower, the loan application and agreement, collateral for
     * asset-finance/mortgage products, recovery/insurance paperwork, and a
     * couple of internal staff document kinds.
     *
     * Repository/Tag/LinkedToTypes references are resolved by Name/Code at
     * run time (not hardcoded IDs) so this only wires up correctly once
     * DmsRegistrySeeder, DMSTagsSeeder, DMSLinkedToTypesSeeder and (for the
     * repository name used here) whatever created PSMFC_Registry/Internal
     * have already run - a missing lookup just leaves that column/relation
     * unset rather than failing the whole run.
     *
     * EntityApplicability is constrained to the six values already seeded in
     * t_CodeDetails (CodeID = DMSEntityApplicabilityId): Account, Application,
     * Collateral, Customer, Loan, Employee - it is not the same lookup as
     * DMSLinkedToTypesSeeder's entity list, so LinkedToTypes below is listed
     * separately per document type rather than derived from it (a couple of
     * document types deserve a more specific Linked-to Type than their
     * broad EntityApplicability bucket - e.g. VEHICLE_LOGBOOK also links to
     * VEHICLE, not just COLLATERAL). STAFF_ID/STAFF_CONTRACT have no
     * LinkedToTypes entry: PSMFC's seeded list only has EMPLOYER (the
     * outside organisation a borrower works for), not an internal-staff
     * type, so there's nothing to link them to yet.
     *
     * Idempotent on Code for the row itself: an existing type (even
     * soft-deleted) is left untouched. Its LinkedToTypes are the exception -
     * they're backfilled whenever a type currently has none configured, the
     * same rationale as DMSLinkedToTypesSeeder's SourceRelation backfill.
     */
    public function run(): void
    {
        $actor = SystemHelper::user();

        $repositoryIds = Repository::query()->whereNull('DeletedOn')->pluck('Id', 'Name');
        $tagIds = DMSTags::query()->whereNull('DeletedOn')->pluck('Id', 'Name');
        $linkedToTypeIds = LinkedToType::query()->whereNull('DeletedOn')->pluck('Id', 'Code');

        foreach ($this->types() as $type) {
            $documentType = DMSDocumentType::withTrashed()->where('Code', $type['Code'])->first();

            if (! $documentType) {
                $documentType = DMSDocumentType::create([
                    'Code' => $type['Code'],
                    'Name' => $type['Name'],
                    'RepositoryId' => $repositoryIds[$type['Repository']] ?? null,
                    'TagId' => $type['Tag'] ? ($tagIds[$type['Tag']] ?? null) : null,
                    'EntityApplicability' => $type['EntityApplicability'],
                    'AllowedExtensions' => $type['AllowedExtensions'],
                    'MaxSizeMB' => $type['MaxSizeMB'],
                    'ExpiryRequired' => $type['ExpiryRequired'],
                    'IsActive' => true,
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                ]);
            }

            if ($type['LinkedToTypes'] === [] || $documentType->linkedToTypes()->exists()) {
                continue;
            }

            $ids = collect($type['LinkedToTypes'])->map(fn ($code) => $linkedToTypeIds[$code] ?? null)->filter()->values();
            if ($ids->isNotEmpty()) {
                $documentType->linkedToTypes()->attach($ids, ['CreatedBy' => $actor->Id, 'ModifiedBy' => $actor->Id]);
            }
        }
    }

    /**
     * @return array<int, array{Code: string, Name: string, Repository: string, Tag: ?string, EntityApplicability: string, LinkedToTypes: array<string>, AllowedExtensions: string, MaxSizeMB: int, ExpiryRequired: bool}>
     */
    private function types(): array
    {
        return [
            // Borrower KYC
            ['Code' => 'NATIONAL_ID', 'Name' => 'National Registration Card', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Member KYC', 'EntityApplicability' => 'Customer', 'LinkedToTypes' => ['CUSTOMER'], 'AllowedExtensions' => 'pdf,jpg,png', 'MaxSizeMB' => 5, 'ExpiryRequired' => false],
            ['Code' => 'PASSPORT_PHOTO', 'Name' => 'Passport Photo', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Member KYC', 'EntityApplicability' => 'Customer', 'LinkedToTypes' => ['CUSTOMER'], 'AllowedExtensions' => 'jpg,png', 'MaxSizeMB' => 2, 'ExpiryRequired' => false],
            ['Code' => 'PROOF_OF_RESIDENCE', 'Name' => 'Proof of Residence', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Member KYC', 'EntityApplicability' => 'Customer', 'LinkedToTypes' => ['CUSTOMER'], 'AllowedExtensions' => 'pdf,jpg,png', 'MaxSizeMB' => 5, 'ExpiryRequired' => false],
            ['Code' => 'PAYSLIP', 'Name' => 'Payslip', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Member KYC', 'EntityApplicability' => 'Customer', 'LinkedToTypes' => ['CUSTOMER'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 5, 'ExpiryRequired' => false],

            // Loan application
            ['Code' => 'LOAN_APP_FORM', 'Name' => 'Loan Application Form', 'Repository' => 'PSMFC_Registry', 'Tag' => null, 'EntityApplicability' => 'Application', 'LinkedToTypes' => ['LOAN_APP'], 'AllowedExtensions' => 'pdf,docx', 'MaxSizeMB' => 10, 'ExpiryRequired' => false],
            ['Code' => 'EMPLOYER_CONFIRMATION', 'Name' => 'Employer Confirmation Letter', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Public Service Worker', 'EntityApplicability' => 'Application', 'LinkedToTypes' => ['LOAN_APP'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 5, 'ExpiryRequired' => false],
            ['Code' => 'LOAN_OFFER_LETTER', 'Name' => 'Loan Offer Letter', 'Repository' => 'PSMFC_Registry', 'Tag' => null, 'EntityApplicability' => 'Application', 'LinkedToTypes' => ['LOAN_APP'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 5, 'ExpiryRequired' => false],

            // Loan account / agreement
            ['Code' => 'LOAN_AGREEMENT', 'Name' => 'Loan Agreement', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Loan Agreement', 'EntityApplicability' => 'Loan', 'LinkedToTypes' => ['LOAN_ACC'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 10, 'ExpiryRequired' => false],
            ['Code' => 'PAYROLL_DEDUCTION_FORM', 'Name' => 'Payroll Deduction Authorization', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Payroll Deduction', 'EntityApplicability' => 'Loan', 'LinkedToTypes' => ['LOAN_ACC', 'PAYROLL_DEDUCTION'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 5, 'ExpiryRequired' => false],
            ['Code' => 'CREDIT_LIFE_CERT', 'Name' => 'Credit Life Insurance Certificate', 'Repository' => 'PSMFC_Registry', 'Tag' => null, 'EntityApplicability' => 'Loan', 'LinkedToTypes' => ['LOAN_ACC', 'INSURANCE_CLAIM'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 5, 'ExpiryRequired' => true],
            ['Code' => 'DEMAND_LETTER', 'Name' => 'Recovery Demand Letter', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Loan Recovery', 'EntityApplicability' => 'Loan', 'LinkedToTypes' => ['LOAN_ACC', 'RECOVERY_CASE'], 'AllowedExtensions' => 'pdf,docx', 'MaxSizeMB' => 5, 'ExpiryRequired' => false],

            // Collateral (motor vehicle asset finance / home ownership)
            ['Code' => 'VEHICLE_LOGBOOK', 'Name' => 'Motor Vehicle Logbook', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Motor Vehicle Asset Finance', 'EntityApplicability' => 'Collateral', 'LinkedToTypes' => ['COLLATERAL', 'VEHICLE'], 'AllowedExtensions' => 'pdf,jpg,png', 'MaxSizeMB' => 10, 'ExpiryRequired' => false],
            ['Code' => 'VEHICLE_INSURANCE', 'Name' => 'Vehicle Insurance Certificate', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Motor Vehicle Asset Finance', 'EntityApplicability' => 'Collateral', 'LinkedToTypes' => ['COLLATERAL', 'VEHICLE'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 5, 'ExpiryRequired' => true],
            ['Code' => 'TITLE_DEED', 'Name' => 'Property Title Deed', 'Repository' => 'PSMFC_Registry', 'Tag' => 'Home Ownership Loan', 'EntityApplicability' => 'Collateral', 'LinkedToTypes' => ['COLLATERAL', 'PROPERTY'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 10, 'ExpiryRequired' => false],
            ['Code' => 'VALUATION_REPORT', 'Name' => 'Collateral Valuation Report', 'Repository' => 'PSMFC_Registry', 'Tag' => null, 'EntityApplicability' => 'Collateral', 'LinkedToTypes' => ['COLLATERAL'], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 10, 'ExpiryRequired' => true],

            // PSMFC staff - no matching Linked-to Type yet (PSMFC's seeded
            // list only covers the external employer, not internal staff)
            ['Code' => 'STAFF_ID', 'Name' => 'Staff Identification', 'Repository' => 'Internal', 'Tag' => null, 'EntityApplicability' => 'Employee', 'LinkedToTypes' => [], 'AllowedExtensions' => 'pdf,jpg,png', 'MaxSizeMB' => 5, 'ExpiryRequired' => false],
            ['Code' => 'STAFF_CONTRACT', 'Name' => 'Staff Employment Contract', 'Repository' => 'Internal', 'Tag' => 'HR Policy', 'EntityApplicability' => 'Employee', 'LinkedToTypes' => [], 'AllowedExtensions' => 'pdf', 'MaxSizeMB' => 10, 'ExpiryRequired' => false],
        ];
    }
}

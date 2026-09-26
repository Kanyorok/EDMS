<?php

namespace Database\Seeders;

use App\Helpers\SystemHelper;
use App\Models\DMS\LinkedToType;
use Illuminate\Database\Seeder;

class DMSLinkedToTypesSeeder extends Seeder
{
    /**
     * Seeds the business entity types PSMFC documents get linked to, covering
     * the loan lifecycle for a public-service microfinance lender: the
     * borrower, their employer, the loan itself, its security, and what
     * happens when it goes wrong (recovery, write-off, insurance claim).
     *
     * Idempotent on Code for the row itself: an existing type (even
     * soft-deleted) is left untouched. SourceRelation is the exception -
     * it's backfilled onto an existing row whenever it's still empty, since
     * that column didn't exist when this seeder first ran on most
     * environments and would otherwise stay blank forever. A SourceRelation
     * an admin has already set (or deliberately cleared) is never
     * overwritten.
     */
    public function run(): void
    {
        $actor = SystemHelper::user();
        $now = now();

        foreach ($this->types() as [$code, $name, $description, $sourceRelation]) {
            $existing = LinkedToType::withTrashed()->where('Code', $code)->first();

            if ($existing) {
                if ($sourceRelation && ! $existing->SourceRelation) {
                    $existing->update(['SourceRelation' => $sourceRelation, 'ModifiedBy' => $actor->Id]);
                }

                continue;
            }

            LinkedToType::create([
                'Code' => $code,
                'Name' => $name,
                'Description' => $description,
                'SourceRelation' => $sourceRelation,
                'IsActive' => true,
                'CreatedBy' => $actor->Id,
                'ModifiedBy' => $actor->Id,
                'CreatedOn' => $now,
                'ModifiedOn' => $now,
            ]);
        }
    }

    /**
     * SourceRelation is only set for types that map cleanly onto one of
     * DocumentRelationOptionsController::RELATION_TYPES' three live lookups
     * (Client/Lead/Employee). The rest have no matching source-of-record
     * model yet, so they're left null and keep the upload form's manual
     * "Related to" picker - see that controller's doc comment.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: ?string}>
     */
    private function types(): array
    {
        $client = 'ClientID';
        $lead = 'LeadID';

        return [
            ['CUSTOMER', 'Customer / Member', 'A public service employee holding an account or loan with PSMFC.', $client],
            ['EMPLOYER', 'Employer', 'The government ministry, agency or partner employer (e.g. ZESCO) a borrower works for.', null],
            ['LOAN_APP', 'Loan Application', 'An application for a personal, motor vehicle asset finance, home ownership or other loan.', $lead],
            ['LOAN_ACC', 'Loan Account', 'A disbursed and active loan account.', null],
            ['GUARANTOR', 'Guarantor', 'A person or institution guaranteeing a loan.', $client],
            ['NEXT_OF_KIN', 'Next of Kin', 'A borrower\'s declared next of kin or beneficiary.', null],
            ['COLLATERAL', 'Collateral / Security', 'An asset pledged as security against a loan.', null],
            ['VEHICLE', 'Financed Vehicle', 'A motor vehicle financed under the asset finance product.', null],
            ['PROPERTY', 'Financed Property', 'A property financed under the home ownership loan product.', null],
            ['LOAN_COMMITTEE', 'Loan Committee Case', 'A loan application or exception referred to the loan/credit committee.', null],
            ['PAYROLL_DEDUCTION', 'Payroll Deduction', 'A check-off/payroll deduction schedule submitted by an employer.', null],
            ['RECOVERY_CASE', 'Loan Recovery Case', 'An arrears management or recovery case on a loan account.', null],
            ['WRITE_OFF', 'Loan Write-off Case', 'A loan account referred for or subject to write-off.', null],
            ['INSURANCE_CLAIM', 'Credit Life Insurance Claim', 'A claim under a loan\'s credit life/insurance cover.', null],
            ['BRANCH', 'Branch', 'A PSMFC branch or service point.', null],
        ];
    }
}

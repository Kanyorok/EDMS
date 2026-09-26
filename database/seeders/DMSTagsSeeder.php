<?php

namespace Database\Seeders;

use App\Enums\Core\VisibilityEnum;
use App\Helpers\SystemHelper;
use App\Models\DMS\DMSTags;
use App\Services\DMS\TagService;
use Illuminate\Database\Seeder;

class DMSTagsSeeder extends Seeder
{
    /**
     * Seeds document classification tags reflecting PSMFC's business: its
     * loan products for public service workers, its statutory/regulatory
     * environment as a Zambian government-owned microfinance institution,
     * and its key strategic partnerships (ZESCO, NATSAVE).
     *
     * TagID assignment goes through TagService::create() (sequential
     * T0001... allocation), the same path the DMS tags UI uses. Idempotent
     * on Name: a tag already present (even soft-deleted) is left untouched.
     */
    public function run(): void
    {
        $actor = SystemHelper::user();

        foreach ($this->tags() as [$name, $description, $visibility]) {
            $exists = DMSTags::withTrashed()->where('Name', $name)->exists();

            if ($exists) {
                continue;
            }

            TagService::create($name, $description, $actor, $visibility);
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: VisibilityEnum}>
     */
    private function tags(): array
    {
        return [
            // Loan products
            ['Personal Loan', 'Personal loan applications, offers and agreements for public service employees.', VisibilityEnum::Public],
            ['Motor Vehicle Asset Finance', 'Documents relating to motor vehicle asset finance loans.', VisibilityEnum::Public],
            ['Home Ownership Loan', 'Documents relating to home ownership and mortgage loan products.', VisibilityEnum::Public],
            ['Salary Advance', 'Short-term salary advance requests and approvals.', VisibilityEnum::Public],
            ['Emergency Loan', 'Emergency loan applications and supporting documents.', VisibilityEnum::Public],

            // Borrowers / members
            ['Public Service Worker', 'Documents identifying borrowers employed in the public service.', VisibilityEnum::Public],
            ['Member KYC', 'Know-Your-Customer identification and verification documents for members and borrowers.', VisibilityEnum::Private],
            ['Next of Kin', 'Next of kin and beneficiary declaration forms.', VisibilityEnum::Private],

            // Strategic partnerships
            ['ZESCO Partnership', 'Documents relating to the loan scheme partnership with ZESCO Limited.', VisibilityEnum::Public],
            ['NATSAVE Partnership', 'Documents relating to the service delivery partnership with NATSAVE.', VisibilityEnum::Public],
            ['Ministry of Finance', 'Correspondence and instructions from the Ministry of Finance as shareholder.', VisibilityEnum::Public],

            // Regulatory / compliance (Zambia-specific)
            ['Bank of Zambia Circular', 'Regulatory circulars and directives issued by the Bank of Zambia.', VisibilityEnum::Private],
            ['AML/CFT Compliance', 'Anti-money laundering and counter-terrorism financing compliance records.', VisibilityEnum::Private],
            ['Statutory Filing', 'NAPSA, NHIMA, ZRA and other statutory returns and filings.', VisibilityEnum::Private],
            ['PACRA Filing', 'Filings and correspondence with the Patents and Companies Registration Agency.', VisibilityEnum::Private],

            // Corporate governance
            ['Board Minutes', 'Minutes of Board and Board Committee meetings.', VisibilityEnum::Private],
            ['Board Resolution', 'Board resolutions authorising corporate action.', VisibilityEnum::Private],
            ['Annual General Meeting', 'AGM notices, resolutions and minutes.', VisibilityEnum::Private],

            // Loan servicing / finance
            ['Loan Agreement', 'Executed loan agreements and security documents.', VisibilityEnum::Private],
            ['Loan Disbursement', 'Loan disbursement instructions and confirmations.', VisibilityEnum::Private],
            ['Loan Recovery', 'Arrears management and loan recovery correspondence.', VisibilityEnum::Private],
            ['Payroll Deduction', 'Check-off/payroll deduction schedules submitted by employers.', VisibilityEnum::Private],

            // General corporate/admin
            ['Procurement', 'Tender, quotation and procurement contract documents.', VisibilityEnum::Public],
            ['HR Policy', 'Human resources policies and procedures.', VisibilityEnum::Public],
            ['Circular', 'Internal circulars and staff notices.', VisibilityEnum::Public],
        ];
    }
}

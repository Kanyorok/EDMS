<?php

namespace App\Enums\Core;

use App\Traits\UsefulEnumTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Permissions supported by the standalone DMS. */
enum PermissionEnum: string
{
    use UsefulEnumTrait;

    case DMSView = 'dms-view';
    case DMSBulkUpload = 'dms-bulkUpload';
    case DMSLegalHoldCreate = 'dmsLegalHold-create';
    case DMSLegalHoldView = 'dmsLegalHold-view';
    case DMSLegalHoldRelease = 'dmsLegalHold-release';
    case DMSValidationApprove = 'dmsValidation-approve';
    case DMSRegistryManage = 'dmsRegistry-manage';
    case DMSWidgetsCreate = 'dmswidgets-create';
    case DMSWidgetsUpdate = 'dmswidgets-update';
    case DMSWidgetsDelete = 'dmswidgets-delete';
    case DMSWidgetsView = 'dmswidgets-view';
    case DMSLayoutsCreate = 'dmslayouts-create';
    case DMSLayoutsUpdate = 'dmslayouts-update';
    case DMSLayoutsDelete = 'dmslayouts-delete';
    case DMSLayoutsView = 'dmslayouts-view';

    case Admin = 'admin';
    case Users = 'users';
    case UsersSessions = 'users-nonExpiringSessions';
    case Roles = 'roles';
    case Teams = 'teams';
    case ListsView = 'lists-view';
    case ListsUpdate = 'lists-update';
    case Integrations = 'integrations';
    case OrganizationProfile = 'organization-profile';
    case SetBaseCurrency = 'set-base-currency';
    case BranchView = 'branch-view';
    case BranchCreate = 'branch-create';
    case BranchUpdate = 'branch-update';
    case BranchDelete = 'branch-delete';
    case BranchSyncAct = 'branchSync-act';

    case WorkflowView = 'workflow-view';
    case WorkflowCreate = 'workflow-create';
    case WorkflowUpdate = 'workflow-update';
    case WorkflowDelete = 'workflow-delete';

    case EmployeesView = 'employee-read';
    case EmployeesCreate = 'employee-create';
    case EmployeesUpdate = 'employee-update';
    case EmployeesDelete = 'employee-delete';
    case Departments = 'department';

    public static function display(): Collection
    {
        return collect([
            [self::DMSView, self::DMSBulkUpload, self::DMSLegalHoldCreate, self::DMSLegalHoldView,
                self::DMSLegalHoldRelease, self::DMSValidationApprove, self::DMSRegistryManage],
            [self::DMSWidgetsView, self::DMSWidgetsCreate, self::DMSWidgetsUpdate, self::DMSWidgetsDelete],
            [self::DMSLayoutsView, self::DMSLayoutsCreate, self::DMSLayoutsUpdate, self::DMSLayoutsDelete],
            [self::Users, self::UsersSessions, self::Roles, self::Teams, self::Admin],
            [self::ListsView, self::ListsUpdate, self::Integrations, self::OrganizationProfile, self::SetBaseCurrency],
            [self::BranchView, self::BranchCreate, self::BranchUpdate, self::BranchDelete, self::BranchSyncAct],
            [self::WorkflowView, self::WorkflowCreate, self::WorkflowUpdate, self::WorkflowDelete],
            [self::EmployeesView, self::EmployeesCreate, self::EmployeesUpdate, self::EmployeesDelete, self::Departments],
        ]);
    }

    public static function approvals(): Collection
    {
        return collect([self::DMSValidationApprove]);
    }

    public function module(): ModulesEnum
    {
        return match ($this) {
            self::DMSView, self::DMSBulkUpload, self::DMSLegalHoldCreate, self::DMSLegalHoldView,
            self::DMSLegalHoldRelease, self::DMSValidationApprove, self::DMSRegistryManage,
            self::DMSWidgetsCreate, self::DMSWidgetsUpdate, self::DMSWidgetsDelete, self::DMSWidgetsView,
            self::DMSLayoutsCreate, self::DMSLayoutsUpdate, self::DMSLayoutsDelete, self::DMSLayoutsView
                => ModulesEnum::DMS,
            self::EmployeesView, self::EmployeesCreate, self::EmployeesUpdate, self::EmployeesDelete,
            self::Departments => ModulesEnum::HRM,
            default => ModulesEnum::Settings,
        };
    }

    public function subName(): string
    {
        return match ($this) {
            self::SetBaseCurrency => 'Set Base Currency',
            default => Str::of(array_reverse(explode('-', $this->value))[0])->snake(' ')->title()->toString(),
        };
    }

    public function isAssignableToRoles(): bool
    {
        return true;
    }

    public function isAssignableInRolesScreen(): bool
    {
        return in_array($this->module(), self::rolesScreenModules(), true);
    }

    /**
     * Modules whose permissions can be managed from the Roles screen.
     *
     * @return array<int, ModulesEnum>
     */
    public static function rolesScreenModules(): array
    {
        return [ModulesEnum::DMS, ModulesEnum::Settings, ModulesEnum::HRM];
    }

    public function title(): string
    {
        return match ($this) {
            self::DMSView, self::DMSBulkUpload => 'Documents',
            self::DMSLegalHoldCreate, self::DMSLegalHoldView, self::DMSLegalHoldRelease => 'Legal Holds',
            self::DMSValidationApprove => 'Document Validation',
            self::DMSRegistryManage => 'DMS Registry',
            self::DMSWidgetsCreate, self::DMSWidgetsUpdate, self::DMSWidgetsDelete, self::DMSWidgetsView => 'DMS Widgets',
            self::DMSLayoutsCreate, self::DMSLayoutsUpdate, self::DMSLayoutsDelete, self::DMSLayoutsView => 'DMS Layouts',
            self::Users, self::UsersSessions => 'Users',
            self::BranchView, self::BranchCreate, self::BranchUpdate, self::BranchDelete, self::BranchSyncAct => 'Branches',
            self::ListsView, self::ListsUpdate => 'Code Lists',
            self::WorkflowView, self::WorkflowCreate, self::WorkflowUpdate, self::WorkflowDelete => 'Workflows',
            self::EmployeesView, self::EmployeesCreate, self::EmployeesUpdate, self::EmployeesDelete => 'Employees',
            default => Str::of($this->name)->headline()->toString(),
        };
    }
}

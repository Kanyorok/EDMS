<?php

namespace Database\Seeders;

use App\Enums\Core\PermissionEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [700000, 'Document Management', null, null, null],
            [700100, 'Dashboard', 700000, 'documentdashboardwidgets.index', PermissionEnum::DMSWidgetsView->value],
            [701000, 'Recent Documents', 700000, 'repo.recent', PermissionEnum::DMSView->value],
            [702000, 'Repository', 700000, 'repo.index', PermissionEnum::DMSView->value],
            [703000, 'Tags', 700000, 'file-tags.index', PermissionEnum::DMSView->value],
            [704000, 'Bulk Upload', 700000, 'files.upload', PermissionEnum::DMSBulkUpload->value],
            [705000, 'Legal Hold', 700000, 'legal-hold.index', PermissionEnum::DMSLegalHoldView->value],
            [706000, 'Document Validation', 700000, 'dms.validation.index', PermissionEnum::DMSView->value],
            [706100, 'Document Review', 700000, 'dms.review.index', PermissionEnum::DMSView->value],
            [797000, 'Settings', 700000, null, PermissionEnum::DMSView->value],
            [797100, 'Signatures', 797000, 'document-signature.index', PermissionEnum::DMSView->value],
            [797200, 'Validation Types', 797000, 'document-validation-type.index', PermissionEnum::DMSView->value],
            [797300, 'Registry', 700000, 'storage-locations.index', PermissionEnum::DMSView->value],
            [797400, 'Manage Registries', 797000, 'registries.index', PermissionEnum::DMSView->value],
            [797500, 'Linked-to Types', 797000, 'linked-to-types.index', PermissionEnum::DMSView->value],
            [797600, 'Document Types', 797000, 'document-types.index', PermissionEnum::DMSView->value],
            [798000, 'Trash', 700000, 'document-trashed.index', PermissionEnum::DMSView->value],
            [799000, 'Reports', 700000, 'dms-reports.index', PermissionEnum::DMSView->value],
            [1000000, 'User Management', null, null, null],
            [1001000, 'Employees', 1000000, null, PermissionEnum::EmployeesView->value],
            [1001100, 'New Employee', 1001000, 'employees.create', PermissionEnum::EmployeesCreate->value],
            [1001200, 'Employees List', 1001000, 'employees.index', PermissionEnum::EmployeesView->value],
            [1002000, 'Departments', 1000000, 'departments.index', PermissionEnum::Departments->value],
            [9800000, 'Settings', null, null, null],
            [9800100, 'Users', 9800000, 'users.index', PermissionEnum::Users->value],
            [9800200, 'Roles', 9800000, 'roles.index', PermissionEnum::DMSRegistryManage->value],
            [9800300, 'Branches', 9800000, 'branches.index', PermissionEnum::BranchView->value],
            [98004000, 'Code Details', 9800000, 'settings.lists', PermissionEnum::ListsView->value],
            [98004500, 'User Sessions', 9800000, 'settings.user-sessions.index', PermissionEnum::UsersSessions->value],
            [98005500, 'Organization Profile', 9800000, 'settings.organization-profile.index', PermissionEnum::OrganizationProfile->value],
            [98006000, 'Global Workflow', 9800000, null, null],
            [98006100, 'Workflow Setup', 98006000, 'settings.workflows.index', PermissionEnum::WorkflowView->value],
            [98006200, 'Workflow Limits', 98006000, 'settings.workflow_limits', PermissionEnum::WorkflowView->value],
            [9800800, 'System Backup', 9800000, 'settings.system-backups.index', PermissionEnum::Admin->value],
            [9800900, 'Module Settings', 9800000, 'settings.modules.index', PermissionEnum::Admin->value],
        ];

        foreach ($modules as [$id, $name, $parent, $route, $permission]) {
            DB::table('t_Modules')->updateOrInsert(['ModuleID' => $id], [
                'Name' => $name,
                'ParentID' => $parent,
                'Route' => $route,
                'RequiredPermission' => $permission,
                'IsActive' => 1,
                'CreatedOn' => now(),
                'CreatedBy' => 1,
                'ModifiedOn' => now(),
                'ModifiedBy' => 1,
            ]);
        }
    }
}

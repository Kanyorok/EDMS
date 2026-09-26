<?php

use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Core\UserNotificationController;
use App\Http\Controllers\Settings\WorflowLimitsController;
use App\Http\Controllers\Settings\WorkFlowController;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}', function (string $locale) {
    if (!in_array($locale, ['en', 'fr'])) {
        abort(400);
    }
    session(['locale' => $locale]);
    return redirect()->back(302, [], '/');
})->name('lang.switch');

// Added for debugging authentication in production
// Added for debugging authentication in production
Route::get('/debug/auth', function (Illuminate\Http\Request $request) {
    $sessionId = session()->getId();
    $tableName = config('session.table', 't_SYSSessions');

    $sessionEntry = \Illuminate\Support\Facades\DB::table($tableName)
        ->where('id', $sessionId)
        ->first();

    return response()->json([
        'auth_check' => auth()->check(),
        'user_id' => auth()->id(),
        'session_id' => $sessionId,
        'session_table' => $tableName,
        'db_session_found' => (bool) $sessionEntry,
        'db_user_id' => $sessionEntry ? $sessionEntry->user_id : null,
        'db_last_activity' => $sessionEntry ? $sessionEntry->last_activity : null,
        'session_config' => [
            'driver' => config('session.driver'),
            'cookie' => config('session.cookie'),
            'domain' => config('session.domain'),
            'secure' => config('session.secure'),
        ],
        'cookies_received' => [
            'value' => $request->cookie(config('session.cookie')),
            'matches_current' => $request->cookie(config('session.cookie')) === $sessionId,
        ],
    ]);
});

require __DIR__ . '/auth.php';

Route::middleware(['web', 'auth', 'active-module-route'])->namespace('App\Http\Controllers')->group(function () {
    Route::get('notifications', [UserNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [UserNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [UserNotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/clear', [UserNotificationController::class, 'clear'])->name('notifications.clear');

    require __DIR__ . '/dms.php';
    require __DIR__ . '/hrms.php';

    Route::namespace('Settings')->prefix('settings')->group(function () {
        Route::get('lists', 'SettingsController@lists')->name('settings.lists');
        Route::get('users-roles', 'SettingsController@users')->name('settings.users');
        Route::get('workflows', [WorkFlowController::class, 'index'])->name('settings.workflows.index');
        Route::post('workflows/create', [WorkFlowController::class, 'store'])->name('settings.workflows.store');
        Route::put('workflows/{id}', [WorkFlowController::class, 'update'])->name('settings.workflows.update');
        // Delete workflow: use proper HTTP verb. Remove old GET delete route.
        Route::delete('workflows/{id}', [WorkFlowController::class, 'destroy'])->name('settings.workflows.destroy');
        // Optional fallback if DELETE is blocked by infra
        Route::post('workflows/delete/{id}', [WorkFlowController::class, 'destroy'])->name('settings.workflows.delete.post');
        Route::get('workflows/{id}', [WorkFlowController::class, 'show'])->name('settings.workflows.show');
        Route::post('workflow-stages', 'WorkflowStagesController@store')->name('settings.workflow_stages.store');
        Route::put('workflow-stages/{id}', 'WorkflowStagesController@update')->name('settings.workflow_stages.update');
        Route::put('workflow-stages/{id}/roles', 'WorkflowStagesController@syncRoles')->name('settings.workflow_stages.roles.sync');
        Route::patch('workflow-stages/{id}/disable', 'WorkflowStagesController@disable')->name('settings.workflow_stages.disable');
        Route::patch('workflow-stages/{id}/enable', 'WorkflowStagesController@enable')->name('settings.workflow_stages.enable');

        Route::get('/workflows/{id}/state', [WorkFlowController::class, 'getState'])->name('settings.workflows.state');



        //         // Add POST alternative for delete to handle form submission

        // Workflow Limits Routes
        Route::get('workflow-limits', [WorflowLimitsController::class, 'index'])->name('settings.workflow_limits');
        Route::post('workflow-limits', [WorflowLimitsController::class, 'store'])->name('settings.workflow_limits.store');
        Route::delete('workflow-limits/{id}', [WorflowLimitsController::class, 'destroy'])->name('settings.workflow_limits.destroy');

        Route::get('organization-profile', 'OrganizationProfileController@index')->name('settings.organization-profile.index');
        Route::post('organization-profile', 'OrganizationProfileController@update')->name('settings.organization-profile.update');


        Route::get('system-backups', 'SystemBackupController@index')->name('settings.system-backups.index');
        Route::post('system-backups', 'SystemBackupController@update')->name('settings.system-backups.update');
        Route::post('system-backups/run-now', 'SystemBackupController@runNow')->name('settings.system-backups.run-now');
        Route::get('modules', 'ModuleManagementController@index')->name('settings.modules.index');
        Route::post('modules/{module}/toggle', 'ModuleManagementController@toggle')->name('settings.modules.toggle');

        Route::get('ui-preferences', 'UiPreferencesController@show')->name('settings.ui-preferences.show');
        Route::post('ui-preferences', 'UiPreferencesController@update')->name('settings.ui-preferences.update');
        Route::namespace('Codes')->group(function () {
            Route::resource('currencies', 'CurrencyController')->only('index');

            Route::post('code-lists-change-order', 'CodeDetailController@order')->name('code-lists.order');
            Route::resource('code-lists', 'CodeDetailController')->parameters(['code-lists' => 'code_detail'])->except(['create', 'show', 'edit']);

            Route::get('locality', 'LocalitySelectController')->name('locality.select2');
            Route::resource('localities', 'LocalityController')->except(['create', 'show', 'edit']);
        });

        // User Sessions (single-session admin)
        Route::get('user-sessions', 'UserSessionController@index')->name('settings.user-sessions.index');
        Route::post('user-sessions/revoke/{id}', 'UserSessionController@revoke')->name('settings.user-sessions.revoke');
        Route::post('user-sessions/revoke-others/{userId}', 'UserSessionController@revokeOthers')->name('settings.user-sessions.revoke-others');


        Route::resource('teams/{team}/team-users', 'TeamUserController')->parameters(['team-users' => 'user'])->except(['create', 'edit']);
        Route::resource('teams', 'TeamController')->except(['create', 'edit']);


        Route::resource('branches', 'CrmBranchController')->parameters(['branches' => 'crm_branch'])->except(['edit', 'create', 'show']);
        Route::get('branches-sync', 'CrmBranchController@sync')->name('branches.sync');
        Route::post('branches-sync', 'CrmBranchController@startSync')->name('branches.sync.start');
        Route::post('branches-sync/cancel/{syncRunId}', 'CrmBranchController@cancelSync')->name('branches.sync.cancel');
        Route::get('branches-sync/progress/{syncRunId}', 'CrmBranchController@getSyncProgress')->name('branches.sync.progress');
        Route::get('branches-sync/search', 'CrmBranchController@searchSyncedBranches')->name('branches.sync.search');

        Route::resource('roles', 'RoleController'); // remove ->except(['show'])
        Route::post('roles/seed-permissions', 'RoleController@seedPermissions')->name('roles.seedPermissions');
        Route::get('workflow/stage/{stageId}/approvers', [\App\Http\Controllers\Settings\WorkFlowController::class, 'getApprovers'])->name('workflow.stage.approvers');
        Route::get('roles/{id}/ajax', 'RoleController@showAjax')->name('roles.showAjax');


        Route::namespace('Users')->group(function () {
            Route::post('user_roles/branch', 'UserRoleController@storeBranch')->name('user_roles.store_branch');
            // Delete a branch role assignment (under the settings prefix)
            Route::delete('users/branch-role/{modelRole}', 'UserRoleController@destroy')
                ->whereNumber('modelRole')
                ->name('user_roles.delete_branch');
            // Fallback endpoints to operate on ModelRole records identified by composite keys
            // Accept PATCH/DELETE as well so method-overridden forms target these endpoints instead of the {modelRole} parameter route
            Route::post('users/branch-role/delete-by-keys', 'UserRoleController@destroyByKeys')
                ->name('user_roles.delete_branch_by_keys');
            Route::delete('users/branch-role/delete-by-keys', 'UserRoleController@destroyByKeys');

            Route::post('users/branch-role/update-by-keys', 'UserRoleController@updateByKeys')
                ->name('user_roles.update_branch_by_keys');
            Route::patch('users/branch-role/update-by-keys', 'UserRoleController@updateByKeys');

            // Update an existing branch role assignment (route-model binding)
            Route::patch('users/branch-role/{modelRole}', 'UserRoleController@update')
                ->whereNumber('modelRole')
                ->name('user_roles.update_branch');
            Route::prefix('users/{user}')->group(function () {
                Route::resource('user_roles', 'UserRoleController')->only(['index', 'store']);
                Route::get('activities', 'UserActivitiesController')->name('user.activities');
                Route::post('authentication', 'UserActionsController@sendResetLink')->name('users.password.reset');
            });

            Route::get('fetch-users', 'UserSelectController')->name('users.select2');

            Route::resource('users', 'UserController');
        });
    });
    Route::get('help', 'HelpController')->name('help');

});
// // Admin Licensing endpoints (should be accessible post-auth; license check happens after upload)
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/license', [LicenseController::class, 'index'])
        ->name('admin.license.index');
    Route::post('/admin/license', [LicenseController::class, 'store'])
        ->name('admin.license.store');
});

Route::get('/get-totp', function () {
    $secret = session('2fa_pending_secret');

    return ['otp' => app('pragmarx.google2fa')->getCurrentOtp($secret)];
});

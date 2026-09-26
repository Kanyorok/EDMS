<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Core\ModulesEnum;
use App\Enums\Core\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Core\Module;
use App\Services\Core\ModuleService;
use App\Services\Licensing\LicensingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ModuleManagementController extends Controller
{
    private const STANDALONE_ROOTS = ['Document Management', 'user Management', 'Settings', 'Dashboard', 'Home'];
    /**
     * Top-level modules that are essential to reaching/administering this screen and can
     * never be disabled from the UI.
     */
    private const PROTECTED_MODULE_IDS = [
        9800000, // Settings
        9900000, // MyAccount
    ];

    public function __construct(private readonly LicensingService $licensingService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeModuleAccess($request);

        $license = $this->licensingService->current();
        $licensed = $license->isValid() ? $license->allowedModules : [];

        $allModules = Module::query()->orderBy('ModuleID')->get();

        $modules = $allModules
            ->whereNull('ParentID')
            ->whereNotIn('ModuleID', self::PROTECTED_MODULE_IDS)
            ->filter(fn (Module $module) => in_array($module->Name, self::STANDALONE_ROOTS, true))
            ->whereIn('ModuleID', $licensed)
            ->sortBy('ModuleID')
            ->map(fn (Module $module) => $this->buildModuleTree($module, $allModules))
            ->values();

        return view('settings.modules.index', [
            'modules' => $modules,
            'licenseValid' => $license->isValid(),
        ]);
    }

    /**
     * Recursively shape a module and its descendants (any depth) into the array the
     * "Disable Modules" tree view renders, so a whole module or an individual sub-item
     * under it can be toggled off independently.
     */
    private function buildModuleTree(Module $module, Collection $allModules): array
    {
        $enum = ModulesEnum::tryFrom((int) $module->ModuleID);

        $children = $allModules
            ->where('ParentID', $module->ModuleID)
            ->sortBy('ModuleID')
            ->map(fn (Module $child) => $this->buildModuleTree($child, $allModules))
            ->values();

        return [
            'id' => $module->ModuleID,
            'name' => $enum?->description() ?? $module->Name,
            'icon' => $module->Icon,
            'is_active' => $module->IsActive !== false,
            'children' => $children,
        ];
    }

    public function toggle(Request $request, Module $module): RedirectResponse
    {
        $this->authorizeModuleAccess($request);

        if (in_array((int) $module->ModuleID, self::PROTECTED_MODULE_IDS, true)) {
            abort(403, 'This module cannot be disabled.');
        }

        $root = $module;
        while ($root->ParentID !== null) {
            $root = Module::query()->findOrFail($root->ParentID);
        }
        abort_unless(in_array($root->Name, self::STANDALONE_ROOTS, true), 404);

        // Licensing is granted per top-level module only; a sub-module's own ID never
        // appears in the license, so walk up to its top-level ancestor to check it.
        $license = $this->licensingService->current();
        $licensed = $license->isValid() ? $license->allowedModules : [];
        if (! in_array($this->resolveTopLevelModuleId($module), $licensed, true)) {
            abort(403, 'This module is not included in your current license and cannot be managed here.');
        }

        $module->IsActive = ! ($module->IsActive !== false);
        $module->save();

        ModuleService::clearNavbarCache($request->user());

        return redirect()
            ->route('settings.modules.index')
            ->with('success', $module->Name . ' has been ' . ($module->IsActive ? 'enabled' : 'disabled') . '.');
    }

    private function resolveTopLevelModuleId(Module $module): int
    {
        $current = $module;
        $visited = [];
        while ($current->ParentID !== null) {
            $parentId = (int) $current->ParentID;
            if (isset($visited[$parentId])) {
                break;
            }
            $visited[$parentId] = true;
            $parent = Module::query()->where('ModuleID', $parentId)->first();
            if (! $parent) {
                break;
            }
            $current = $parent;
        }

        return (int) $current->ModuleID;
    }

    private function authorizeModuleAccess(Request $request): void
    {
        abort_unless(
            $request->user()?->hasPermissionTo(PermissionEnum::Admin->value)
                || $request->user()?->hasRole('admin'),
            403,
            'Only system administrators can manage modules.'
        );
    }
}

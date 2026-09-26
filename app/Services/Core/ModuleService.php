<?php

namespace App\Services\Core;

use App\Models\Auth\User;
use App\Models\Core\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ModuleService
{
    public static function generateNavbar(?User $user = null): string
    {
        return Cache::remember(self::getNavbarCacheKey($user), 3600, static function (): string {
            $menu = '';
            foreach (self::navbar() as $module) {
                $menu .= self::renderItem($module);
            }

            return $menu;
        });
    }

    public static function getNavbarCacheKey(?User $user = null): string
    {
        $user ??= Auth::user();
        $version = self::modulesVersion();
        if (! $user instanceof User) {
            return 'guest-navbar-' . app()->getLocale() . '-' . $version;
        }

        return implode('-', [
            $user->UserID,
            session('LoginBranchId', 'no-branch'),
            app()->getLocale(),
            'standalone-dms-navbar',
            $version,
        ]);
    }

    public static function clearNavbarCache(?User $user = null): bool
    {
        return Cache::forget(self::getNavbarCacheKey($user));
    }

    public static function isRouteModuleActive(?string $routeName): bool
    {
        if (! is_string($routeName) || $routeName === '') {
            return true;
        }

        $modules = Module::all()->keyBy('ModuleID');
        $module = $modules->firstWhere('Route', $routeName);
        $visited = [];
        while ($module instanceof Module) {
            if ($module->IsActive === false) {
                return false;
            }
            $parentId = $module->ParentID ? (int) $module->ParentID : null;
            if ($parentId === null || isset($visited[$parentId])) {
                break;
            }
            $visited[$parentId] = true;
            $module = $modules->get($parentId);
        }

        return true;
    }

    public static function renderModuleIcon(?string $icon, string $moduleName = ''): string
    {
        $icon = trim((string) $icon);
        if ($icon !== '' && str_contains($icon, '<')) {
            if (preg_match('/data-feather=["\']([a-z0-9-]+)["\']/i', $icon, $match)) {
                return '<i data-feather="' . e($match[1]) . '" aria-hidden="true"></i>';
            }
            if (preg_match('/class=["\']([^"\']+)["\']/i', $icon, $match)) {
                $icon = $match[1];
            }
        }
        if ($icon !== '' && preg_match('/^[a-z0-9 _-]+$/i', $icon)) {
            return '<i class="' . e(preg_replace('/\bfa-circle-o\b/', 'fa-circle', $icon)) . '" aria-hidden="true"></i>';
        }

        $name = Str::lower(trim($moduleName));
        $feather = match ($name) {
            'document management' => 'folder',
            'user management' => 'users',
            'settings' => 'settings',
            'dashboard', 'home' => 'grid',
            default => 'box',
        };

        return '<i data-feather="' . $feather . '" aria-hidden="true"></i>';
    }

    private static function modulesVersion(): string
    {
        try {
            return (string) (Module::withTrashed()->max('ModifiedOn') ?? 'none');
        } catch (\Throwable) {
            return 'none';
        }
    }

    private static function navbar(): Collection
    {
        $all = Module::all();
        $allowedRoots = ['Document Management', 'User Management', 'Settings', 'Dashboard', 'Home'];
        $roots = $all->whereNull('ParentID')
            ->filter(fn (Module $module): bool => in_array($module->Name, $allowedRoots, true))
            ->filter(fn (Module $module): bool => $module->IsActive !== false)
            ->sortBy('ModuleID');
        $permissions = self::permissions(Auth::user());

        return $roots->map(fn (Module $module): ?array => self::buildItem($module, $all, $permissions))
            ->filter()
            ->values();
    }

    private static function buildItem(Module $module, Collection $all, array $permissions): ?array
    {
        if ($module->IsActive === false || ! self::hasRequiredPermission($module, $permissions)) {
            return null;
        }

        $children = $all->where('ParentID', $module->ModuleID)
            ->sortBy('ModuleID')
            ->map(fn (Module $child): ?array => self::buildItem($child, $all, $permissions))
            ->filter()
            ->values()
            ->all();
        $routeName = is_string($module->Route) ? $module->Route : null;
        $routeUrl = self::routeUrl($routeName);

        if ($routeUrl === 'javascript:void(0)' && $children === []) {
            return null;
        }

        return [
            'id' => $module->ModuleID,
            'name' => $module->Name,
            'icon' => $module->Icon,
            'route' => $routeUrl,
            'route_name' => $routeName,
            'description' => $module->Description ?? '',
            'children' => $children,
        ];
    }

    private static function hasRequiredPermission(Module $module, array $permissions): bool
    {
        $required = trim((string) $module->RequiredPermission);
        if ($required === '') {
            return true;
        }

        foreach (explode('|', $required) as $permission) {
            if (isset($permissions[Str::lower(trim($permission))])) {
                return true;
            }
        }

        return false;
    }

    private static function permissions(mixed $user): array
    {
        if (! $user instanceof User) {
            return [];
        }
        try {
            return $user->getPermissionsViaRoles()->pluck('name')
                ->mapWithKeys(fn (string $name): array => [Str::lower($name) => true])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private static function routeUrl(?string $routeName): string
    {
        if (! $routeName || ! Route::has($routeName)) {
            return 'javascript:void(0)';
        }
        $route = Route::getRoutes()->getByName($routeName);
        if ($route && preg_match('/\{[^}?]+\}/', $route->uri())) {
            return 'javascript:void(0)';
        }

        return route($routeName);
    }

    private static function renderItem(array $item): string
    {
        $hasChildren = $item['children'] !== [];
        $classes = 'pc-item' . ($hasChildren ? ' pc-hasmenu' : '');
        $html = '<li class="' . $classes . '" data-item-id="' . e((string) $item['id']) . '">';
        $html .= '<a href="' . e($item['route']) . '" class="pc-link" data-ajax="1">';
        $html .= '<span class="pc-micon">' . self::renderModuleIcon($item['icon'], $item['name']) . '</span>';
        $html .= '<span class="pc-mtext">' . e($item['name']) . '</span>';
        if ($hasChildren) {
            $html .= '<span class="pc-arrow"><i data-feather="chevron-right"></i></span>';
        }
        $html .= '</a>';
        if ($hasChildren) {
            $html .= '<ul class="pc-submenu">';
            foreach ($item['children'] as $child) {
                $html .= self::renderItem($child);
            }
            $html .= '</ul>';
        }

        return $html . '</li>';
    }
}

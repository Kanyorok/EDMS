<?php

namespace App\Http\Middleware;

use App\Services\Core\ModuleService;
use Closure;
use Illuminate\Http\Request;

/**
 * Global companion to RequireModule: while `module:<id>` gates whole top-level
 * route groups, this blocks direct access to an individual sub-module's page
 * once an admin disables it from Settings > Disable Modules — without needing
 * `module:<id>` middleware wired onto every one of the ~570 sub-module routes.
 * It's a no-op for any route that isn't the registered entry point (t_Modules.Route)
 * of some module row.
 */
class RequireActiveModuleRoute
{
    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route()?->getName();

        if (! ModuleService::isRouteModuleActive($routeName)) {
            abort(403, 'This feature has been disabled by your administrator.');
        }

        return $next($request);
    }
}

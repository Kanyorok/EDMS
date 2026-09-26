<?php

namespace App\Http\Middleware;

use App\Models\Core\Module;
use App\Services\Licensing\LicensingService;
use Closure;
use Illuminate\Http\Request;

class RequireModule
{
    public function handle(Request $request, Closure $next, string $moduleId)
    {
        $lic = app(LicensingService::class)->current();
        if (! $lic->isValid()) {
            abort(402, 'License invalid or expired.');
        }
        $id = (int) $moduleId;

        // Walk the module up to its top-level ancestor once; used for both the
        // admin-disable check and the license inheritance check below.
        $chain = [];
        try {
            $visited = [];
            $mod = Module::query()->where('ModuleID', $id)->first();
            while ($mod) {
                $chain[] = $mod;
                $pid = $mod->ParentID ? (int) $mod->ParentID : null;
                if (! $pid || isset($visited[$pid])) {
                    break;
                }
                $visited[$pid] = true;
                $mod = Module::query()->where('ModuleID', $pid)->first();
            }
        } catch (\Throwable $e) {
            // fallthrough: if resolution fails, rely purely on the license check below
        }

        // Admin-disable check: block if the module itself or any ancestor (i.e. its
        // top-level module) has been turned off from Settings > Disable Modules.
        foreach ($chain as $mod) {
            if ($mod->IsActive === false) {
                abort(403, 'Module disabled.');
            }
        }

        // Fast path: direct allow
        if ($lic->allows($id)) {
            return $next($request);
        }

        // Parent inheritance: if any ancestor ModuleID is licensed, allow
        foreach ($chain as $mod) {
            if (in_array((int) $mod->ModuleID, $lic->allowedModules, true)) {
                return $next($request);
            }
        }

        abort(403, 'Module not licensed.');

        return $next($request);
    }
}

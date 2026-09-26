<?php

namespace App\Http\Middleware;

use App\Http\Controllers\DMS\API\ExternalSourceTokenController;
use App\Models\Auth\User;
use App\Models\DMS\SourceSystem;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a call to the external DMS API by the token the system got from
 * ExternalSourceTokenController. It must be a token issued for exactly this source
 * system, carry the external-DMS ability, and belong to the system's service user.
 *
 * An unknown or inactive system is answered as 401, same as a bad token, so that an
 * unauthenticated caller cannot tell which system codes exist. The resolved system is
 * put on the request for the middleware and controllers after it.
 */
class AuthenticateExternalSource
{
    public function handle(Request $request, Closure $next): Response
    {
        $system = SourceSystem::query()
            ->where('Code', (string) $request->route('system'))
            ->where('IsActive', true)
            ->first();

        $user = Auth::guard('sanctum')->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $system instanceof SourceSystem
            || $token === null
            || $token->name !== ExternalSourceTokenController::tokenName($system)
            || ! $user->tokenCan(ExternalSourceTokenController::ABILITY)
            || $user->Id !== $system->ServiceUserId
            || ! $user->isActive()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('sourceSystem', $system);

        return $next($request);
    }
}

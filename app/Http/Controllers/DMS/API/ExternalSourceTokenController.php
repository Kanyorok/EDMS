<?php

namespace App\Http\Controllers\DMS\API;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Models\DMS\SourceSystem;
use App\Services\Auth\UserPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Login for an external system's integration account: the one place its credentials are
 * sent. It returns a short-lived token that is good only for this source system's external
 * DMS API, which the caller then reuses until it expires.
 *
 * The account must be the source system's own service user - the same ERP identity the
 * gateway already authenticates as - so a valid password for some other user gets nothing.
 * Every failure reads the same, so the response does not reveal which part was wrong.
 */
class ExternalSourceTokenController extends Controller
{
    public const string ABILITY = 'dms:external';
    public const int TOKEN_MINUTES = 60;

    public static function tokenName(SourceSystem $system): string
    {
        return 'dms-external:' . $system->Code;
    }

    public function store(Request $request, string $system): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        $source = SourceSystem::query()->where('Code', $system)->where('IsActive', true)->first();
        $user = User::query()->where('UserID', strtoupper(trim($data['userId'])))->first();

        $valid = $source instanceof SourceSystem
            && $user instanceof User
            && $user->Id === $source->ServiceUserId
            && $user->isActive()
            // A second factor cannot be answered by another system, so such an account cannot be used here.
            && ! $user->hasTwoFactorEnabled()
            && UserPasswordService::verify($user, $data['password']);

        if (! $valid) {
            Log::warning('external DMS token refused', ['system' => $system, 'userId' => $data['userId'], 'ip' => $request->ip()]);

            return response()->json(['message' => 'Authentication failed. Invalid credentials.'], 401);
        }

        $expires = now()->addMinutes(self::TOKEN_MINUTES);
        $token = $user->createToken(self::tokenName($source), [self::ABILITY], $expires);

        activity()->causedBy($user)->performedOn($source)->event('token')->log("Issued external DMS token to {$source->Code} (until {$expires->toDateTimeString()})");

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expires->toIso8601String(),
        ]]);
    }
}

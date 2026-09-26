<?php

use App\Exceptions\ErroredException;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TransformApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Aliases for route middleware
        $middleware->alias([
            'ajax'               => \App\Http\Middleware\AjaxCheckMiddleware::class,
            'license'            => \App\Http\Middleware\RequireLicense::class,
            'module'             => \App\Http\Middleware\RequireModule::class,
            'active-module-route' => \App\Http\Middleware\RequireActiveModuleRoute::class,
            'canAction'          => \App\Http\Middleware\CanAction::class,
            'abilities'          => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'            => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'ensure_2fa'         => \App\Http\Middleware\EnsureTwoFactorComplete::class,
            'pending_2fa'        => \App\Http\Middleware\EnsurePendingTwoFactor::class,
            'role'               => \App\Http\Middleware\RoleMiddleware::class,
            'permission'         => \App\Http\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \App\Http\Middleware\RoleOrPermissionMiddleware::class,
            'verified.user'      => \App\Http\Middleware\VerifiedUser::class,
        ]);

        $middleware->appendToGroup('web', SetLocale::class);

        // Trust the reverse proxy in front of this app (if any) so getScheme()/
        // getHost()/isSecure() reflect the public request instead of the raw
        // connection between the proxy and this process. Inert unless
        // TRUSTED_PROXIES is set - set it to the proxy's IP(s) in production
        // (comma-separated), or "*" only if the app is never reachable except
        // through that proxy.
        $trustedProxies = array_filter(explode(',', (string) env('TRUSTED_PROXIES', '')));
        if ($trustedProxies) {
            $middleware->trustProxies(
                at: in_array('*', $trustedProxies, true) ? '*' : $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
            );
        }

        $middleware->trimStrings(except: [
            'reports/*',
        ]);

        // Ensure CORS middleware is prepended to API group
        $middleware->prependToGroup('api', \Illuminate\Http\Middleware\HandleCors::class);

        // Security headers on all API responses
        $middleware->appendToGroup('api', \App\Http\Middleware\SecureHeaders::class);

        // Transform keys of requests that are not GET to snake_case
        // and keys of successful JSON responses to camelCase
        $middleware->appendToGroup('api', [
            //     TransformApiRequest::class,
            TransformApiResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Safety net: business-rule failures (e.g. maker-checker violations,
        // workflow stored-procedure errors) are expected, user-facing errors —
        // never a server bug — so an ErroredException that reaches here
        // uncaught must never render as a 500. Controllers should still catch
        // it themselves for a redirect to a specific route/tab, but any spot
        // that misses that gets a safe fallback instead of a crash page.
        $exceptions->render(function (ErroredException $e, Request $request) {
            if ($request->expectsJson() || $request->ajax()) {
                return $e->toJson();
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        });

        $exceptions->respond(function ($response) {
            if (method_exists($response, 'header')) {
                $allowedOrigins = array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')));
                $origin = request()->header('Origin');
                if ($origin && in_array($origin, $allowedOrigins, true)) {
                    $response->header('Access-Control-Allow-Origin', $origin);
                    $response->header('Access-Control-Allow-Credentials', 'true');
                }
            }
            return $response;
        });
    })->withEvents(discover: [
        __DIR__ . '/../app/Listeners',
    ])->create();

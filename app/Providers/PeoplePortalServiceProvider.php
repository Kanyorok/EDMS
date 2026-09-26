<?php

namespace App\Providers;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PeoplePortalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerJobLimiters('ess');
        $this->registerJobLimiters('applicant');
        $this->registerApplicantVerificationLimiter();
    }

    private function registerApplicantVerificationLimiter(): void
    {
        RateLimiter::for('applicant-verification-resend', function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email', '')));
            $emailIdentity = hash('sha256', $email);
            $network = $this->networkIdentity($request);
            $requestsPerMinute = $this->rateLimit('requests_per_minute', 100);
            $emailRequestsPerHour = $this->rateLimit('email_requests_per_hour', 6000);
            $networkRequestsPerHour = $this->rateLimit('network_requests_per_hour', 20000);

            return [
                Limit::perMinute($requestsPerMinute)
                    ->by("email-network:{$emailIdentity}:{$network}")
                    ->response($this->rateLimitedResponse(
                        'Several verification emails were requested. Please wait a moment before trying again.',
                    )),
                Limit::perHour($emailRequestsPerHour)
                    ->by("email:{$emailIdentity}")
                    ->response($this->rateLimitedResponse(
                        'This email has reached the verification resend limit. Please try again later.',
                    )),
                Limit::perHour($networkRequestsPerHour)
                    ->by("network:{$network}")
                    ->response($this->rateLimitedResponse(
                        'This network has reached the verification resend limit. Please try again later.',
                    )),
            ];
        });
    }

    private function registerJobLimiters(string $guard): void
    {
        RateLimiter::for("{$guard}-jobs-read", function (Request $request) use ($guard): array {
            $identity = $this->portalIdentity($request, $guard);
            $network = $this->networkIdentity($request);
            $accountRequestsPerMinute = $this->rateLimit('job_reads_per_minute', 120);
            $networkRequestsPerMinute = $this->rateLimit('job_reads_network_per_minute', 600);

            return [
                Limit::perMinute($accountRequestsPerMinute)
                    ->by("account:{$identity}")
                    ->response($this->rateLimitedResponse('Too many job-listing requests. Please wait before refreshing again.')),
                Limit::perMinute($networkRequestsPerMinute)
                    ->by("network:{$network}")
                    ->response($this->rateLimitedResponse('This network is sending too many job-listing requests. Please wait and try again.')),
            ];
        });

        RateLimiter::for("{$guard}-job-applications", function (Request $request) use ($guard): array {
            $identity = $this->portalIdentity($request, $guard);
            $network = $this->networkIdentity($request);
            $routeOpening = $request->route('opening');
            $opening = is_object($routeOpening) && method_exists($routeOpening, 'getRouteKey')
                ? (string) $routeOpening->getRouteKey()
                : (string) ($routeOpening ?? 'unknown');
            $requestsPerMinute = $this->rateLimit('requests_per_minute', 100);
            $accountRequestsPerHour = $this->rateLimit('account_requests_per_hour', 6000);
            $networkRequestsPerHour = $this->rateLimit('network_requests_per_hour', 20000);

            return [
                Limit::perMinute($requestsPerMinute)
                    ->by("opening:{$identity}:{$opening}")
                    ->response($this->rateLimitedResponse('Too many application attempts for this position. Please wait before trying again.')),
                Limit::perHour($accountRequestsPerHour)
                    ->by("account:{$identity}")
                    ->response($this->rateLimitedResponse('Your account has reached the hourly application-attempt limit. Please try again later.')),
                Limit::perHour($networkRequestsPerHour)
                    ->by("network:{$network}")
                    ->response($this->rateLimitedResponse('This network has reached the hourly application-attempt limit. Please try again later.')),
            ];
        });
    }

    private function portalIdentity(Request $request, string $guard): string
    {
        $accountId = Auth::guard($guard)->id();

        return $accountId !== null
            ? "{$guard}:{$accountId}"
            : "{$guard}:anonymous:{$this->networkIdentity($request)}";
    }

    private function networkIdentity(Request $request): string
    {
        return hash('sha256', (string) $request->ip());
    }

    private function rateLimit(string $key, int $default): int
    {
        return max(1, (int) config("ess.rate_limits.{$key}", $default));
    }

    private function rateLimitedResponse(string $message): Closure
    {
        return static function (Request $_request, array $headers) use ($message): JsonResponse {
            $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));

            return response()->json([
                'message' => $message,
                'code' => 'rate_limit_exceeded',
                'retry_after' => $retryAfter,
            ], 429, $headers);
        };
    }
}

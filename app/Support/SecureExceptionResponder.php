<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class SecureExceptionResponder
{
    /**
     * Record an exception while returning an opaque reference safe to show to a user.
     */
    public static function capture(Throwable $exception, string $message, ?Request $request = null): string
    {
        $reference = (string) Str::uuid();

        report($exception);

        logger()->error($message, array_filter([
            'reference' => $reference,
            'user_id' => $request?->user()?->getAuthIdentifier(),
            'route' => $request?->route()?->getName(),
            'exception' => $exception::class,
        ], static fn (mixed $value): bool => $value !== null));

        return $reference;
    }
}

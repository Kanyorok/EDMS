<?php

namespace App\Services\Auth;

use App\Models\Auth\User;

final class UserPasswordService
{
    public static function verify(User $user, #[\SensitiveParameter] string $password): bool
    {
        return hash_equals($user->Password, self::hash($user, $password));
    }

    public static function hash(User $user, #[\SensitiveParameter] string $password): string
    {
        return base64_encode(hash('sha256', $user->UserID . $password, true));
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by LoginRequest::authenticate() when the user's credentials are valid
 * but they have two-factor authentication enabled. The controller catches this
 * and redirects the user to the TOTP challenge page without completing login.
 */
class TwoFactorRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Two-factor authentication required.');
    }
}

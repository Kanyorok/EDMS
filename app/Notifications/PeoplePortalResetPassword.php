<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PeoplePortalResetPassword extends ResetPassword
{
    public function __construct(
        string $token,
        private readonly string $accountType,
    ) {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $baseUrl = rtrim((string) config('ess.portal_url'), '/');
        $prefix = $this->accountType === 'applicant' ? '/applicant' : '';
        $url = $baseUrl.$prefix.'/reset-password/'.rawurlencode($this->token)
            .'?email='.rawurlencode((string) $notifiable->getEmailForPasswordReset());

        return (new MailMessage())
            ->subject('Reset your MSC portal password')
            ->line('We received a request to reset your password.')
            ->action('Reset password', $url)
            ->line('This link expires in 30 minutes. If you did not request it, no action is needed.');
    }
}

<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyApplicantEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'api.v1.applicant.auth.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Verify your MSC applicant account')
            ->greeting('Welcome to the MSC People Portal')
            ->line('Verify your email address to activate your applicant account.')
            ->action('Verify email', $this->verificationUrl($notifiable))
            ->line('This link expires in 60 minutes.');
    }
}

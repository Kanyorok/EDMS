<?php

namespace App\Services\Communication;

use App\Models\Auth\User;
use App\Models\Communication\{NotificationDispatchLog, UserNotification};
use App\Services\HRM\UserService;

class InternalNotifier
{
    public function notify(User $user, string $type, string $key, string $title, string $body): void
    {
        $actorId = auth()->id();

        $this->sendInApp($user, $type, $key, $title, $body, $actorId);
        $this->sendEmail($user, $type, $key, $title, $body, $actorId);
        $this->sendSms($user, $type, $key, $title, $body, $actorId);
    }

    private function sent(string $channel, string $key): bool
    {
        return NotificationDispatchLog::where('Channel', $channel)->where('Key', $key)->exists();
    }

    private function log(string $channel, string $type, string $key, ?int $actorId): void
    {
        NotificationDispatchLog::create([
            'Channel' => $channel,
            'Type' => $type,
            'Key' => $key,
            'CreatedBy' => $actorId,
            'CreatedOn' => now(),
        ]);
    }

    private function sendInApp(User $user, string $type, string $key, string $title, string $body, ?int $actorId): void
    {
        $dispatchKey = "inapp:{$key}";
        if ($this->sent('inapp', $dispatchKey)) {
            return;
        }

        UserNotification::create([
            'UserID' => $user->Id,
            'Type' => $type,
            'Title' => $title,
            'Body' => $body,
            'Source' => $type,
            'SourceID' => $key,
            'CreatedOn' => now(),
        ]);

        $this->log('inapp', $type, $dispatchKey, $actorId);
    }

    private function sendEmail(User $user, string $type, string $key, string $title, string $body, ?int $actorId): void
    {
        $dispatchKey = "email:{$key}";
        if ($this->sent('email', $dispatchKey)) {
            return;
        }

        (new UserService($user))->sendEmail($title, '<div><p>' . e($body) . '</p></div>');
        $this->log('email', $type, $dispatchKey, $actorId);
    }

    private function sendSms(User $user, string $type, string $key, string $title, string $body, ?int $actorId): void
    {
        $dispatchKey = "sms:{$key}";
        if ($this->sent('sms', $dispatchKey)) {
            return;
        }

        (new UserService($user))->sendMessage($title . ': ' . $body, \App\Helpers\SystemHelper::user());
        $this->log('sms', $type, $dispatchKey, $actorId);
    }
}

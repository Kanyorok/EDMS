<?php

namespace App\Services;

use App\Enums\Core\EmailChannelEnum;
use App\Enums\EmailPriorityEnum;
use App\Enums\EmailStatusEnum;
use App\Enums\EmailTypeEnum;
use App\Events\EmailSendEvent;
use App\Helpers\StringHelper;
use App\Helpers\SystemHelper;
use App\Mail\DefaultEmail;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Models\Communication\Email;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CRMEmailService
{
    public function __construct(public Email $crmEmail)
    {
    }

    private static function create(
        User $actor,
        string $subject,
        string $body,
        EmailPriorityEnum $priority,
        array $to,
        ?string $Party = null,
        ?string $PartyID = null,
        array $cc = [],
        array $bcc = [],
        Email $replyTo = null,
        EmailChannelEnum $channel = null
    ): CRMEmailService {
        if (! $replyTo instanceof Email && ! Str::contains($subject, ['RE:', config('org.name')])) {
            $subject .= ' - ' . config('org.name');
        }
        $crmEmail = new Email();
        $crmEmail->fill([
            'Type' => EmailTypeEnum::Outgoing->value,
            'Status' => EmailStatusEnum::Draft->value,
            'Priority' => $priority->value,
            'From' => config('org.email'),
            'To' => $to,
            'CC' => $cc,
            'BCC' => $bcc,
            'Subject' => $subject,
            'Body' => $body,
            'Text' => StringHelper::cleanHtml($body),
            'Party' => $Party ?: null,
            'PartyID' => $PartyID ?: null,
            'EmailConversationId' => $replyTo?->EmailConversationId,
            'ReferenceId' => $replyTo?->MailID,
            'Channel' => $channel ?? EmailChannelEnum::SMTP,
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ])->save();

        return new self($crmEmail);
    }

    public static function createTeam(
        Team $team,
        string $subject,
        string $body,
        User $actor,
        array $cc = []
    ): CRMEmailService {
        return self::create(
            $actor,
            $subject,
            $body,
            EmailPriorityEnum::Normal,
            [[$team->Name => $team->Email]],
            Team::getPrimaryKey(),
            $team->TeamID,
            $cc,
            channel: EmailChannelEnum::SMTP
        );
    }

    public static function createUser(User $user, string $subject, string $body, User $actor, array $cc = [], EmailPriorityEnum $priorityEnum = EmailPriorityEnum::Normal, Email $replyTo = null): CRMEmailService
    {
        return self::create($actor, $subject, $body, $priorityEnum, [[$user->Name => $user->Email]], User::getPrimaryKey(), $user->Id, $cc, replyTo: $replyTo);
    }

    public static function createRaw(User $actor, string $subject, string $body, array $to, ?string $Party = null, ?string $PartyID = null, array $cc = [], array $bcc = [], EmailPriorityEnum $priorityEnum = null, Email $replyTo = null): CRMEmailService
    {
        return self::create($actor, $subject, $body, ($priorityEnum) ?? EmailPriorityEnum::Normal, $to, $Party, $PartyID, $cc, $bcc, $replyTo);
    }

    public function setSource(string $Source, string $SourceID): static
    {
        $this->crmEmail->update([
            'Source' => $Source,
            'SourceID' => $SourceID,
        ]);

        return $this;
    }

    public function setReplyTo(?string $email, ?string $name = null): static
    {
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this;
        }

        $extra = $this->crmEmail->Extra;
        $extraData = is_object($extra) ? (array) $extra : (is_array($extra) ? $extra : []);
        $extraData['reply_to'] = [
            'email' => $email,
            'name' => $name ?: $email,
        ];

        $this->crmEmail->update([
            'Extra' => (object) $extraData,
        ]);

        return $this;
    }

    public function send(bool $immediate = false): static
    {
        if ($this->crmEmail->Type->value !== EmailTypeEnum::Outgoing->value) {
            return $this;
        }

        if ($this->crmEmail->Status->value === EmailStatusEnum::Queued->value) {
            return $this->_send();
        }

        if ($this->crmEmail->Status->value === EmailStatusEnum::Draft->value) {
            if (! $immediate && config('queue.default') !== 'sync') {
                $this->crmEmail->update([
                    'Status' => EmailStatusEnum::Sending->value,
                ]);

                event(new EmailSendEvent($this->crmEmail));

                return $this;
            }

            return $this->_send();
        }

        if ($this->crmEmail->Status->value === EmailStatusEnum::Sending->value) {
            if (! $immediate && config('queue.default') !== 'sync') {
                return $this;      //already queued for sending.
            }

            return $this->_send();
        }

        return $this;
    }

    private function _getEmailTo(): string
    {
        $email = is_array($this->crmEmail->To) && count($this->crmEmail->To) > 0 ? $this->crmEmail->To : [];
        return collect($email)->first(default: '');
    }


    protected function _failed(string $reason): static
    {
        $this->crmEmail->update([
            'Dated' => now(),
            'Status' => EmailStatusEnum::Failed->value,
        ]);
        Log::error('Email failed to send: ' . $reason);

        return $this;
    }

    private function _sendSMTP(): ?string
    {
        try {
            $sent = Mail::send(new DefaultEmail($this->crmEmail));

            return $sent?->getMessageId();
        } catch (\Throwable $e) {
            SystemHelper::notifyAdmin('SMTP email failed: ' . $e->getMessage());
            return null;
        }
    }

    protected function _send(): static
    {
        if (config('app.debug')) {
            return $this->_failed('Application in Development mode.');
        }

        $mailID = $this->_sendSMTP();

        if (is_string($mailID)) {
            $this->crmEmail->update([
                'MailID' => $mailID,
                'Dated' => now(),
                'Status' => EmailStatusEnum::Sent->value
            ]);

            return $this;
        }

        return $this->_failed('Unknown error');
    }

}

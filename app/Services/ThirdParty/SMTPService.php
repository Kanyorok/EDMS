<?php

namespace App\Services\ThirdParty;

use App\Enums\Core\IntegrationsEnum;
use App\Enums\EmailEncryptionEnum;
use App\Exceptions\ErroredException;
use App\Mail\DefaultEmail;
use App\Mail\TestMail;
use App\Models\Communication\Email;
use App\Models\Settings\APICredential;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Infobip\Api\EmailApi;
use SensitiveParameter;

class SMTPService
{
    public string $From;
    protected EmailApi $sendApi;
    private array $Config;

    /**
     * @throws ErroredException
     */
    public function __construct()
    {
        $credentials = APICredential::query()
            ->where('Integration', IntegrationsEnum::Email->value)->latest('Id')->first();
        if (!$credentials instanceof APICredential) {
            throw new ErroredException('no email mail configuration');
        }
        $emailConfig = $credentials->Configuration;
        $this->From = $emailConfig?->Outgoing?->username;
        $this->Config = [
            'transport' => 'smtp',
            'host' => $emailConfig?->Outgoing?->host,
            'port' => $emailConfig?->Outgoing?->port,
            'encryption' => $emailConfig?->Outgoing?->encryption,
            'username' => $emailConfig?->Outgoing?->username,
            'password' => $emailConfig?->Outgoing?->password,
        ];
    }

    public static function testConfig(
        string $host,
        int $port,
        EmailEncryptionEnum $encryption,
        string $username,
        #[SensitiveParameter] string $password
    ): bool {
        try {
            $mailer = clone app('mailer');
            $mailer->alwaysFrom($username, config('org.name'));
            $mailer->alwaysTo($username, config('org.name'));
            $mailer->setSymfonyTransport(Mail::createSymfonyTransport([
                'transport' => 'smtp',
                'timeout' => 5,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption->value,
                'username' => $username,
                'password' => $password,
            ]));
            return ($mailer->sendNow(new TestMail()) instanceof SentMessage);
        } catch (\Exception | \RuntimeException) {
        }

        return false;
    }

    public function sendEmail(Email $email): ?string
    {
        $mailable = $this->_send($email);
        return ($mailable instanceof SentMessage) ? $mailable->getMessageId() : null;
    }

    private function _send(Email $email): ?SentMessage
    {
        $email->update([
            'From' => $this->From,
        ]);

        $mailer = clone app('mailer');
        $mailer->alwaysFrom($this->From, config('org.name'));
        // Override email config
        $mailer->setSymfonyTransport(Mail::createSymfonyTransport($this->Config));

        // Send email
        return $mailer->sendNow(new DefaultEmail($email));
    }
}

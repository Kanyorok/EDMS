<?php

namespace App\Services\ThirdParty;

use App\Enums\Core\IntegrationsEnum;
use App\Exceptions\ErroredException;
use App\Models\Auth\User;
use App\Models\Communication\Email;
use App\Models\Settings\APICredential;
use App\Services\DMS\ImageService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Infobip\Api\EmailApi;
use Infobip\ApiException;
use Infobip\Configuration;
use SensitiveParameter;

class InfobipService
{
    protected EmailApi $sendApi;
    public string $email;

    /**
     * @throws ErroredException
     */
    public function __construct()
    {
        $cred = APICredential::query()->where('Integration', IntegrationsEnum::InfoBip->value)->latest('Id')->first();
        if (!$cred instanceof APICredential) {
            throw new ErroredException('no InfoBip configuration Found');
        }

        $this->sendApi = new EmailApi(config: new Configuration(
            host:  $cred->Configuration?->Host,
            apiKey:  $cred->Configuration?->Key,
        ));
        $this->email = $cred->Configuration?->Email;
    }

    public static function testConfig(
        string $host,
        string $email,
        #[SensitiveParameter] string $ApiKey,
        User $actor
    ): bool {
        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->get($host);

            if (
                $response->getStatusCode() === 200 &&
                json_decode((string)$response->getBody(), true, 512) === 'OK'
            ) {
                (new EmailApi(config: new Configuration(
                    host: $host,
                    apiKey: $ApiKey
                )))->sendEmail(
                    to:[$actor->Email],
                    from: $email,
                    subject:'Infobip Configuration Test Email',
                    html: '<p>This is a test email to confirm that the Infobip email integration has been successfully configured.</p>'
                );

                return true;
            }
        } catch (\Exception | GuzzleException | ApiException) {
        }
        return false;
    }

    public function sendEmail(Email $email, string $replyTo = null): ?string
    {
        try {
            $attachments = [];
            foreach ($email->attachments()->get() as $attachment) {
                if ($attachment instanceof \App\Models\DMS\Image) {
                    $path = (new ImageService($attachment))->getTempPath();
                    if ($path) {
                        $attachments[] = $path;
                    }
                }
            }

            $response = $this->sendApi->sendEmail(
                to: collect($email->To)->flatten()->toArray(),
                from: $this->email,
                subject: $email->Subject,
                html: $email->Body,
                attachment: $attachments,
                campaignReferenceId: $email->SourceID,
                replyTo: $replyTo
            );

            if ($response instanceof \Infobip\Model\EmailSendResponse) {
                return $response->getMessages()[0]?->getMessageId();
            }
        } catch (ApiException $e) {
            Log::error('Sending InfoBip Email: ' . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Unexpected InfoBip send error: ' . $e->getMessage());
        }

        return null;
    }
}

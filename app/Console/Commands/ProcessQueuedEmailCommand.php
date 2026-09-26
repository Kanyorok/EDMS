<?php

namespace App\Console\Commands;

use App\Enums\EmailStatusEnum;
use App\Models\Communication\Email;
use App\Services\CRMEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessQueuedEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-queued-email-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Email::query()
            ->where('Status', EmailStatusEnum::Queued)
            ->where('Type', 'o')
            ->orderBy('EmailID')
            ->chunkById(50, function ($emails) {
                foreach ($emails as $email) {
                    try {
                        (new CRMEmailService($email))->send(true);
                    } catch (\Throwable $e) {
                        Log::error('ProcessQueuedEmailCommand failed for EmailID=' . $email->EmailID . ': ' . $e->getMessage());
                        $email->refresh();
                        if ($email->Status->value !== EmailStatusEnum::Sent->value) {
                            $email->update([
                                'Status' => EmailStatusEnum::Failed->value,
                                'Dated' => now(),
                            ]);
                        }
                    }
                }
            }, 'EmailID');
    }
}

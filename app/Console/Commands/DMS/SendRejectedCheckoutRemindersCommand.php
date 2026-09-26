<?php

namespace App\Console\Commands\DMS;

use App\Models\Auth\User;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentCheckOut;
use App\Enums\DMS\DocumentCheckOutStatusEnum;
use App\Services\HRM\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reminds the document owner to correct and check back in a document that was force-checked
 * out due to a validation rejection, up to 3 times over the 3-day grace period before
 * TrashRejectedCheckoutsCommand disposes of the current version.
 *
 * Scheduled hourly in Console\Kernel so each offset below is hit close to on time regardless
 * of what time of day the rejection happened.
 */
class SendRejectedCheckoutRemindersCommand extends Command
{
    protected $signature   = 'dms:remind-rejected-checkouts';
    protected $description = 'Email the document owner to check a rejected document back in, up to 3 times before it is auto-trashed.';

    /** Hours after checkout at which reminders 1, 2 and 3 become due. */
    private const REMINDER_OFFSETS_HOURS = [24, 48, 66];

    public function handle(): int
    {
        $maxReminders = count(self::REMINDER_OFFSETS_HOURS);

        $dueCheckouts = DocumentCheckOut::query()
            ->where('Status', DocumentCheckOutStatusEnum::CheckOut->value)
            ->where('CheckOutRemark', 'like', '[REJECTED]%')
            ->where('RejectionReminderCount', '<', $maxReminders)
            ->with('document')
            ->get();

        $sent = 0;

        foreach ($dueCheckouts as $checkout) {
            $document = $checkout->document;
            if (! $document instanceof Document || $document->trashed()) {
                continue;
            }

            $offsetHours = self::REMINDER_OFFSETS_HOURS[$checkout->RejectionReminderCount];
            if (now()->lessThan($checkout->CreatedOn->clone()->addHours($offsetHours))) {
                continue;
            }

            $owner = User::query()->find($document->CreatedBy);
            if (! $owner instanceof User) {
                continue;
            }

            $reminderNumber = $checkout->RejectionReminderCount + 1;

            try {
                (new UserService($owner))->sendEmail(
                    "Action Required: Check in rejected document '{$document->Name}' (reminder {$reminderNumber} of {$maxReminders})",
                    body: '<p>Dear ' . $owner->UserID . ',</p>'
                        . '<p>Your document <b>' . e($document->Name) . '</b> was rejected during validation and has been checked out for correction.</p>'
                        . '<p>' . e($checkout->CheckOutRemark) . '</p>'
                        . '<p>Please correct and check the document back in. If it is not checked in within 3 days of the rejection, '
                        . 'the current version will be permanently deleted and moved to trash.</p>',
                    immediate: true
                );

                $checkout->update([
                    'RejectionReminderCount'      => $reminderNumber,
                    'RejectionReminderLastSentOn' => now(),
                    'ModifiedBy'                  => $document->CreatedBy,
                ]);

                $this->info("Reminder {$reminderNumber}/{$maxReminders} sent for document {$document->DocumentId}");
                $sent++;
            } catch (\Throwable $e) {
                Log::error("Failed to send rejection reminder for document {$document->DocumentId}: " . $e);
                $this->error("Failed to send reminder for document {$document->DocumentId}: " . $e->getMessage());
            }
        }

        $this->info("Done. {$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}

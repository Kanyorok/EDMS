<?php

namespace App\Console\Commands\DMS;

use App\Models\DMS\Document;
use App\Models\DMS\DocumentCheckOut;
use App\Enums\DMS\DocumentCheckOutStatusEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trashes documents that were force-checked out due to a validation rejection
 * and have not been checked back in within 3 days.
 *
 * Scheduled daily in Console\Kernel.
 */
class TrashRejectedCheckoutsCommand extends Command
{
    protected $signature   = 'dms:trash-rejected-checkouts';
    protected $description = 'Trash documents that were force-checked out after rejection and have not been returned within 3 days.';

    public function handle(): int
    {
        // Find checkout records that:
        //  - are still in CheckOut status
        //  - carry the [REJECTED] tag in their remark
        //  - are older than 3 days
        $cutoff = now()->subDays(3);

        $staleCheckouts = DocumentCheckOut::query()
            ->where('Status', DocumentCheckOutStatusEnum::CheckOut->value)
            ->where('CheckOutRemark', 'like', '[REJECTED]%')
            ->where('CreatedOn', '<=', $cutoff)
            ->with('document')
            ->get();

        if ($staleCheckouts->isEmpty()) {
            $this->info('No stale rejected checkouts found.');
            return self::SUCCESS;
        }

        $trashed = 0;
        foreach ($staleCheckouts as $checkout) {
            $document = $checkout->document;
            if (! $document instanceof Document || $document->trashed()) {
                continue;
            }

            try {
                DB::transaction(function () use ($document, $checkout) {
                    // Cancel the checkout record.
                    $checkout->update([
                        'Status'       => DocumentCheckOutStatusEnum::Canceled->value,
                        'Dated'        => now(),
                        'CheckInRemark'=> 'Auto-canceled: document trashed after 3-day rejection window expired.',
                        'ModifiedBy'   => $document->CreatedBy,
                    ]);

                    // Soft-delete (trash) the document.
                    $document->forceFill([
                        'DeletedOn' => now(),
                        'DeletedBy' => $document->CreatedBy,
                    ])->save();
                });

                activity()
                    ->performedOn($document)
                    ->event('auto-trash')
                    ->log("Document '{$document->Name}' auto-trashed: not checked in within 3 days of rejection.");

                $this->info("Trashed: {$document->Name} (ID: {$document->DocumentId})");
                $trashed++;
            } catch (\Throwable $e) {
                Log::error("Failed to auto-trash document {$document->DocumentId} after rejection checkout: " . $e);
                $this->error("Failed to trash document {$document->DocumentId}: " . $e->getMessage());
            }
        }

        $this->info("Done. {$trashed} document(s) auto-trashed.");
        return self::SUCCESS;
    }
}

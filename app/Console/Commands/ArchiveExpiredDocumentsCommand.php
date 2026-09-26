<?php

namespace App\Console\Commands;

use App\Exceptions\ErroredException;
use App\Helpers\SystemHelper;
use App\Models\DMS\Document;
use App\Services\DMS\DocumentService;
use App\Services\HRM\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveExpiredDocumentsCommand extends Command
{
    protected $signature = 'dms:archive-expired-documents';
    protected $description = 'Archive DMS documents whose ExpiryDate has passed, and notify the uploader.';

    public function handle(): void
    {
        $actor = SystemHelper::user();

        $documents = Document::query()
            ->whereNull('ArchivedOn')
            ->whereNotNull('ExpiryDate')
            ->where('ExpiryDate', '<', today())
            ->with('creator')
            ->get();

        foreach ($documents as $document) {
            try {
                (new DocumentService($document))->archive($actor, 'expired');
            } catch (ErroredException $e) {
                Log::error('archiving expired document ' . $document->DocumentId . ' failed : ' . $e->getMessage());

                continue;
            }

            $this->info("Archived {$document->Name} ({$document->DocumentId}), expired {$document->ExpiryDate->toDateString()}.");

            if ($document->creator) {
                (new UserService($document->creator))->sendEmail(
                    'Document Archived: ' . $document->Name,
                    '<p>Dear ' . e($document->creator->Name) . ',</p>'
                    . '<p>The document below has passed its expiry date and has been automatically archived. It is no longer shown in the repository browser by default, but has not been deleted and can be restored.</p>'
                    . '<table style="border-collapse:collapse;width:100%;">'
                    . '<tr><td style="padding:6px;font-weight:bold;">Document:</td><td style="padding:6px;">' . e($document->Name) . '</td></tr>'
                    . '<tr><td style="padding:6px;font-weight:bold;">Expiry Date:</td><td style="padding:6px;">' . $document->ExpiryDate->format('d M Y') . '</td></tr>'
                    . '</table>'
                    . '<p>Thank you.</p>',
                    immediate: true
                );
            }
        }
    }
}

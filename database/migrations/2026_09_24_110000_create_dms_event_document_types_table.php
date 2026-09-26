<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * An event offers a LIST of document types, and the person filing picks which document it is: "Account
     * documents" carries National ID, Proof of residence, Payslip and so on, each with its own rules (extensions,
     * size, expiry, repository). Until now an event named exactly one document type, which made the external
     * system's "what is this document?" question a one-item list.
     *
     * Every existing event keeps the type it had: it moves into the new table before the old column goes.
     */
    public function up(): void
    {
        Schema::create('t_DMSEventDocumentTypes', static function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('EventTypeId')->constrained('t_DMSEventTypes', 'Id');
            $table->foreignId('DocumentTypeId')->constrained('t_DMSDocumentTypes', 'Id');
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');

            $table->unique(['EventTypeId', 'DocumentTypeId']);
        });

        $now = now();
        DB::table('t_DMSEventTypes')->whereNotNull('DocumentTypeId')->get(['Id', 'DocumentTypeId', 'CreatedBy'])
            ->each(fn ($event) => DB::table('t_DMSEventDocumentTypes')->insert([
                'EventTypeId' => $event->Id,
                'DocumentTypeId' => $event->DocumentTypeId,
                'CreatedBy' => $event->CreatedBy,
                'CreatedOn' => $now,
                'ModifiedBy' => $event->CreatedBy,
                'ModifiedOn' => $now,
            ]));

        Schema::table('t_DMSEventTypes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('DocumentTypeId');
        });
    }

    public function down(): void
    {
        Schema::table('t_DMSEventTypes', function (Blueprint $table) {
            $table->foreignId('DocumentTypeId')->nullable()->after('Description')->constrained('t_DMSDocumentTypes', 'Id');
        });

        // Only one type fits the old column: the first one, which is lossy for events that had several.
        DB::table('t_DMSEventTypes')->get(['Id'])->each(function ($event) {
            $first = DB::table('t_DMSEventDocumentTypes')->where('EventTypeId', $event->Id)->orderBy('Id')->value('DocumentTypeId');
            if ($first) {
                DB::table('t_DMSEventTypes')->where('Id', $event->Id)->update(['DocumentTypeId' => $first]);
            }
        });

        Schema::dropIfExists('t_DMSEventDocumentTypes');
    }
};

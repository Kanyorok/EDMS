<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Promotes the free-typed Folio string on t_Documents to a real FolioId
     * FK against t_DMSFolios, so a Folio can be searched/reused reliably and
     * a document's Folio membership is enforced as zero-or-one by the column
     * itself. Existing distinct Folio strings are backfilled into one
     * t_DMSFolios row each before the string column is dropped.
     */
    public function up(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->foreignId('FolioId')->nullable()->after('LinkedId')
                ->constrained('t_DMSFolios', 'Id')->nullOnDelete();
        });

        $actorId = DB::table('t_Documents')->whereNotNull('CreatedBy')->value('CreatedBy');
        if (! $actorId && Schema::hasTable('t_Users')) {
            $actorId = DB::table('t_Users')->min('Id');
        }

        if ($actorId) {
            $date = now();
            $folios = DB::table('t_Documents')->whereNotNull('Folio')->distinct()->pluck('Folio');

            foreach ($folios as $code) {
                $folioId = DB::table('t_DMSFolios')->insertGetId([
                    'Code' => $code,
                    'CreatedBy' => $actorId,
                    'CreatedOn' => $date,
                    'ModifiedBy' => $actorId,
                    'ModifiedOn' => $date,
                ], 'Id');

                DB::table('t_Documents')->where('Folio', $code)->update(['FolioId' => $folioId]);
            }
        }

        Schema::table('t_Documents', function (Blueprint $table) {
            $table->dropColumn('Folio');
        });
    }

    public function down(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->string('Folio', 100)->nullable()->after('LinkedId');
        });

        DB::statement('UPDATE d SET d.Folio = f.Code FROM t_Documents d INNER JOIN t_DMSFolios f ON d.FolioId = f.Id');

        Schema::table('t_Documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('FolioId');
        });
    }
};

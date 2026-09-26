<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Repairs databases where the compatibility table was created with Notes. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_DMSPhysicalDocuments')) {
            return;
        }

        $hasNotes = Schema::hasColumn('t_DMSPhysicalDocuments', 'Notes');
        $hasDescription = Schema::hasColumn('t_DMSPhysicalDocuments', 'Description');

        if ($hasNotes && ! $hasDescription) {
            Schema::table('t_DMSPhysicalDocuments', function (Blueprint $table) {
                $table->renameColumn('Notes', 'Description');
            });

            return;
        }

        if (! $hasDescription) {
            Schema::table('t_DMSPhysicalDocuments', function (Blueprint $table) {
                $table->string('Description', 1000)->nullable();
            });

            return;
        }

        if ($hasNotes) {
            DB::table('t_DMSPhysicalDocuments')
                ->whereNull('Description')
                ->update(['Description' => DB::raw('Notes')]);

            Schema::table('t_DMSPhysicalDocuments', function (Blueprint $table) {
                $table->dropColumn('Notes');
            });
        }
    }

    public function down(): void
    {
        // Forward-only repair: Description is the canonical application field.
    }
};

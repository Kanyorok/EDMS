<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('t_DocumentSignatures', 'Status')) {
            Schema::table('t_DocumentSignatures', static function (Blueprint $table) {
                $table->string('Status', 50)->default('p')->after('Content')->comment('p=pending, a=approved, r=rejected');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_DocumentSignatures', static function (Blueprint $table) {
            $table->dropColumn('Status');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('t_Users', function (Blueprint $table) {
            if (! Schema::hasColumn('t_Users', 'UIPreferences')) {
                $table->json('UIPreferences')->nullable()->after('CurrentSessionId');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_Users', function (Blueprint $table) {
            if (Schema::hasColumn('t_Users', 'UIPreferences')) {
                $table->dropColumn('UIPreferences');
            }
        });
    }
};

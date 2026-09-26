<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Collected at upload time when the document's type has ExpiryRequired
     * set. Nullable: most existing documents, and documents of a type with no
     * expiry rule, never have one.
     */
    public function up(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->date('ExpiryDate')->nullable()->after('Notes');
        });
    }

    public function down(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->dropColumn('ExpiryDate');
        });
    }
};

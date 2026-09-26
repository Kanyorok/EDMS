<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A case-level container grouping documents uploaded for one business
     * transaction (one loan application, one onboarding case) - potentially
     * across different Document Types and different Linked-to Types. Status
     * is reserved for a future workflow module (Open -> Submitted ->
     * Closed) and is deliberately a plain nullable string here: this
     * upload-facing schema does not enforce or hardcode any transitions on
     * it.
     */
    public function up(): void
    {
        Schema::create('t_DMSFolios', static function (Blueprint $table) {
            $table->id('Id');
            $table->string('Code', 100)->unique();
            $table->string('Name', 150)->nullable();
            $table->text('Description')->nullable();
            $table->string('Status', 50)->nullable();
            $table->dateTime('ClosedOn')->nullable();
            $table->foreignId('ClosedBy')->nullable()->constrained('t_Users', 'Id');
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_DMSFolios');
    }
};

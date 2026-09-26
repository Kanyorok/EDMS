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
        Schema::create('t_DocumentReviews', static function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('DocumentId')->constrained('t_Documents', 'Id');
            $table->foreignId('VersionId')->constrained('t_DocumentVersions', 'Id');
            $table->foreignId('ReviewerId')->constrained('t_Users', 'Id');
            $table->char('Status', 2)->comment('DocumentReviewStatusEnum - pe,rv');
            $table->longText('RequestNote')->nullable();
            $table->longText('ReviewComment')->nullable();
            $table->dateTime('ReviewedOn')->nullable();
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');
        });

        Schema::create('t_DocumentReviewAnnotations', static function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('DocumentReviewId')->constrained('t_DocumentReviews', 'Id');
            $table->unsignedInteger('Page');
            $table->decimal('X', 6, 4)->comment('fraction of page width, 0-1');
            $table->decimal('Y', 6, 4)->comment('fraction of page height, 0-1');
            $table->longText('Comment');
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_DocumentReviewAnnotations');
        Schema::dropIfExists('t_DocumentReviews');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Document type definitions: upload rules (extensions, size, mandatory,
     * expiry) plus the repository and tag a document of this type inherits.
     * "Category" here is a repository (t_Repositories) and "Record
     * classification" is a tag (t_DMSTags) - the two concepts this module
     * already had, reused rather than duplicated under new names.
     */
    public function up(): void
    {
        Schema::create('t_DMSDocumentTypes', static function (Blueprint $table) {
            $table->id('Id');
            $table->string('Code', 50)->unique();
            $table->string('Name', 150);
            $table->foreignId('RepositoryId')->nullable()->constrained('t_Repositories', 'Id');
            $table->foreignId('TagId')->nullable()->constrained('t_DMSTags', 'Id');
            $table->char('Validation', 2)->nullable()->comment('DocumentTypeValidationEnum - none,fc,sg');
            $table->string('EntityApplicability', 255)->nullable();
            $table->string('AllowedExtensions', 500)->nullable()->comment('comma-separated, e.g. .pdf,.jpg,.png');
            $table->unsignedInteger('MaxSizeMB')->default(10);
            $table->boolean('IsMandatory')->default(false);
            $table->boolean('ExpiryRequired')->default(false);
            $table->boolean('IsActive')->default(true);
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
        Schema::dropIfExists('t_DMSDocumentTypes');
    }
};

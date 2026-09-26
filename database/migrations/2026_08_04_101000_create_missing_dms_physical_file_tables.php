<?php

use App\Enums\Core\VisibilityEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compatibility for databases that ran 100000 before physical custody was
 * added to that migration. Fresh databases already have these tables and this
 * migration is consequently a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_DMSStorageLocations_IdRegistry' AND object_id = OBJECT_ID('t_DMSStorageLocations')) CREATE UNIQUE INDEX UX_DMSStorageLocations_IdRegistry ON t_DMSStorageLocations (Id, RegistryId)");

        if (! Schema::hasTable('t_DMSPhysicalFiles')) {
            Schema::create('t_DMSPhysicalFiles', function (Blueprint $table) {
                $table->id('Id');
                $table->string('FileNumber', 100);
                $table->string('Title', 255);
                $table->string('Description', 1000)->nullable();
                $table->string('Barcode', 100)->nullable();
                $table->foreignId('HomeRegistryId')->constrained('t_DMSRegistries', 'Id');
                $table->foreignId('HomeLocationId');
                $table->foreignId('CurrentRegistryId')->nullable()->constrained('t_DMSRegistries', 'Id');
                $table->foreignId('CurrentLocationId')->nullable();
                $table->foreignId('CurrentHolderUserId')->nullable()->constrained('t_Users', 'Id');
                $table->foreignId('FileStatusId')->constrained('t_CodeDetails', 'ID');
                $table->date('OpenedOn')->nullable();
                $table->date('ClosedOn')->nullable();
                $table->dateTime('ArchivedOn')->nullable();
                $table->char('Visibility', 3)->default(VisibilityEnum::Private->value);
                $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
                $table->dateTime('CreatedOn');
                $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
                $table->dateTime('ModifiedOn');
                $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
                $table->softDeletes('DeletedOn');
                $table->index('HomeLocationId', 'IX_DMSPhysicalFiles_HomeLocation');
                $table->index('CurrentLocationId', 'IX_DMSPhysicalFiles_CurrentLocation');
                $table->index('CurrentHolderUserId', 'IX_DMSPhysicalFiles_CurrentHolder');
                $table->index('FileStatusId', 'IX_DMSPhysicalFiles_Status');
            });
            DB::statement('ALTER TABLE t_DMSPhysicalFiles ADD CONSTRAINT FK_DMSPhysicalFiles_HomeLocation FOREIGN KEY (HomeLocationId, HomeRegistryId) REFERENCES t_DMSStorageLocations (Id, RegistryId)');
            DB::statement('ALTER TABLE t_DMSPhysicalFiles ADD CONSTRAINT FK_DMSPhysicalFiles_CurrentLocation FOREIGN KEY (CurrentLocationId, CurrentRegistryId) REFERENCES t_DMSStorageLocations (Id, RegistryId)');
            DB::statement('CREATE UNIQUE INDEX UX_DMSPhysicalFiles_FileNumber ON t_DMSPhysicalFiles (HomeRegistryId, FileNumber) WHERE DeletedOn IS NULL');
            DB::statement('CREATE UNIQUE INDEX UX_DMSPhysicalFiles_Barcode ON t_DMSPhysicalFiles (Barcode) WHERE Barcode IS NOT NULL AND DeletedOn IS NULL');
            DB::statement('ALTER TABLE t_DMSPhysicalFiles ADD CONSTRAINT CK_DMSPhysicalFiles_CurrentPlace CHECK ((CurrentLocationId IS NULL AND CurrentRegistryId IS NULL) OR (CurrentLocationId IS NOT NULL AND CurrentRegistryId IS NOT NULL))');
            DB::statement('ALTER TABLE t_DMSPhysicalFiles ADD CONSTRAINT CK_DMSPhysicalFiles_OneCustodian CHECK (CurrentLocationId IS NULL OR CurrentHolderUserId IS NULL)');
        }

        if (! Schema::hasTable('t_DMSPhysicalDocuments')) {
            Schema::create('t_DMSPhysicalDocuments', function (Blueprint $table) {
                $table->id('Id');
                $table->foreignId('PhysicalFileId')->constrained('t_DMSPhysicalFiles', 'Id');
                $table->foreignId('DocumentId')->nullable()->constrained('t_Documents', 'Id');
                $table->string('ReferenceNumber', 150)->nullable();
                $table->string('Title', 255);
                $table->date('DocumentDate')->nullable();
                $table->integer('SortOrder')->default(0);
                $table->string('Description', 1000)->nullable();
                $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
                $table->dateTime('CreatedOn');
                $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
                $table->dateTime('ModifiedOn');
                $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
                $table->softDeletes('DeletedOn');
                $table->index('PhysicalFileId', 'IX_DMSPhysicalDocuments_File');
                $table->index('DocumentId', 'IX_DMSPhysicalDocuments_Document');
            });
        }

        if (! Schema::hasTable('t_DMSPhysicalFileRequests')) {
            Schema::create('t_DMSPhysicalFileRequests', function (Blueprint $table) {
                $table->id('Id');
                $table->foreignId('PhysicalFileId')->constrained('t_DMSPhysicalFiles', 'Id');
                $table->foreignId('RequestedByUserId')->constrained('t_Users', 'Id');
                $table->dateTime('RequestedOn');
                $table->dateTime('NeededFrom');
                $table->dateTime('RequestedUntil');
                $table->string('Purpose', 1000);
                $table->foreignId('RequestStatusId')->constrained('t_CodeDetails', 'ID');
                $table->foreignId('DecidedByUserId')->nullable()->constrained('t_Users', 'Id');
                $table->dateTime('DecidedOn')->nullable();
                $table->string('DecisionRemark', 1000)->nullable();
                $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
                $table->dateTime('CreatedOn');
                $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
                $table->dateTime('ModifiedOn');
                $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
                $table->softDeletes('DeletedOn');
                $table->index(['PhysicalFileId', 'RequestStatusId'], 'IX_DMSPhysicalFileRequests_FileStatus');
                $table->index('RequestedByUserId', 'IX_DMSPhysicalFileRequests_Requester');
            });
            DB::statement('ALTER TABLE t_DMSPhysicalFileRequests ADD CONSTRAINT CK_DMSPhysicalFileRequests_DateRange CHECK (RequestedUntil >= NeededFrom)');
        }

        if (! Schema::hasTable('t_DMSPhysicalFileCheckouts')) {
            Schema::create('t_DMSPhysicalFileCheckouts', function (Blueprint $table) {
                $table->id('Id');
                $table->foreignId('PhysicalFileId')->constrained('t_DMSPhysicalFiles', 'Id');
                $table->foreignId('RequestId')->constrained('t_DMSPhysicalFileRequests', 'Id');
                $table->foreignId('BorrowerUserId')->constrained('t_Users', 'Id');
                $table->foreignId('IssuedByUserId')->constrained('t_Users', 'Id');
                $table->dateTime('CheckedOutOn');
                $table->dateTime('DueOn');
                $table->string('CheckOutRemark', 1000)->nullable();
                $table->foreignId('CheckoutStatusId')->constrained('t_CodeDetails', 'ID');
                $table->dateTime('ClosedOn')->nullable();
                $table->dateTime('ReturnedOn')->nullable();
                $table->foreignId('ReturnedToLocationId')->nullable()->constrained('t_DMSStorageLocations', 'Id');
                $table->foreignId('ReceivedByUserId')->nullable()->constrained('t_Users', 'Id');
                $table->string('CheckInRemark', 1000)->nullable();
                $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
                $table->dateTime('CreatedOn');
                $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
                $table->dateTime('ModifiedOn');
                $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
                $table->softDeletes('DeletedOn');
                $table->index('BorrowerUserId', 'IX_DMSPhysicalFileCheckouts_Borrower');
                $table->index('DueOn', 'IX_DMSPhysicalFileCheckouts_DueOn');
            });
            DB::statement('CREATE UNIQUE INDEX UX_DMSPhysicalFileCheckouts_Request ON t_DMSPhysicalFileCheckouts (RequestId) WHERE DeletedOn IS NULL');
            DB::statement('CREATE UNIQUE INDEX UX_DMSPhysicalFileCheckouts_ActiveFile ON t_DMSPhysicalFileCheckouts (PhysicalFileId) WHERE ClosedOn IS NULL AND DeletedOn IS NULL');
            DB::statement('ALTER TABLE t_DMSPhysicalFileCheckouts ADD CONSTRAINT CK_DMSPhysicalFileCheckouts_DateRange CHECK (DueOn >= CheckedOutOn)');
        }

        if (! Schema::hasTable('t_DMSPhysicalFileMovements')) {
            Schema::create('t_DMSPhysicalFileMovements', function (Blueprint $table) {
                $table->id('Id');
                $table->foreignId('PhysicalFileId')->constrained('t_DMSPhysicalFiles', 'Id');
                $table->foreignId('RequestId')->nullable()->constrained('t_DMSPhysicalFileRequests', 'Id');
                $table->foreignId('CheckoutId')->nullable()->constrained('t_DMSPhysicalFileCheckouts', 'Id');
                $table->foreignId('FromRegistryId')->nullable()->constrained('t_DMSRegistries', 'Id');
                $table->foreignId('FromLocationId')->nullable();
                $table->foreignId('FromHolderUserId')->nullable()->constrained('t_Users', 'Id');
                $table->foreignId('ToRegistryId')->nullable()->constrained('t_DMSRegistries', 'Id');
                $table->foreignId('ToLocationId')->nullable();
                $table->foreignId('ToHolderUserId')->nullable()->constrained('t_Users', 'Id');
                $table->foreignId('MovementTypeId')->constrained('t_CodeDetails', 'ID');
                $table->string('Reason', 1000)->nullable();
                $table->foreignId('DispatchedByUserId')->constrained('t_Users', 'Id');
                $table->dateTime('DispatchedOn');
                $table->foreignId('ReceivedByUserId')->nullable()->constrained('t_Users', 'Id');
                $table->dateTime('ReceivedOn')->nullable();
                $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
                $table->dateTime('CreatedOn');
                $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
                $table->dateTime('ModifiedOn');
                $table->index(['PhysicalFileId', 'DispatchedOn'], 'IX_DMSPhysicalFileMovements_FileDate');
                $table->index('RequestId', 'IX_DMSPhysicalFileMovements_Request');
                $table->index('CheckoutId', 'IX_DMSPhysicalFileMovements_Checkout');
            });
            DB::statement('ALTER TABLE t_DMSPhysicalFileMovements ADD CONSTRAINT FK_DMSPhysicalFileMovements_FromLocationRegistry FOREIGN KEY (FromLocationId, FromRegistryId) REFERENCES t_DMSStorageLocations (Id, RegistryId)');
            DB::statement('ALTER TABLE t_DMSPhysicalFileMovements ADD CONSTRAINT FK_DMSPhysicalFileMovements_ToLocationRegistry FOREIGN KEY (ToLocationId, ToRegistryId) REFERENCES t_DMSStorageLocations (Id, RegistryId)');
        }
    }

    public function down(): void
    {
        // Forward compatibility migration: table ownership remains with 100000.
    }
};

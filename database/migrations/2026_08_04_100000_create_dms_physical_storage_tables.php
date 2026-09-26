<?php

use App\Enums\Core\VisibilityEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | t_DMSRegistries
        |----------------------------------------------------------------------
        | A registry is a department that custodies physical records. There may
        | be many, and one may parent others (HQ registry -> branch registries).
        |
        | Name doubles as getSharedName() for the SpecialPermission traits.
        | Visibility defaults to Public: staff may know which registries exist,
        | while what sits inside them is governed by grants.
        */
        Schema::create('t_DMSRegistries', function (Blueprint $table) {
            $table->id('Id');

            $table->string('Code', 20);
            $table->string('Name', 150);
            $table->string('Description', 255)->nullable();

            $table->foreignId('ParentRegistryId')->nullable();
            $table->foreignId('BranchId')->nullable()->constrained('t_Branches', 'Id');

            // The officer who sanctions requests raised against this registry.
            // An 'a' grant on the registry is what lets a delegate stand in.
            $table->foreignId('HodUserId')->constrained('t_Users', 'Id');

            // Marks a registry that holds archived / semi-current records.
            $table->boolean('IsArchive')->default(false);

            // t_CodeDetails: RegistryStatusId -> active | inactive
            $table->foreignId('RegistryStatusId')->constrained('t_CodeDetails', 'ID');

            // Read by SpecialPermissionTrait::scopeUser / scopeUserRole, which
            // compares against VisibilityEnum values ('pub' / 'pri') - hence 3.
            $table->char('Visibility', 3)->default(VisibilityEnum::Public->value)
                  ->comment('pub,pri');

            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');

            $table->index('ParentRegistryId', 'IX_DMSRegistries_ParentRegistryId');
            $table->index('RegistryStatusId', 'IX_DMSRegistries_Status');
            $table->index('Visibility', 'IX_DMSRegistries_Visibility');
        });

        /*
        |----------------------------------------------------------------------
        | t_DMSStorageLocations
        |----------------------------------------------------------------------
        | Cabinets, shelves, boxes and trays all live here as one tree, so the
        | nesting depth can differ between registries.
        |
        | Path is a materialized address maintained by StorageLocationService.
        | It is stored with a leading and trailing slash so that a descendant
        | test is an unambiguous prefix match: without the trailing slash,
        | '/HR-REG/CAB-04' would also match '/HR-REG/CAB-041'.
        |
        | InheritAccess = 0 detaches a node from ancestor grants and honours
        | only its own. Snapshot the inherited grants with copyPermissions()
        | before flipping it, or everyone loses access at once.
        */
        Schema::create('t_DMSStorageLocations', function (Blueprint $table) {
            $table->id('Id');

            $table->foreignId('RegistryId')->constrained('t_DMSRegistries', 'Id');

            // NULL parent = a top-level node standing in the registry.
            $table->foreignId('ParentId')->nullable();

            // t_CodeDetails: LocationTypeId -> cabinet | shelf | box | tray
            $table->foreignId('LocationTypeId')->constrained('t_CodeDetails', 'ID');

            // t_CodeDetails: LocationPurposeId
            //   -> standard | receiving | returns | transit | quarantine
            $table->foreignId('LocationPurposeId')->constrained('t_CodeDetails', 'ID');

            // t_CodeDetails: LocationStatusId
            //   -> active | full | sealed | retired | in_transit
            $table->foreignId('LocationStatusId')->constrained('t_CodeDetails', 'ID');

            $table->string('Code', 30);                 // CAB-04, SH-2, BX-11
            $table->string('Label', 150)->nullable();

            $table->string('Path', 400)->nullable();    // /HR-REG/CAB-04/SH-2/
            $table->smallInteger('Depth')->default(0);

            $table->string('Barcode', 50)->nullable();

            $table->integer('Capacity')->nullable();
            $table->integer('SortOrder')->default(0);

            // Contents are governed by grants, never openly visible.
            $table->char('Visibility', 3)->default(VisibilityEnum::Private->value)
                  ->comment('pub,pri');
            $table->boolean('InheritAccess')->default(true);

            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');

            $table->index('ParentId', 'IX_DMSStorageLocations_ParentId');
            $table->index('RegistryId', 'IX_DMSStorageLocations_RegistryId');
            $table->index('Path', 'IX_DMSStorageLocations_Path');
            $table->index(
                ['RegistryId', 'LocationPurposeId'],
                'IX_DMSStorageLocations_Purpose'
            );
        });

        /*
        |----------------------------------------------------------------------
        | t_DMSLocationTypeRules
        |----------------------------------------------------------------------
        | Declares which nesting arrangements are legal, so a cabinet may hold
        | shelves and boxes while a box may never hold a cabinet. A NULL
        | ParentTypeId means the child may sit directly under the registry.
        |
        | The rule set is system-wide: it bounds what any registry may build,
        | it does not pin a given registry to one layout. Enforced by
        | StorageLocationService, not by the database.
        */
        Schema::create('t_DMSLocationTypeRules', function (Blueprint $table) {
            $table->id('Id');

            $table->foreignId('ParentTypeId')->nullable()
                  ->constrained('t_CodeDetails', 'ID');
            $table->foreignId('ChildTypeId')
                  ->constrained('t_CodeDetails', 'ID');

            $table->boolean('IsActive')->default(true);

            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');
        });

        /*
        |----------------------------------------------------------------------
        | t_DMSPhysicalFiles / t_DMSPhysicalDocuments
        |----------------------------------------------------------------------
        | A physical file is the borrowable folder or jacket. Its HomeLocation
        | never changes merely because it is borrowed: CurrentLocationId and
        | CurrentHolderUserId describe where it is now, while the home remains
        | the unambiguous return destination.
        |
        | A file can contain paper-only documents as well as paper counterparts
        | of records already held in t_Documents. DocumentId is therefore
        | optional and deliberately not unique.
        */
        Schema::create('t_DMSPhysicalFiles', function (Blueprint $table) {
            $table->id('Id');

            $table->string('FileNumber', 100);
            $table->string('Title', 255);
            $table->string('Description', 1000)->nullable();
            $table->string('Barcode', 100)->nullable();

            $table->foreignId('HomeRegistryId')->constrained('t_DMSRegistries', 'Id');
            $table->foreignId('HomeLocationId');

            // Exactly one of current location or holder may be populated. Both
            // are null while the file is physically between two custodians.
            $table->foreignId('CurrentRegistryId')->nullable()
                  ->constrained('t_DMSRegistries', 'Id');
            $table->foreignId('CurrentLocationId')->nullable();
            $table->foreignId('CurrentHolderUserId')->nullable()
                  ->constrained('t_Users', 'Id');

            // t_CodeDetails: PhysicalFileStatusId
            // available | in_tray | in_transit | with_person | checked_out |
            // archived | missing
            $table->foreignId('FileStatusId')->constrained('t_CodeDetails', 'ID');

            $table->date('OpenedOn')->nullable();
            $table->date('ClosedOn')->nullable();
            $table->dateTime('ArchivedOn')->nullable();

            // Permission to discover/request the physical file is attached to
            // this row through SpecialPermissionTrait, like a digital document.
            $table->char('Visibility', 3)->default(VisibilityEnum::Private->value)
                  ->comment('pub,pri');

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

        Schema::create('t_DMSPhysicalDocuments', function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('PhysicalFileId')->constrained('t_DMSPhysicalFiles', 'Id');
            $table->foreignId('DocumentId')->nullable()->constrained('t_Documents', 'Id');
            $table->string('ReferenceNumber', 150)->nullable();
            $table->string('Title', 255);
            $table->date('DocumentDate')->nullable();
            $table->integer('SortOrder')->default(0);
            $table->string('Notes', 1000)->nullable();

            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');

            $table->index('PhysicalFileId', 'IX_DMSPhysicalDocuments_File');
            $table->index('DocumentId', 'IX_DMSPhysicalDocuments_Document');
        });

        /*
        |----------------------------------------------------------------------
        | Requests, checkouts and movements
        |----------------------------------------------------------------------
        | Requests are the approval record; checkouts are the actual loan.
        | Keeping them separate lets a registry approve a request without
        | pretending the file has already left its custody. Movements form an
        | append-only chain of custody for shelving, dispatch and receipt.
        */
        Schema::create('t_DMSPhysicalFileRequests', function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('PhysicalFileId')->constrained('t_DMSPhysicalFiles', 'Id');
            $table->foreignId('RequestedByUserId')->constrained('t_Users', 'Id');
            $table->dateTime('RequestedOn');
            $table->dateTime('NeededFrom');
            $table->dateTime('RequestedUntil');
            $table->string('Purpose', 1000);

            // t_CodeDetails: PhysicalFileRequestStatusId
            // pending | approved | rejected | cancelled | fulfilled | expired
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

            $table->index(
                ['PhysicalFileId', 'RequestStatusId'],
                'IX_DMSPhysicalFileRequests_FileStatus'
            );
            $table->index('RequestedByUserId', 'IX_DMSPhysicalFileRequests_Requester');
        });

        Schema::create('t_DMSPhysicalFileCheckouts', function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('PhysicalFileId')->constrained('t_DMSPhysicalFiles', 'Id');
            $table->foreignId('RequestId')->constrained('t_DMSPhysicalFileRequests', 'Id');
            $table->foreignId('BorrowerUserId')->constrained('t_Users', 'Id');
            $table->foreignId('IssuedByUserId')->constrained('t_Users', 'Id');
            $table->dateTime('CheckedOutOn');
            $table->dateTime('DueOn');
            $table->string('CheckOutRemark', 1000)->nullable();

            // t_CodeDetails: PhysicalFileCheckoutStatusId
            // active | returned | recalled
            $table->foreignId('CheckoutStatusId')->constrained('t_CodeDetails', 'ID');
            // Null until the loan is finally closed. A recall does not close
            // custody: the borrower still has the file until it is received.
            $table->dateTime('ClosedOn')->nullable();
            $table->dateTime('ReturnedOn')->nullable();
            $table->foreignId('ReturnedToLocationId')->nullable()
                  ->constrained('t_DMSStorageLocations', 'Id');
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

        Schema::create('t_DMSPhysicalFileMovements', function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('PhysicalFileId')->constrained('t_DMSPhysicalFiles', 'Id');
            $table->foreignId('RequestId')->nullable()
                  ->constrained('t_DMSPhysicalFileRequests', 'Id');
            $table->foreignId('CheckoutId')->nullable()
                  ->constrained('t_DMSPhysicalFileCheckouts', 'Id');

            $table->foreignId('FromRegistryId')->nullable()->constrained('t_DMSRegistries', 'Id');
            $table->foreignId('FromLocationId')->nullable()->constrained('t_DMSStorageLocations', 'Id');
            $table->foreignId('FromHolderUserId')->nullable()->constrained('t_Users', 'Id');
            $table->foreignId('ToRegistryId')->nullable()->constrained('t_DMSRegistries', 'Id');
            $table->foreignId('ToLocationId')->nullable()->constrained('t_DMSStorageLocations', 'Id');
            $table->foreignId('ToHolderUserId')->nullable()->constrained('t_Users', 'Id');

            // t_CodeDetails: PhysicalFileMovementTypeId
            // registered | relocated | dispatched | received | checked_out |
            // returned | archived | recalled | marked_missing | found
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

            $table->index(
                ['PhysicalFileId', 'DispatchedOn'],
                'IX_DMSPhysicalFileMovements_FileDate'
            );
            $table->index('RequestId', 'IX_DMSPhysicalFileMovements_Request');
            $table->index('CheckoutId', 'IX_DMSPhysicalFileMovements_Checkout');
        });

        /*
        |----------------------------------------------------------------------
        | Self-referencing keys
        |----------------------------------------------------------------------
        | Added after creation and deliberately without cascade: SQL Server
        | rejects multiple cascade paths, and a retired node must still resolve
        | historical records that point at it.
        |
        | Cycles are NOT prevented here - no constraint can express that.
        | StorageLocationService::assertNoCycle() guards every re-parent.
        */
        Schema::table('t_DMSRegistries', function (Blueprint $table) {
            $table->foreign('ParentRegistryId', 'FK_DMSRegistries_Parent')
                  ->references('Id')->on('t_DMSRegistries');
        });

        /*
        | The parent key is composite on purpose. A plain FK on ParentId only
        | proves the parent row exists; it would happily let a shelf in HR-REG
        | hang off a cabinet in FIN-REG, which corrupts Path and leaks contents
        | across custody boundaries. Including RegistryId forces parent and
        | child into the same registry. A NULL ParentId skips the check under
        | SQL Server's default FK semantics, so top-level nodes still work.
        */
        DB::statement("
            CREATE UNIQUE INDEX UX_DMSStorageLocations_IdRegistry
            ON t_DMSStorageLocations (Id, RegistryId)
        ");

        DB::statement("
            ALTER TABLE t_DMSStorageLocations
            ADD CONSTRAINT FK_DMSStorageLocations_Parent
            FOREIGN KEY (ParentId, RegistryId)
            REFERENCES t_DMSStorageLocations (Id, RegistryId)
        ");

        // The same composite identity prevents a file's declared home/current
        // registry disagreeing with the registry of the selected shelf/tray.
        DB::statement("
            ALTER TABLE t_DMSPhysicalFiles
            ADD CONSTRAINT FK_DMSPhysicalFiles_HomeLocation
            FOREIGN KEY (HomeLocationId, HomeRegistryId)
            REFERENCES t_DMSStorageLocations (Id, RegistryId)
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFiles
            ADD CONSTRAINT FK_DMSPhysicalFiles_CurrentLocation
            FOREIGN KEY (CurrentLocationId, CurrentRegistryId)
            REFERENCES t_DMSStorageLocations (Id, RegistryId)
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFileMovements
            ADD CONSTRAINT FK_DMSPhysicalFileMovements_FromLocationRegistry
            FOREIGN KEY (FromLocationId, FromRegistryId)
            REFERENCES t_DMSStorageLocations (Id, RegistryId)
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFileMovements
            ADD CONSTRAINT FK_DMSPhysicalFileMovements_ToLocationRegistry
            FOREIGN KEY (ToLocationId, ToRegistryId)
            REFERENCES t_DMSStorageLocations (Id, RegistryId)
        ");

        /*
        |----------------------------------------------------------------------
        | Filtered unique indexes
        |----------------------------------------------------------------------
        | Raw SQL because Blueprint cannot express a WHERE clause. SQL Server
        | treats NULLs as equal in a plain UNIQUE constraint, so a nullable
        | Barcode would permit only one unlabelled row table-wide, and a code
        | could never be re-used after a soft delete.
        */
        DB::statement("
            CREATE UNIQUE INDEX UX_DMSRegistries_Code
            ON t_DMSRegistries (Code)
            WHERE DeletedOn IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX UX_DMSStorageLocations_SiblingCode
            ON t_DMSStorageLocations (RegistryId, ParentId, Code)
            WHERE DeletedOn IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX UX_DMSStorageLocations_Barcode
            ON t_DMSStorageLocations (Barcode)
            WHERE Barcode IS NOT NULL AND DeletedOn IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX UX_DMSLocationTypeRules_Pair
            ON t_DMSLocationTypeRules (ParentTypeId, ChildTypeId)
            WHERE DeletedOn IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX UX_DMSPhysicalFiles_FileNumber
            ON t_DMSPhysicalFiles (HomeRegistryId, FileNumber)
            WHERE DeletedOn IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX UX_DMSPhysicalFiles_Barcode
            ON t_DMSPhysicalFiles (Barcode)
            WHERE Barcode IS NOT NULL AND DeletedOn IS NULL
        ");

        // A request can create at most one loan, and a file can only have one
        // live loan. Returned/recalled history remains unrestricted.
        DB::statement("
            CREATE UNIQUE INDEX UX_DMSPhysicalFileCheckouts_Request
            ON t_DMSPhysicalFileCheckouts (RequestId)
            WHERE DeletedOn IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX UX_DMSPhysicalFileCheckouts_ActiveFile
            ON t_DMSPhysicalFileCheckouts (PhysicalFileId)
            WHERE ClosedOn IS NULL AND DeletedOn IS NULL
        ");

        /*
        |----------------------------------------------------------------------
        | Value checks
        |----------------------------------------------------------------------
        | A node may not be its own parent. This catches the one-hop cycle at
        | the database; longer cycles remain the service layer's job.
        */
        DB::statement("
            ALTER TABLE t_DMSStorageLocations
            ADD CONSTRAINT CK_DMSStorageLocations_Capacity
            CHECK (Capacity IS NULL OR Capacity >= 0)
        ");

        DB::statement("
            ALTER TABLE t_DMSStorageLocations
            ADD CONSTRAINT CK_DMSStorageLocations_NotSelfParent
            CHECK (ParentId IS NULL OR ParentId <> Id)
        ");

        DB::statement("
            ALTER TABLE t_DMSRegistries
            ADD CONSTRAINT CK_DMSRegistries_NotSelfParent
            CHECK (ParentRegistryId IS NULL OR ParentRegistryId <> Id)
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFiles
            ADD CONSTRAINT CK_DMSPhysicalFiles_CurrentPlace
            CHECK (
                (CurrentLocationId IS NULL AND CurrentRegistryId IS NULL)
                OR (CurrentLocationId IS NOT NULL AND CurrentRegistryId IS NOT NULL)
            )
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFiles
            ADD CONSTRAINT CK_DMSPhysicalFiles_OneCustodian
            CHECK (CurrentLocationId IS NULL OR CurrentHolderUserId IS NULL)
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFileRequests
            ADD CONSTRAINT CK_DMSPhysicalFileRequests_DateRange
            CHECK (RequestedUntil >= NeededFrom)
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFileCheckouts
            ADD CONSTRAINT CK_DMSPhysicalFileCheckouts_DueDate
            CHECK (DueOn >= CheckedOutOn)
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFileMovements
            ADD CONSTRAINT CK_DMSPhysicalFileMovements_FromCustodian
            CHECK (
                (FromLocationId IS NULL AND FromRegistryId IS NULL)
                OR (FromLocationId IS NOT NULL AND FromRegistryId IS NOT NULL)
            )
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFileMovements
            ADD CONSTRAINT CK_DMSPhysicalFileMovements_ToCustodian
            CHECK (
                (ToLocationId IS NULL AND ToRegistryId IS NULL)
                OR (ToLocationId IS NOT NULL AND ToRegistryId IS NOT NULL)
            )
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFileMovements
            ADD CONSTRAINT CK_DMSPhysicalFileMovements_FromOneCustodian
            CHECK (FromLocationId IS NULL OR FromHolderUserId IS NULL)
        ");

        DB::statement("
            ALTER TABLE t_DMSPhysicalFileMovements
            ADD CONSTRAINT CK_DMSPhysicalFileMovements_ToOneCustodian
            CHECK (ToLocationId IS NULL OR ToHolderUserId IS NULL)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('t_DMSPhysicalFileMovements');
        Schema::dropIfExists('t_DMSPhysicalFileCheckouts');
        Schema::dropIfExists('t_DMSPhysicalFileRequests');
        Schema::dropIfExists('t_DMSPhysicalDocuments');
        Schema::dropIfExists('t_DMSPhysicalFiles');

        Schema::table('t_DMSRegistries', function (Blueprint $table) {
            $table->dropForeign('FK_DMSRegistries_Parent');
        });

        Schema::dropIfExists('t_DMSLocationTypeRules');
        Schema::dropIfExists('t_DMSStorageLocations');
        Schema::dropIfExists('t_DMSRegistries');
    }
};

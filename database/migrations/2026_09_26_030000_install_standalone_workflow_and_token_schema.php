<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_SYSPersonalAccessTokens')) {
            Schema::create('t_SYSPersonalAccessTokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('t_DocumentValidationTypes', 'WorkFlowId')) {
            Schema::table('t_DocumentValidationTypes', function (Blueprint $table): void {
                $table->dropForeign(['WorkFlowId']);
            });
        }

        Schema::dropIfExists('t_PendingWorkflows');
        Schema::dropIfExists('t_Workflows');

        if (! Schema::hasTable('t_WorkFlowTypes')) {
            Schema::create('t_WorkFlowTypes', function (Blueprint $table): void {
                $table->id('Id');
                $table->char('TypeID', 3)->unique();
                $table->string('Name', 100);
                $this->auditColumns($table);
            });
        }

        Schema::create('t_Workflows', function (Blueprint $table): void {
            $table->id('Id');
            $table->string('Name', 100)->index();
            $table->string('Source', 100);
            $table->string('FinalStage', 100)->nullable();
            $table->longText('Description')->nullable();
            $table->unsignedBigInteger('MainModuleID')->nullable();
            $table->foreign('MainModuleID')->references('ModuleID')->on('t_Modules')->nullOnDelete();
            $this->auditColumns($table);
        });

        if (Schema::hasColumn('t_DocumentValidationTypes', 'WorkFlowId')) {
            Schema::table('t_DocumentValidationTypes', function (Blueprint $table): void {
                $table->foreign('WorkFlowId')->references('Id')->on('t_Workflows')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('t_WorkFlowLimits')) {
            Schema::create('t_WorkFlowLimits', function (Blueprint $table): void {
                $table->id('Id');
                $table->string('Source')->index();
                $table->decimal('MaxAmount', 20, 4);
                $table->foreignId('PermissionId')->constrained('t_Permissions', 'id');
                $this->auditColumns($table);
            });
        }

        if (! Schema::hasTable('t_WorkFlowStages')) {
            Schema::create('t_WorkFlowStages', function (Blueprint $table): void {
                $table->id('Id');
                $table->unsignedSmallInteger('Order');
                $table->string('StageName');
                $table->integer('EscalationLimit')->default(0);
                $table->foreignId('WorkFlowId')->constrained('t_Workflows', 'Id')->cascadeOnDelete();
                $table->foreignId('WorkFlowTypeId')->nullable()->constrained('t_WorkFlowTypes', 'Id');
                $table->foreignId('WorkFlowLimitId')->nullable()->constrained('t_WorkFlowLimits', 'Id');
                $table->foreignId('PermissionId')->nullable()->constrained('t_Permissions', 'id');
                $table->foreignId('OverridePermissionId')->nullable()->constrained('t_Permissions', 'id');
                $table->integer('Count')->default(0);
                $table->boolean('IsActive')->default(true);
                $table->foreignId('StatusId')->nullable()->constrained('t_CodeDetails', 'ID');
                $this->auditColumns($table);
            });
        }

        if (! Schema::hasTable('t_WorkFlowHistory')) {
            Schema::create('t_WorkFlowHistory', function (Blueprint $table): void {
                $table->id('Id');
                $table->string('Source');
                $table->string('SourceID', 100);
                $table->string('Stage', 200);
                $table->decimal('Amount', 20, 4)->nullable();
                $table->longText('Notes')->nullable();
                $table->boolean('isApproved')->nullable();
                $table->foreignId('StatusId')->constrained('t_CodeDetails', 'ID');
                $this->auditColumns($table);
                $table->index(['Source', 'SourceID']);
            });
        }

        if (! Schema::hasTable('t_WorkFlowPending')) {
            Schema::create('t_WorkFlowPending', function (Blueprint $table): void {
                $table->id('Id');
                $table->string('Source');
                $table->string('SourceID', 100);
                $table->string('Stage', 200);
                $table->foreignId('UserId')->nullable()->constrained('t_Users', 'Id');
                $table->dateTime('EscalatedOn')->nullable();
                $this->auditColumns($table);
                $table->index(['Source', 'SourceID']);
            });
        }

        if (! Schema::hasTable('t_WorkFlowEscalation')) {
            Schema::create('t_WorkFlowEscalation', function (Blueprint $table): void {
                $table->id('Id');
                $table->foreignId('WorkFlowStageId')->constrained('t_WorkFlowStages', 'Id');
                $table->foreignId('UserId')->constrained('t_Users', 'Id');
                $table->foreignId('SupervisorId')->constrained('t_Users', 'Id');
                $table->text('Notes')->nullable();
                $this->auditColumns($table);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_WorkFlowEscalation');
        Schema::dropIfExists('t_WorkFlowPending');
        Schema::dropIfExists('t_WorkFlowHistory');
        Schema::dropIfExists('t_WorkFlowStages');
        Schema::dropIfExists('t_WorkFlowLimits');
        Schema::dropIfExists('t_Workflows');
        Schema::dropIfExists('t_WorkFlowTypes');
        Schema::dropIfExists('t_SYSPersonalAccessTokens');
    }

    private function auditColumns(Blueprint $table): void
    {
        $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
        $table->dateTime('CreatedOn');
        $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
        $table->dateTime('ModifiedOn');
        $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
        $table->softDeletes('DeletedOn');
    }
};

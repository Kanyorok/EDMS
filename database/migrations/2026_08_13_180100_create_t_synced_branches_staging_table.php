<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_SyncedBranchesStaging', function (Blueprint $table) {
            $table->id('Id');
            $table->string('BranchID')->unique();
            $table->string('Name')->nullable();
            $table->string('Address')->nullable();
            $table->string('Address2')->nullable();
            $table->string('City')->nullable();
            $table->string('State')->nullable();
            $table->string('Zip')->nullable();
            $table->string('Country')->nullable();
            $table->string('Phone')->nullable();
            $table->string('Fax')->nullable();
            $table->string('Email')->nullable();
            $table->boolean('IsHQ')->default(false);
            $table->boolean('IsActive')->default(1);
            $table->string('Source', 100)->nullable();
            $table->string('SourceTable', 255)->nullable();
            $table->boolean('IsSynced')->default(false);
            $table->foreignId('CreatedBy')->nullable()->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn')->nullable();
            $table->foreignId('ModifiedBy')->nullable()->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn')->nullable();
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->dateTime('DeletedOn')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_SyncedBranchesStaging');
    }
};

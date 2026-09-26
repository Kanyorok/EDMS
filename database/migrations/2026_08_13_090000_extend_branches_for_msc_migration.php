<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_Branches', function (Blueprint $table): void {
            $table->unsignedBigInteger('MSCRegionID')->nullable();
            $table->string('TollFree', 30)->nullable();
            $table->text('AboutBranch')->nullable();
            $table->text('DistrictsCovered')->nullable();
            $table->string('BranchStatus', 50)->nullable();
            $table->boolean('IsActive')->nullable();
        });

        DB::statement('ALTER TABLE dbo.t_Branches ALTER COLUMN IsHQ BIT NULL');
    }

    public function down(): void
    {
        DB::table('t_Branches')->whereNull('IsHQ')->update(['IsHQ' => false]);
        DB::statement('ALTER TABLE dbo.t_Branches ALTER COLUMN IsHQ BIT NOT NULL');

        Schema::table('t_Branches', function (Blueprint $table): void {
            $table->dropColumn([
                'MSCRegionID',
                'TollFree',
                'AboutBranch',
                'DistrictsCovered',
                'BranchStatus',
                'IsActive',
            ]);
        });
    }
};

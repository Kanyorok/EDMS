<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_Branches', function (Blueprint $table) {
            if (! Schema::hasColumn('t_Branches', 'Latitude')) {
                $table->decimal('Latitude', 10, 7)->nullable()->after('DistrictsCovered');
            }
            if (! Schema::hasColumn('t_Branches', 'Longitude')) {
                $table->decimal('Longitude', 10, 7)->nullable()->after('Latitude');
            }
            if (! Schema::hasColumn('t_Branches', 'GeofenceRadiusMeters')) {
                $table->unsignedInteger('GeofenceRadiusMeters')->nullable()->after('Longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('t_Branches', function (Blueprint $table) {
            $columns = array_filter(
                ['Latitude', 'Longitude', 'GeofenceRadiusMeters'],
                fn (string $column): bool => Schema::hasColumn('t_Branches', $column)
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

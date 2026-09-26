<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('t_Users', 'ClientID')) {
            Schema::table('t_Users', function (Blueprint $table): void {
                $table->dropIndex('t_users_clientid_index');
            });
        }

        Schema::table('t_Users', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['ClientID', 'Linked'],
                static fn (string $column): bool => Schema::hasColumn('t_Users', $column)
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('t_ModelRoles_old');
    }

    public function down(): void
    {
        Schema::table('t_Users', function (Blueprint $table): void {
            if (! Schema::hasColumn('t_Users', 'Linked')) {
                $table->boolean('Linked')->default(false);
            }
            if (! Schema::hasColumn('t_Users', 'ClientID')) {
                $table->string('ClientID')->nullable()->index();
            }
        });
    }
};

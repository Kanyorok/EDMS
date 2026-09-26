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
        Schema::create('t_Countries', static function (Blueprint $table) {
            $table->id('Id');
            $table->string('Name')->unique();
            $table->string('CountryCode', 2)->unique()->comment('ISO 3166-1 alpha-3');
            $table->string('PhoneCode', 4)->nullable();
            $table->string('Flag')->nullable();
            $table->foreignId('CurrencyId')->constrained('t_Currencies', 'Id');
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');
        });

        Schema::table('t_Localities', static function (Blueprint $table) {
            $table->foreignId('CountryId')->nullable()->constrained('t_Countries', 'Id');
            $table->string('LocationType', 100)->change();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_Localities', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('CountryId');
            $table->string('LocationType', 10)->change();
        });

        Schema::dropIfExists('t_Countries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * External systems (core banking today, others later) that push documents into
     * the DMS and read them back. Each one only ever sees its own events, and every
     * document it uploads is attributed to ServiceUserId, with the person who acted
     * in the external system recorded alongside as an attribute.
     */
    public function up(): void
    {
        Schema::create('t_DMSSourceSystems', static function (Blueprint $table) {
            $table->id('Id');
            $table->string('Code', 50)->unique();
            $table->string('Name', 150);
            $table->string('Description', 255)->nullable();
            $table->foreignId('ServiceUserId')->constrained('t_Users', 'Id');
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
        Schema::dropIfExists('t_DMSSourceSystems');
    }
};

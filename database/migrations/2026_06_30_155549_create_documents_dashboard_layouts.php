<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_DocumentDashboardLayouts', function (Blueprint $table) {
            $table->id('Id');
            $table->string('WidgetKey')->unique();
            $table->unsignedInteger('x')->default(0);
            $table->unsignedInteger('y')->default(0);
            $table->unsignedSmallInteger('w')->default(6);
            $table->unsignedSmallInteger('h')->default(1);
            $table->unsignedInteger('SortOrder')->default(0);
            $table->json('Config')->nullable();
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');
        });
    }

    public function down()
    {
        Schema::dropIfExists('t_DocumentDashboardLayouts');
    }
};

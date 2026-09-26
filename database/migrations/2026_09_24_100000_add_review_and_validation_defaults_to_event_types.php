<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Who reviews, and what validates, a document an external system asks about.
     *
     * An event names a default reviewer - one user OR one team - and a validation type. A
     * review can therefore be assigned to a team: ReviewerId is then empty until a member
     * completes the review (it is set to whoever did), and ReviewerTeamId says whose queue
     * it sits in.
     */
    public function up(): void
    {
        Schema::table('t_DMSEventTypes', function (Blueprint $table) {
            $table->foreignId('ReviewerUserId')->nullable()->after('AllowReplace')->constrained('t_Users', 'Id');
            $table->foreignId('ReviewerTeamId')->nullable()->after('ReviewerUserId')->constrained('t_Teams', 'TeamID');
            $table->foreignId('ValidationTypeId')->nullable()->after('ReviewerTeamId')->constrained('t_DocumentValidationTypes', 'Id');
        });

        Schema::table('t_DocumentReviews', function (Blueprint $table) {
            $table->foreignId('ReviewerTeamId')->nullable()->after('ReviewerId')->constrained('t_Teams', 'TeamID');
        });

        // Widening only: every existing row already has a reviewer.
        DB::statement('ALTER TABLE t_DocumentReviews ALTER COLUMN ReviewerId BIGINT NULL');
    }

    public function down(): void
    {
        // Only safe while no team-assigned review exists; those rows have no ReviewerId.
        DB::table('t_DocumentReviews')->whereNull('ReviewerId')->update(['ReviewerId' => DB::raw('CreatedBy')]);
        DB::statement('ALTER TABLE t_DocumentReviews ALTER COLUMN ReviewerId BIGINT NOT NULL');

        Schema::table('t_DocumentReviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ReviewerTeamId');
        });

        Schema::table('t_DMSEventTypes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ValidationTypeId');
            $table->dropConstrainedForeignId('ReviewerTeamId');
            $table->dropConstrainedForeignId('ReviewerUserId');
        });
    }
};

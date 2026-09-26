<?php

use App\Helpers\SystemHelper;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
//         Schema::table('t_Teams', function (Blueprint $table) {
//             $table->string('TeamSlug')->nullable()->default(null)->after('Name');
//             $table->json('Extra')->nullable()->default(null)->after('TeamSlug');
//         });

        $usedSlugs = [];
        foreach (Team::withTrashed()->select('TeamID', 'Name')->get() as $team) {
            $slug = (string) Str::of($team->Name)->trim()->slug();
            if (isset($usedSlugs[$slug])) {
                $slug = $slug . '-' . $team->TeamID;
            }
            $usedSlugs[$slug] = true;
            DB::table('t_Teams')->where('TeamID', $team->TeamID)
                ->update(['TeamSlug' => $slug]);
        }
        DB::statement('DROP INDEX IF EXISTS UQ_t_Teams_TeamSlug ON t_Teams');

//         DB::statement('ALTER TABLE t_Teams ALTER COLUMN TeamSlug NVARCHAR(255) NOT NULL');
//         DB::statement('CREATE UNIQUE INDEX UQ_t_Teams_TeamSlug ON t_Teams(TeamSlug)');

//         if (User::query()->exists()) {//FOSA
//             Artisan::call('app:create-recovery-team-command');
//             $user = SystemHelper::user();
//             $date = now();
//             //create for BOSA,Micro Credit & Diaspora
//             $collect = collect(['BOSA', 'Micro Credit', 'Diaspora']);

//             foreach ($collect as $item) {
//                 $slug = Str::of($item)->prepend('sys-recovery-')->slug();
//                 if (!Team::query()->where('TeamSlug', $slug)->exists()) {
//                     DB::table('t_Teams')->insert([
//                         'TeamSlug' => $slug,
//                         'Name' => $item . ' Recovery Team',
//                         'Email' => config('org.email'),
//                         'Extra' => json_encode(['recovery' => true, 'system' => true, 'branch' => '00'], JSON_THROW_ON_ERROR),
//                         'CreatedOn' => $date,
//                         'CreatedBy' => $user->Id,
//                         'ModifiedOn' => $date,
//                         'ModifiedBy' => $user->Id,
//                     ]);
//                 }
//             }
//         }
    }

//     /**
//      * Reverse the migrations.
//      */
//     public function down(): void
//     {
//         DB::statement('DROP INDEX IF EXISTS UQ_t_Teams_TeamSlug ON t_Teams');

//         Schema::table('t_Teams', function (Blueprint $table) {
//             $table->dropColumn(['TeamSlug', 'Extra']);
//         });
//     }
};

<?php

namespace Database\Seeders;

use App\Helpers\SystemHelper;
use Illuminate\Database\Seeder;

use App\Models\Auth\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = SystemHelper::user();
       // $brUser = SystemHelper::brUser();

            User::updateOrCreate([
                'UserID' => $user->UserID,
            ], [
                'Name' => $user->Name,
                'Email' => $user->Email ?? 'admin@test.co.ke',
                'Phone' => $user->Phone ?? '0700100100',
                'Password' => $user->Password,
                'BranchId' => $user->BranchId,
                'CreatedOn' => now(),
                'CreatedBy' => $user->Id,
                'ModifiedOn' => now(),
                'ModifiedBy' => $user->Id,

            ]);
    }
    
}

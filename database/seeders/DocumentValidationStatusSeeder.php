<?php

namespace Database\Seeders;

use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Helpers\SystemHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentValidationStatusSeeder extends Seeder
{
    public function run(): void
    {
        $actorId = SystemHelper::user()->Id;
        foreach (DocumentValidationStatusEnum::cases() as $order => $status) {
            DB::table('t_CodeDetails')->updateOrInsert(
                ['CodeID' => 'DocumentValidationStatus', 'Value' => $status->value],
                [
                    'Description' => $status->description(),
                    'DisplayOrder' => $order + 1,
                    'IsActive' => true,
                    'CreatedBy' => $actorId,
                    'CreatedOn' => now(),
                    'ModifiedBy' => $actorId,
                    'ModifiedOn' => now(),
                    'DeletedBy' => null,
                    'DeletedOn' => null,
                ]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\Core\VisibilityEnum;
use App\Helpers\SystemHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DmsRegistrySeeder extends Seeder
{
    /**
     * Creates the head office registry, the root every other registry hangs
     * off. Branch registries are added from the UI and point back at this one
     * through ParentRegistryId.
     *
     * Must run after CodeDetailSeeder, which supplies the RegistryStatusId
     * bucket, and after BranchSeeder and UserSeeder for the two FKs.
     */
    public function run(): void
    {
        $actor = SystemHelper::user();
        $now = now();

        DB::table('t_CodeDetails')->updateOrInsert(
            ['CodeID' => 'RegistryStatusId', 'Value' => 'active'],
            [
                'Description' => 'Active',
                'DisplayOrder' => 1,
                'IsActive' => 1,
                'CreatedBy' => $actor->Id,
                'CreatedOn' => $now,
                'ModifiedBy' => $actor->Id,
                'ModifiedOn' => $now,
                'DeletedBy' => null,
                'DeletedOn' => null,
            ]
        );

        $exists = DB::table('t_DMSRegistries')
            ->where('Code', 'HQ-REG')
            ->whereNull('DeletedOn')
            ->exists();

        if ($exists) {
            $this->command->info('HQ registry already present, leaving it untouched.');

            return;
        }

        DB::table('t_DMSRegistries')->insert([
            'Code' => 'HQ-REG',
            'Name' => 'Head Office Registry',
            'Description' => 'Central registry custodying head office physical records.',

            // The root of the registry tree.
            'ParentRegistryId' => null,
            'BranchId' => $this->headOfficeBranchId(),

            // Placeholder custodian: the real officer is assigned from
            // Settings once staff records exist. An 'a' grant on this registry
            // is what lets anyone else act in that role meanwhile.
            'HodUserId' => $actor->Id,

            'IsArchive' => 0,
            'RegistryStatusId' => $this->activeStatusId(),

            // Staff may see that the registry exists; its contents are still
            // governed by grants on the locations inside it.
            'Visibility' => VisibilityEnum::Public->value,

            'CreatedBy' => $actor->Id,
            'CreatedOn' => $now,
            'ModifiedBy' => $actor->Id,
            'ModifiedOn' => $now,
        ]);
    }

    /**
     * BranchId is nullable, so a missing head office branch is not fatal.
     */
    private function headOfficeBranchId(): ?int
    {
        $branchId = DB::table('t_Branches')
            ->where('IsHQ', true)
            ->whereNull('DeletedOn')
            ->value('Id')
            ?? DB::table('t_Branches')
                ->where('BranchID', '000')
                ->whereNull('DeletedOn')
                ->value('Id');

        return $branchId !== null ? (int) $branchId : null;
    }

    /**
     * RegistryStatusId is NOT NULL, so a missing bucket is fatal and worth
     * saying so plainly rather than failing on a constraint violation.
     */
    private function activeStatusId(): int
    {
        $statusId = DB::table('t_CodeDetails')
            ->where('CodeID', 'RegistryStatusId')
            ->where('Value', 'active')
            ->whereNull('DeletedOn')
            ->value('ID');

        if ($statusId === null) {
            throw new RuntimeException(
                'RegistryStatusId "active" is missing. Run CodeDetailSeeder first.'
            );
        }

        return (int) $statusId;
    }
}

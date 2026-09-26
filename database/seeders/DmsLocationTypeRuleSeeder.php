<?php

namespace Database\Seeders;

use App\Helpers\SystemHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DmsLocationTypeRuleSeeder extends Seeder
{
    /**
     * Seeds the legal nesting arrangements for a registry's shelving tree.
     *
     * Cabinets sit in the registry, shelves and boxes in a cabinet, boxes and
     * trays on a shelf, trays in a box. A null parent means the child type may
     * stand directly in the registry.
     *
     * The LocationTypeId bucket these reference is seeded by CodeDetailSeeder,
     * which must run first. Idempotent on (ParentTypeId, ChildTypeId).
     */
    public function run(): void
    {
        $actor = SystemHelper::user();
        $now = now();

        foreach ([
            'cabinet' => 'Cabinet',
            'shelf' => 'Shelf',
            'box' => 'Box',
            'tray' => 'Tray',
            'compactor' => 'Compactor',
            'bay' => 'Bay',
        ] as $value => $description) {
            DB::table('t_CodeDetails')->updateOrInsert(
                ['CodeID' => 'LocationTypeId', 'Value' => $value],
                [
                    'Description' => $description,
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
        }

        $typeIds = DB::table('t_CodeDetails')
            ->where('CodeID', 'LocationTypeId')
            ->whereNull('DeletedOn')
            ->pluck('ID', 'Value');

        $pairs = [
            [null, 'cabinet'],
            ['cabinet', 'shelf'],
            ['cabinet', 'box'],
            ['shelf', 'box'],
            ['shelf', 'tray'],
            ['box', 'tray'],
            [null, 'compactor'],
            [null, 'bay'],
            ['compactor', 'bay'],
            ['bay', 'cabinet'],
            ['bay', 'shelf'],
        ];

        foreach ($pairs as [$parent, $child]) {
            $parentId = $parent !== null ? $this->typeId($typeIds, $parent) : null;
            $childId = $this->typeId($typeIds, $child);

            $existing = DB::table('t_DMSLocationTypeRules')
                ->where('ChildTypeId', $childId)
                ->when(
                    $parentId === null,
                    static fn ($q) => $q->whereNull('ParentTypeId'),
                    static fn ($q) => $q->where('ParentTypeId', $parentId)
                )
                ->first();

            if ($existing) {
                DB::table('t_DMSLocationTypeRules')
                    ->where('Id', $existing->Id)
                    ->update([
                        'IsActive' => 1,
                        'ModifiedBy' => $actor->Id,
                        'ModifiedOn' => $now,
                        'DeletedBy' => null,
                        'DeletedOn' => null,
                    ]);

                continue;
            }

            DB::table('t_DMSLocationTypeRules')->insert([
                'ParentTypeId' => $parentId,
                'ChildTypeId' => $childId,
                'IsActive' => 1,
                'CreatedBy' => $actor->Id,
                'CreatedOn' => $now,
                'ModifiedBy' => $actor->Id,
                'ModifiedOn' => $now,
            ]);
        }
    }

    /**
     * The child column is a NOT NULL FK, so a missing type is fatal and worth
     * naming plainly rather than failing on a constraint violation.
     */
    private function typeId(mixed $typeIds, string $value): int
    {
        if (! isset($typeIds[$value])) {
            throw new RuntimeException(
                "LocationTypeId \"{$value}\" is missing. Run CodeDetailSeeder first."
            );
        }

        return (int) $typeIds[$value];
    }
}

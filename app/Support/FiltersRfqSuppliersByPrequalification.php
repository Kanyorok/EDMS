<?php

namespace App\Support;

use App\Models\Procurement\RFQ;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait FiltersRfqSuppliersByPrequalification
{
    protected function eligibleSupplierMasterIdsForRfq(RFQ $rfq): Collection
    {
        return $this->qualifiedSupplierRowsForRfq($rfq)
            ->pluck('SupplierMasterId')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    protected function qualifiedSupplierRowsForRfq(RFQ $rfq, array|Collection $supplierMasterIds = []): Collection
    {
        $supplierCategoryIds = $this->supplierCategoryIdsForRfq($rfq);

        if ($supplierCategoryIds->isEmpty()) {
            return collect();
        }

        $supplierMasterIds = collect($supplierMasterIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $query = DB::table('t_Suppliers as s')
            ->join('t_SupplierMaster as sm', 's.SupplierMasterId', '=', 'sm.Id')
            ->whereNull('s.DeletedOn')
            ->whereNull('sm.DeletedOn')
            ->where('s.Active_Status', 1)
            ->where('sm.ApprovalStatus', 'A')
            ->where('sm.IsPrequalified', 1)
            ->where(function ($query) use ($supplierCategoryIds) {
                $query->whereIn('s.CategoryId', $supplierCategoryIds)
                    ->orWhereIn('s.SupplierCategoryID', $supplierCategoryIds);
            });

        if ($supplierMasterIds->isNotEmpty()) {
            $query->whereIn('s.SupplierMasterId', $supplierMasterIds);
        }

        return $query
            ->select('s.Id', 's.SupplierMasterId', 's.CategoryId', 's.SupplierCategoryID')
            ->orderBy('s.SupplierMasterId')
            ->orderBy('s.Id')
            ->get()
            ->unique('SupplierMasterId')
            ->values();
    }

    protected function unqualifiedSupplierMasterIdsForRfq(RFQ $rfq, array|Collection $supplierMasterIds): Collection
    {
        $supplierMasterIds = collect($supplierMasterIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($supplierMasterIds->isEmpty()) {
            return collect();
        }

        $qualifiedIds = $this->qualifiedSupplierRowsForRfq($rfq, $supplierMasterIds)
            ->pluck('SupplierMasterId')
            ->map(fn ($id) => (int) $id)
            ->unique();

        return $supplierMasterIds->diff($qualifiedIds)->values();
    }

    protected function supplierCategoryIdsForRfq(RFQ $rfq): Collection
    {
        $itemCategoryIds = $this->rfqItemCategoryIds($rfq);

        if ($itemCategoryIds->isEmpty()) {
            return collect();
        }

        // Supplier eligibility is category-specific. Never build a union of
        // suppliers for a legacy/malformed RFQ containing multiple categories.
        if ($itemCategoryIds->count() !== 1) {
            return collect();
        }

        $supplierCategoryIds = DB::table('t_SupplierCategory_ItemCategory')
            ->whereIn('ItemCategoryID', $itemCategoryIds)
            ->whereNull('DeletedOn')
            ->pluck('SupplierCategoryID')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($supplierCategoryIds->isNotEmpty()) {
            return $supplierCategoryIds;
        }

        $parentItemCategoryIds = $this->parentItemCategoryIds($itemCategoryIds);

        if ($parentItemCategoryIds->isEmpty()) {
            return collect();
        }

        return DB::table('t_SupplierCategory_ItemCategory')
            ->whereIn('ItemCategoryID', $parentItemCategoryIds)
            ->whereNull('DeletedOn')
            ->pluck('SupplierCategoryID')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    protected function rfqItemCategoryIds(RFQ $rfq): Collection
    {
        return DB::table('t_RFQLines')
            ->where('RFQId', $rfq->Id)
            ->whereNull('DeletedOn')
            ->pluck('ItemCategoryId')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    protected function parentItemCategoryIds(Collection $itemCategoryIds): Collection
    {
        $parents = collect();
        $frontier = $itemCategoryIds;

        while ($frontier->isNotEmpty()) {
            $nextParents = DB::table('t_ItemCategories')
                ->whereIn('Id', $frontier)
                ->pluck('ParentId')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $frontier = $nextParents->diff($parents)->values();
            $parents = $parents->merge($frontier)->unique()->values();
        }

        return $parents;
    }
}

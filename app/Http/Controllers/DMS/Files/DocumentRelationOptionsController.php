<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\HRM\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Record search behind the upload form's employee-link picker.
 * PhysicalFileController::relationOptions(), gated by plain DMS module
 * access instead of registry-management permission - any user uploading a
 * document may link it to a record, not just registry managers.
 */
class DocumentRelationOptionsController extends Controller
{
    public const array RELATION_TYPES = [
        'hr_employees' => 'Employee',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::RELATION_TYPES))],
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $search = trim($validated['q']);

        $options = match ($validated['type']) {
            'hr_employees' => Employee::query()
                ->where(fn ($q) => $q->where('EmployeeID', 'like', "%{$search}%")
                    ->orWhere('FirstName', 'like', "%{$search}%")
                    ->orWhere('LastName', 'like', "%{$search}%"))
                ->limit(15)->get(['Id', 'EmployeeID', 'FirstName', 'LastName'])
                ->map(fn ($item) => [
                    'id' => (string) $item->Id,
                    'text' => $item->EmployeeID . ' - ' . trim($item->FirstName . ' ' . $item->LastName),
                ]),
        };

        return response()->json(['results' => $options->values()]);
    }
}

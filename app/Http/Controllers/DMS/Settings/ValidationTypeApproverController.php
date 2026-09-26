<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Exceptions\ErroredException;
use App\Helpers\SystemHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\ValidationTypeShareRequest;
use App\Models\Core\SpecialPermission;
use App\Models\DMS\DocumentValidationType;
use App\Services\DMS\Verification\ValidationTypeService;
use App\Traits\Controller\SpecialPermissionTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ValidationTypeApproverController extends Controller
{
    use SpecialPermissionTrait;

    public function __construct()
    {
        $this->middleware('ajax');
    }

    public function index(Request $request, DocumentValidationType $documentValidationType): JsonResponse
    {
        $this->authorize('view', $documentValidationType);

        $canDelete = $request->user()->can('admin', $documentValidationType);

        return $this->permissions($documentValidationType->permissions(), $canDelete, instructions: ['append' => ['w' => 'Validate', 'a' => '*']]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ValidationTypeShareRequest $request, DocumentValidationType $documentValidationType): JsonResponse
    {
        $this->authorize('admin', $documentValidationType);
        $approver = $request->getApprover();
        $role = $request->getRole();

        try {
            return \DB::transaction(function () use ($documentValidationType, $approver, $role, $request) {
                (new ValidationTypeService($documentValidationType))->addApprover($approver, $request->user(), $role);

                return $this->succeeded('approver added successfully');
            });
        } catch (ErroredException $e) {
            return $e->toJson();
        } catch (\Throwable $e) {
            Log::error("Error adding document validator: " . $e->getMessage());

            return $this->errored('unexpected error, try again later');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, DocumentValidationType $documentValidationType, $permission_id): JsonResponse
    {
        $this->authorize('admin', $documentValidationType);
        $specialPermission = $documentValidationType->permissions()->where('Id', $permission_id)->first();
        if (! $specialPermission instanceof SpecialPermission) {
            return $this->errored('approver not found');
        }

        try {
            return \DB::transaction(function () use ($specialPermission, $documentValidationType, $request) {
                (new ValidationTypeService($documentValidationType))->removeApprover($specialPermission, $request->user());

                return $this->succeeded('approver removed successfully');
            });
        } catch (ErroredException $e) {
            return $e->toJson();
        } catch (\Throwable $e) {
            Log::error("Error removing document type approver: " . $e->getMessage());

            return $this->errored('unexpected error, try again later');
        }
    }

    protected function _trashRoute(SpecialPermission $permission): string
    {
        return route('doc-validation-type-approvers.destroy', [$permission->model->ValidationTypeId, $permission->Id]);
    }
}

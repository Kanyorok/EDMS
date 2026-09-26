<?php

namespace App\Http\Controllers\DMS\API;

use App\Enums\Core\DataTypesEnum;
use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Helpers\SystemHelper;
use App\Http\Controllers\Controller;
use App\Models\DMS\DocumentValidation;
use App\Models\DMS\DocumentValidationType;
use App\Services\DMS\Files\FileProperties;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Intake for records captured in an external system (core banking) that need a document
 * validated in the DMS.
 *
 * A validation is created here with no DocumentId - the document does not exist yet. It is
 * uploaded later against this record through DMS\Verification\DocumentValidationController::update,
 * which is also where the validation type's approval workflow starts, since there is nothing
 * to approve until a document is attached.
 *
 * Response casing: the api middleware group appends TransformApiResponse, which camelCases the
 * keys of successful JSON responses. The 'Fields' array built below therefore reaches the caller
 * as "fields", with "fieldName" / "item" / "itemElementName" inside it. Renaming these keys here
 * changes the wire contract, so keep them in step with the integration's expectations.
 */
class DocumentVerificationController extends Controller
{
    /**
     * Fields that route the request rather than describe the record, so they are not
     * stored as validation attributes.
     */
    private const ROUTING_FIELDS = ['validation_type'];

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'member_name' => 'required|string',
            'branch_id' => 'required|string|',
            'application_id' => 'required|string',
            'mobile_number' => 'nullable|string',
            'member_class' => 'required|string',
            'email_address' => 'nullable',
            'identification_number' => 'required|string',
            'gender' => 'required',
            'date_of_birth' => 'required|date',
            'captured_by' => 'required|string',
            'status' => 'required|string',
            'member_status' => 'nullable|string',
            'member_number' => 'required|string',
            // Optional: a t_DocumentValidationTypes.ValidationTypeId. Omitted by the
            // core-banking integration, which relies on the configured default.
            'validation_type' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // ValidationId is uniquely indexed across all types, so the duplicate check has to be
        // global rather than scoped to one type, and must see soft-deleted rows - a soft delete
        // does not release the value.
        if (
            DocumentValidation::query()->withTrashed()
                ->where('ValidationId', $validated['application_id'])
                ->exists()
        ) {
            return $this->errored('Application ID already exists.', data: ['status' => '999']);
        }

        $type = $this->resolveValidationType($validated['validation_type'] ?? null);
        if (! $type instanceof DocumentValidationType) {
            Log::error('Document verification intake could not resolve a validation type', [
                'requested' => $validated['validation_type'] ?? null,
                'configured_default' => config('dms.verification.default_validation_type'),
            ]);

            return $this->errored('Validation type is not configured.', data: ['status' => '999']);
        }

        $actor = SystemHelper::user();

        try {
            return DB::transaction(function () use ($actor, $validated, $type) {
                $validation = DocumentValidation::create([
                    'Name' => $validated['member_name'],
                    'ValidationId' => $validated['application_id'],
                    'ValidationTypeId' => $type->Id,
                    'DocumentId' => null,
                    'Status' => DocumentValidationStatusEnum::Pending,
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                ]);

                activity()->causedBy($actor)->performedOn($validation)->event('create')
                    ->log('Created document validation : ' . $validation->ValidationId);

                [$attributes, $fields] = $this->buildAttributes($validated, $validation->Id, $actor->Id);

                if ($attributes->isNotEmpty()) {
                    DB::table('t_DocumentValidationAttributes')->insert($attributes->all());
                }

                return $this->succeeded('document validation created successfully', data: [
                    'status' => '000',
                    'Fields' => $fields,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Saving document verification data failed ' . $e);
        }

        return $this->errored('an unexpected error occurred', ['status' => '999']);
    }

    /**
     * The type named by the request, else the configured default.
     */
    private function resolveValidationType(?string $validationTypeId): ?DocumentValidationType
    {
        $validationTypeId = trim((string) ($validationTypeId ?: config('dms.verification.default_validation_type')));

        if ($validationTypeId === '') {
            return null;
        }

        return DocumentValidationType::query()->where('ValidationTypeId', $validationTypeId)->first();
    }

    /**
     * Turn the posted record into t_DocumentValidationAttributes rows, and the echo of those
     * values the caller expects back under "Fields".
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function buildAttributes(array $validated, int $validationId, int $actorId): array
    {
        $dated = now();
        $attributes = collect();
        $fields = collect();

        foreach ($validated as $item => $value) {
            if (in_array($item, self::ROUTING_FIELDS, true)) {
                continue;
            }

            if (is_null($value) || strtolower((string) $value) === 'null') {
                continue;
            }

            $type = DataTypesEnum::String;
            if (Str::of($item)->contains('date')) {
                try {
                    $value = Carbon::parse($value)->format(FileProperties::DATE_TIME_FORMAT);
                    $type = DataTypesEnum::DateTime;
                } catch (Exception) {
                }
            }

            $attributes->add([
                'Name' => $item,
                'Value' => $value,
                'DataType' => $type->value,
                'DocumentValidationId' => $validationId,
                'CreatedBy' => $actorId,
                'ModifiedBy' => $actorId,
                'CreatedOn' => $dated,
                'ModifiedOn' => $dated,
            ]);

            $fields->add([
                'FieldName' => strtoupper($item),
                'Item' => $value,
                'ItemElementName' => $type->name,
            ]);
        }

        return [$attributes, $fields];
    }
}

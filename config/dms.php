<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External document verification intake
    |--------------------------------------------------------------------------
    |
    | POST /api/dms/verification/data creates a DocumentValidation for a record
    | captured in an external system (core banking), before any document exists.
    |
    | Callers may name the validation type per request with a "validation_type"
    | field carrying a t_DocumentValidationTypes.ValidationTypeId (e.g. ValType001).
    | Payloads that omit it fall back to the ValidationTypeId configured here, which
    | is what the core-banking integration does - it posts client onboarding records
    | only and has no notion of validation types.
    |
    */

    'verification' => [
        'default_validation_type' => env('DMS_DEFAULT_VALIDATION_TYPE'),
    ],

];

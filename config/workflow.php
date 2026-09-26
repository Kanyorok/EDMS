<?php

return [
    'approval_permissions' => [],
    'status_columns' => [
        'DocumentValidationId' => 'Status',
    ],
    'DocumentValidationId' => [
        'Pending' => \App\Enums\DMS\DocumentValidationStatusEnum::Pending->value,
        'Submitted for Approval' => \App\Enums\DMS\DocumentValidationStatusEnum::Submitted->value,
        'Approved' => \App\Enums\DMS\DocumentValidationStatusEnum::Approved->value,
        'Rejected' => \App\Enums\DMS\DocumentValidationStatusEnum::Rejected->value,
    ],
];

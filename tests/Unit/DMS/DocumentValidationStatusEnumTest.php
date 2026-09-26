<?php

namespace Tests\Unit\DMS;

use App\Enums\DMS\DocumentValidationStatusEnum;
use PHPUnit\Framework\TestCase;

/**
 * The workflow engine matches t_CodeDetails.Description literally.
 *
 * p_ProcessWorkflowPending RAISERRORs when 'Submitted for Approval', 'Approved' or 'Rejected'
 * are missing, and ApprovalWorkflowService::advanceToNextStage() keys the final status update on
 * the same strings, so these descriptions are a contract with the database rather than UI labels.
 * The registration migration seeds t_CodeDetails from description(); this pins those strings so a
 * well-meaning reword cannot silently break approvals.
 */
class DocumentValidationStatusEnumTest extends TestCase
{
    public function test_descriptions_match_the_workflow_engine_contract(): void
    {
        $this->assertSame('Pending', DocumentValidationStatusEnum::Pending->description());
        $this->assertSame('Submitted for Approval', DocumentValidationStatusEnum::Submitted->description());
        $this->assertSame('Approved', DocumentValidationStatusEnum::Approved->description());
        $this->assertSame('Rejected', DocumentValidationStatusEnum::Rejected->description());
    }

    public function test_values_fit_the_two_character_status_column(): void
    {
        foreach (DocumentValidationStatusEnum::cases() as $status) {
            $this->assertSame(2, strlen($status->value), "{$status->name} must fit char(2) Status");
        }
    }

    public function test_config_maps_every_status_under_the_morph_alias(): void
    {
        // config/workflow.php is keyed on the morph alias DocumentValidation::getPrimaryKey()
        // returns. Keying it on the table name instead is a silent no-op - the RFQ block does
        // exactly that and falls through to the raw CodeDetail value.
        $map = require __DIR__ . '/../../../config/workflow.php';

        $this->assertArrayHasKey('DocumentValidationId', $map);
        $this->assertSame('Status', $map['status_columns']['DocumentValidationId'] ?? null);

        foreach (DocumentValidationStatusEnum::cases() as $status) {
            $this->assertSame(
                $status->value,
                $map['DocumentValidationId'][$status->description()] ?? null,
                "config/workflow.php must map '{$status->description()}' to '{$status->value}'"
            );
        }
    }
}

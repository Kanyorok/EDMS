<?php

namespace Tests\Feature\DMS;

use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Http\Middleware\DMSAuthMiddleware;
use App\Models\DMS\DocumentValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDocumentValidationFixtures;
use Tests\TestCase;

/**
 * POST /api/v1/dms/verification/data - the external (core banking) intake that creates a
 * document validation before any document exists.
 *
 * Basic auth is exercised by DMSAuthMiddleware against a stored APICredential; these tests
 * bypass it to focus on the intake behaviour itself.
 *
 * No DatabaseTransactions - see CreatesDocumentValidationFixtures for why.
 */
class DocumentVerificationIntakeTest extends TestCase
{
    use CreatesDocumentValidationFixtures;

    private const URI = '/api/v1/dms/verification/data';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(DMSAuthMiddleware::class);
    }

    protected function tearDown(): void
    {
        $this->cleanupDocumentValidationFixtures();

        parent::tearDown();
    }

    public function test_it_creates_a_validation_against_the_configured_default_type(): void
    {
        $actor = $this->makeUser();
        $type = $this->makeValidationType($actor);
        config(['dms.verification.default_validation_type' => $type->ValidationTypeId]);

        $applicationId = 'APP-' . Str::upper(Str::random(10));

        $response = $this->postJson(self::URI, $this->payload($applicationId));

        // TransformApiResponse camelCases successful responses, so the controller's 'Fields' /
        // 'FieldName' reach the caller lowercased. Pinned here because it is the wire contract
        // the core-banking integration reads, and it is not obvious from the controller.
        $response->assertSuccessful()
            ->assertJsonPath('status', '000')
            ->assertJsonStructure(['message', 'status', 'fields' => [['fieldName', 'item', 'itemElementName']]])
            ->assertJsonPath('fields.0.fieldName', 'MEMBER_NAME');

        $validation = DocumentValidation::query()->where('ValidationId', $applicationId)->first();
        $this->assertNotNull($validation, 'the intake must create a validation');
        $this->trackValidation((int) $validation->Id);

        $this->assertSame((int) $type->Id, (int) $validation->ValidationTypeId);
        $this->assertNull($validation->DocumentId, 'no document exists yet at intake');
        $this->assertSame(DocumentValidationStatusEnum::Pending, $validation->Status);

        // The posted record is stored as attributes, and routing metadata is not one of them.
        $names = DB::table('t_DocumentValidationAttributes')
            ->where('DocumentValidationId', $validation->Id)->pluck('Name')->all();

        $this->assertContains('member_name', $names);
        $this->assertContains('identification_number', $names);
        $this->assertNotContains('validation_type', $names, 'routing fields are not record data');
    }

    public function test_payload_may_name_its_own_validation_type(): void
    {
        $actor = $this->makeUser();
        $defaultType = $this->makeValidationType($actor);
        $chosenType = $this->makeValidationType($actor);
        config(['dms.verification.default_validation_type' => $defaultType->ValidationTypeId]);

        $applicationId = 'APP-' . Str::upper(Str::random(10));

        $this->postJson(self::URI, $this->payload($applicationId) + [
            'validation_type' => $chosenType->ValidationTypeId,
        ])->assertSuccessful()->assertJsonPath('status', '000');

        $validation = DocumentValidation::query()->where('ValidationId', $applicationId)->first();
        $this->assertNotNull($validation);
        $this->trackValidation((int) $validation->Id);

        $this->assertSame(
            (int) $chosenType->Id,
            (int) $validation->ValidationTypeId,
            'an explicit validation_type must win over the configured default'
        );
    }

    public function test_duplicate_application_id_is_rejected(): void
    {
        $actor = $this->makeUser();
        $type = $this->makeValidationType($actor);
        config(['dms.verification.default_validation_type' => $type->ValidationTypeId]);

        $applicationId = 'APP-' . Str::upper(Str::random(10));

        $this->postJson(self::URI, $this->payload($applicationId))->assertSuccessful();
        $first = DocumentValidation::query()->where('ValidationId', $applicationId)->first();
        $this->trackValidation((int) $first->Id);

        // ValidationId is uniquely indexed, so this must be caught rather than hit the index.
        $this->postJson(self::URI, $this->payload($applicationId))
            ->assertJsonPath('status', '999');

        $this->assertSame(
            1,
            DocumentValidation::query()->where('ValidationId', $applicationId)->count(),
            'the second post must not create a duplicate'
        );
    }

    public function test_it_fails_cleanly_when_no_validation_type_can_be_resolved(): void
    {
        config(['dms.verification.default_validation_type' => null]);

        $applicationId = 'APP-' . Str::upper(Str::random(10));

        $this->postJson(self::URI, $this->payload($applicationId))
            ->assertJsonPath('status', '999');

        $this->assertNull(
            DocumentValidation::query()->where('ValidationId', $applicationId)->first(),
            'nothing may be created when the type is unresolvable'
        );
    }

    public function test_unknown_validation_type_is_rejected(): void
    {
        $applicationId = 'APP-' . Str::upper(Str::random(10));

        $this->postJson(self::URI, $this->payload($applicationId) + [
            'validation_type' => 'ValTypeDoesNotExist',
        ])->assertJsonPath('status', '999');

        $this->assertNull(DocumentValidation::query()->where('ValidationId', $applicationId)->first());
    }

    public function test_it_still_validates_the_payload(): void
    {
        $this->postJson(self::URI, ['member_name' => 'Only a name'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    /**
     * @return array<string,string>
     */
    private function payload(string $applicationId): array
    {
        return [
            'member_name' => 'Test Member',
            'branch_id' => '001',
            'application_id' => $applicationId,
            'mobile_number' => '0700000000',
            'member_class' => 'ORDINARY',
            'email_address' => 'member@example.test',
            'identification_number' => '12345678',
            'gender' => 'F',
            'date_of_birth' => '1990-04-11',
            'captured_by' => 'TELLER01',
            'status' => 'ACTIVE',
            'member_status' => 'NEW',
            'member_number' => 'MEM-0001',
        ];
    }
}

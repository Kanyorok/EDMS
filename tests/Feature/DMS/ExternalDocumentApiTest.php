<?php

namespace Tests\Feature\DMS;

use App\Enums\Core\DataTypesEnum;
use App\Enums\DMS\LegalHoldStatusEnum;
use App\Services\Auth\UserPasswordService;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentReview;
use App\Models\DMS\DocumentValidationType;
use App\Models\DMS\EventType;
use App\Models\DMS\LegalHold;
use App\Models\DMS\Repository;
use App\Models\DMS\SourceSystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The external-system document API end to end: catalog, upload, list, status, signed
 * content link, replace and the locks that must stop a replace.
 *
 * Runs inside a rolled-back transaction with the DMS disk faked, so nothing persists and no
 * real file is written. Every call logs in for real: a known password is put on the source
 * system's service user, exchanged for a token, and sent as a bearer token.
 */
class ExternalDocumentApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;
    private SourceSystem $system;
    private EventType $event;
    private string $base;

    private const string PASSWORD = 'S3cret!integration';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('fs');
        $this->withHeaders(['Accept' => 'application/json']);

        $this->actor = $this->makeUser();
        $this->system = $this->makeSystem($this->actor);
        $this->event = $this->makeEvent($this->system, 'ACC_ID', 1330);
        $this->base = '/api/v1/dms/external/' . $this->system->Code;

        $this->withToken($this->tokenFor($this->system, $this->actor));
    }

    /** One request per process in production; in a test the sanctum guard would otherwise keep the first caller. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    private function tokenFor(SourceSystem $system, User $user, string $password = self::PASSWORD): string
    {
        return $this->postJson('/api/v1/dms/external/' . $system->Code . '/token', ['userId' => $user->UserID, 'password' => $password])
            ->assertOk()->json('data.token');
    }

    public function test_catalog_returns_the_form_for_the_module(): void
    {
        $response = $this->getJson($this->base . '/events?module=1330')->assertOk();

        $response->assertJsonPath('data.0.code', 'ACC_ID')
            ->assertJsonPath('data.0.properties.0.name', 'cbs.class')
            ->assertJsonPath('data.0.properties.0.type', 'list');

        $this->getJson($this->base . '/events?module=9999')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_upload_list_status_link_and_content(): void
    {
        $document = $this->upload()->assertCreated()->json('data');

        $this->assertSame('ACC_ID', $document['event']['code']);
        $this->assertSame(['type' => 'ACCOUNT', 'id' => '0010000059251'], $document['entity']);
        $this->assertSame(1, $document['version']);
        $this->assertSame('active', $document['status']['state']);
        $this->assertTrue($document['status']['canReplace']);
        $this->assertSame('IDEN', collect($document['properties'])->firstWhere('name', 'cbs.class')['value']);

        $stored = Document::query()->where('DocumentId', $document['documentId'])->firstOrFail();
        $this->assertSame($this->system->Id, $stored->SourceSystemId);
        $this->assertSame('CS.OFFICER', $stored->properties()->where('Name', 'source.actor')->value('Value'));

        $this->getJson($this->base . '/documents?entityType=account&entityId=0010000059251')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.documentId', $document['documentId']);

        $link = $this->postJson($this->base . '/documents/' . $document['documentId'] . '/link', ['actor' => 'CS.OFFICER'])
            ->assertOk()->json('data.url');

        $content = $this->get($link)->assertOk();
        $this->assertSame('application/pdf', $content->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $content->getContent());
    }

    public function test_content_link_is_rejected_when_tampered_with(): void
    {
        $document = $this->upload()->assertCreated()->json('data');
        $link = $this->postJson($this->base . '/documents/' . $document['documentId'] . '/link', ['actor' => 'CS.OFFICER'])->json('data.url');

        $this->get(str_replace('CS.OFFICER', 'SOMEONE.ELSE', $link))->assertForbidden();
    }

    public function test_replace_adds_a_version(): void
    {
        $document = $this->upload()->assertCreated()->json('data');

        $replaced = $this->post($this->base . '/documents/' . $document['documentId'] . '/replace', [
            'actor' => 'CS.SUPERVISOR',
            'file' => $this->pdf('second.pdf'),
        ])->assertOk()->json('data');

        $this->assertSame(2, $replaced['version']);
    }

    public function test_legal_hold_blocks_replace_and_release_lifts_it(): void
    {
        $document = $this->upload()->assertCreated()->json('data');
        $stored = Document::query()->where('DocumentId', $document['documentId'])->firstOrFail();

        $hold = LegalHold::create([
            'Name' => 'Test hold', 'Description' => 'Test', 'Ref' => 'TH-' . Str::random(5), 'Status' => LegalHoldStatusEnum::Active->value,
            'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id,
        ]);
        $stored->holds()->attach($hold->Id, ['CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);

        $status = $this->getJson($this->base . '/documents/' . $document['documentId'])->assertOk()->json('data.status');
        $this->assertSame('legal_hold', $status['state']);
        $this->assertTrue($status['onHold']);
        $this->assertFalse($status['canReplace']);

        $this->post($this->base . '/documents/' . $document['documentId'] . '/replace', ['actor' => 'CS.OFFICER', 'file' => $this->pdf()])
            ->assertStatus(409)->assertJsonPath('reason', 'legal_hold');

        $hold->update(['Status' => LegalHoldStatusEnum::Released->value, 'ReleasedOn' => now(), 'ReleasedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);

        $status = $this->getJson($this->base . '/documents/' . $document['documentId'])->json('data.status');
        $this->assertFalse($status['onHold']);
        $this->assertNotNull($status['holdReleasedOn']);
        $this->assertTrue($status['canReplace']);
    }

    public function test_replace_can_be_switched_off_per_event(): void
    {
        $document = $this->upload()->assertCreated()->json('data');
        $this->event->update(['AllowReplace' => false, 'ModifiedBy' => $this->actor->Id]);

        $this->post($this->base . '/documents/' . $document['documentId'] . '/replace', ['actor' => 'CS.OFFICER', 'file' => $this->pdf()])
            ->assertStatus(422);
    }

    public function test_upload_is_validated_against_the_event(): void
    {
        // Missing required property.
        $this->upload(['properties' => json_encode([['name' => 'cbs.class', 'value' => 'IDEN']])])
            ->assertStatus(422)->assertJsonValidationErrors(['properties.cbs.received_by']);

        // List value outside its options.
        $this->upload(['properties' => $this->properties(['cbs.class' => 'NOPE'])])
            ->assertStatus(422)->assertJsonValidationErrors(['properties.cbs.class']);

        // Property the event does not define.
        $this->upload(['properties' => $this->properties(['cbs.surprise' => 'x'])])
            ->assertStatus(422)->assertJsonValidationErrors(['properties.cbs.surprise']);

        // Not one of the document type's allowed extensions.
        $this->upload(['file' => UploadedFile::fake()->createWithContent('id.exe', 'MZ')])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        // Event not offered from that module.
        $this->upload(['module' => 1094])->assertStatus(422)->assertJsonValidationErrors(['event']);

        $this->assertSame(0, Document::query()->where('SourceSystemId', $this->system->Id)->count());
    }

    public function test_a_type_written_with_jpg_still_accepts_jpeg_and_pdf_but_nothing_else(): void
    {
        // The seeded types say "jpg", which the extensions enum only knows as "jpeg"; that used to be a 500.
        $this->event->documentTypes()->first()->update(['AllowedExtensions' => 'pdf,jpg,png', 'ModifiedBy' => $this->actor->Id]);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');

        $this->upload(['file' => UploadedFile::fake()->createWithContent('photo.jpg', $jpeg)])->assertCreated();
        $this->upload(['file' => $this->pdf()])->assertCreated();
        $this->upload(['file' => UploadedFile::fake()->createWithContent('run.exe', 'MZ')])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        // A type whose extensions are all unknown allows nothing, not everything.
        $this->event->documentTypes()->first()->update(['AllowedExtensions' => 'xyz', 'ModifiedBy' => $this->actor->Id]);
        $this->upload(['file' => $this->pdf()])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_an_event_offers_several_document_types_and_the_chosen_one_decides_the_rules(): void
    {
        $second = $this->addSecondType();
        $first = $this->event->documentTypes()->where('t_DMSDocumentTypes.Id', '!=', $second->Id)->firstOrFail();

        // The catalog lists each with its own rules.
        $types = collect($this->getJson($this->base . '/events?module=1330')->assertOk()->json('data.0.documentTypes'))->keyBy('code');
        $this->assertSame(['pdf'], $types[$first->Code]['allowedExtensions']);
        $this->assertFalse($types[$first->Code]['expiryRequired']);
        $this->assertSame(['png'], $types[$second->Code]['allowedExtensions']);
        $this->assertTrue($types[$second->Code]['expiryRequired']);

        // With more than one, the caller must say which; an unknown code is not accepted either.
        $this->upload()->assertStatus(422)->assertJsonValidationErrors(['documentType']);
        $this->upload(['documentType' => 'NOPE'])->assertStatus(422)->assertJsonValidationErrors(['documentType']);

        // The first type: PDF only, no expiry needed.
        $filed = $this->upload(['documentType' => $first->Code])->assertCreated()->json('data');
        $this->assertSame($first->Code, $filed['documentType']['code']);
        $this->assertEquals($first->Id, Document::query()->where('DocumentId', $filed['documentId'])->value('DocumentTypeId'));
        $this->upload(['documentType' => $first->Code, 'file' => $this->png()])->assertStatus(422)->assertJsonValidationErrors(['file']);

        // The second type: PNG only, and it needs an expiry date.
        $this->upload(['documentType' => $second->Code, 'file' => $this->png()])->assertStatus(422)->assertJsonValidationErrors(['expiryDate']);
        $this->upload(['documentType' => $second->Code, 'file' => $this->png(), 'expiryDate' => '2030-01-31'])->assertCreated()
            ->assertJsonPath('data.documentType.code', $second->Code);
        $this->upload(['documentType' => $second->Code, 'file' => $this->pdf(), 'expiryDate' => '2030-01-31'])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_a_single_document_type_need_not_be_named(): void
    {
        $this->upload()->assertCreated();
    }

    public function test_deactivated_document_types_drop_out_of_the_choice(): void
    {
        $second = $this->addSecondType();

        $second->update(['IsActive' => false, 'ModifiedBy' => $this->actor->Id]);
        $codes = collect($this->getJson($this->base . '/events?module=1330')->json('data.0.documentTypes'))->pluck('code');
        $this->assertFalse($codes->contains($second->Code));
        $this->assertCount(1, $codes);

        // The remaining one is now the only choice, so naming it is optional again; the switched-off one is refused.
        $this->upload()->assertCreated();
        $this->upload(['documentType' => $second->Code, 'file' => $this->png(), 'expiryDate' => '2030-01-31'])->assertStatus(422)->assertJsonValidationErrors(['documentType']);

        // With every type off the event has nothing to offer and leaves the catalog.
        $this->event->documentTypes()->update(['IsActive' => false]);
        $this->getJson($this->base . '/events?module=1330')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_replacing_a_file_uses_the_rules_of_the_type_it_was_filed_under(): void
    {
        $second = $this->addSecondType();
        $first = $this->event->documentTypes()->where('t_DMSDocumentTypes.Id', '!=', $second->Id)->firstOrFail();

        $onFirst = $this->upload(['documentType' => $first->Code])->assertCreated()->json('data.documentId');
        $onSecond = $this->upload(['documentType' => $second->Code, 'file' => $this->png(), 'expiryDate' => '2030-01-31'])->assertCreated()->json('data.documentId');

        // A PNG is fine for the document filed as the second type, and not for the first.
        $this->post($this->base . '/documents/' . $onFirst . '/replace', ['actor' => 'CS.OFFICER', 'file' => $this->png()])->assertStatus(422);
        $this->post($this->base . '/documents/' . $onSecond . '/replace', ['actor' => 'CS.OFFICER', 'file' => $this->png()])->assertOk()->assertJsonPath('data.version', 2);
    }

    public function test_the_listing_says_which_document_each_file_is(): void
    {
        $filed = $this->upload()->assertCreated()->json('data');

        $listed = $this->getJson($this->base . '/documents?entityType=ACCOUNT&entityId=0010000059251')->assertOk()->json('data.0');
        $this->assertSame($filed['documentType']['code'], $listed['documentType']['code']);
        $this->assertSame('Test type', $listed['documentType']['name']);
    }

    public function test_a_system_cannot_reach_another_systems_documents(): void
    {
        $document = $this->upload()->assertCreated()->json('data');
        $otherUser = $this->makeUser();
        $other = $this->makeSystem($otherUser);
        $otherToken = $this->tokenFor($other, $otherUser);

        $this->withToken($otherToken)->getJson('/api/v1/dms/external/' . $other->Code . '/documents/' . $document['documentId'])->assertNotFound();
        $this->withToken($otherToken)->getJson('/api/v1/dms/external/' . $other->Code . '/documents?entityType=ACCOUNT&entityId=0010000059251')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_calls_without_a_valid_token_are_refused(): void
    {
        $this->withToken('')->getJson($this->base . '/events?module=1330')->assertUnauthorized();
        $this->withToken('not-a-real-token')->getJson($this->base . '/events?module=1330')->assertUnauthorized();

        // Unknown and inactive systems read exactly like a bad token: no hint which codes exist.
        $this->getJson('/api/v1/dms/external/NOSUCH/events?module=1330')->assertUnauthorized();
        $this->system->update(['IsActive' => false, 'ModifiedBy' => $this->actor->Id]);
        $this->getJson($this->base . '/events?module=1330')->assertUnauthorized();
    }

    public function test_a_token_is_only_good_for_the_system_it_was_issued_for(): void
    {
        $sameUser = $this->makeSystem($this->actor);
        $this->makeEvent($sameUser, 'OTHER_EVT', 1330);

        // Same service user, but the token says which system it was issued for.
        $this->getJson('/api/v1/dms/external/' . $sameUser->Code . '/events?module=1330')->assertUnauthorized();
    }

    public function test_a_token_without_the_dms_ability_or_past_its_expiry_is_refused(): void
    {
        $wrongAbility = $this->actor->createToken('dms-external:' . $this->system->Code, ['something:else'])->plainTextToken;
        $this->withToken($wrongAbility)->getJson($this->base . '/events?module=1330')->assertUnauthorized();

        $expired = $this->actor->createToken('dms-external:' . $this->system->Code, ['dms:external'], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson($this->base . '/events?module=1330')->assertUnauthorized();
    }

    public function test_a_token_stops_working_when_the_service_user_is_deactivated_or_changed(): void
    {
        $this->getJson($this->base . '/events?module=1330')->assertOk();

        $this->actor->forceFill(['IsActive' => false])->save();
        $this->getJson($this->base . '/events?module=1330')->assertUnauthorized();

        $this->actor->forceFill(['IsActive' => true])->save();
        $this->getJson($this->base . '/events?module=1330')->assertOk();

        // Point the system at a different service user: the old token no longer matches it.
        $this->system->update(['ServiceUserId' => $this->makeUser()->Id, 'ModifiedBy' => $this->actor->Id]);
        $this->getJson($this->base . '/events?module=1330')->assertUnauthorized();
    }

    public function test_token_login_only_accepts_the_systems_service_user_with_the_right_password(): void
    {
        $url = $this->base . '/token';
        $stranger = $this->makeUser();

        $this->withToken('')->postJson($url, ['userId' => $this->actor->UserID, 'password' => 'wrong'])->assertUnauthorized();
        // A perfectly valid account, but not this system's service user.
        $this->withToken('')->postJson($url, ['userId' => $stranger->UserID, 'password' => self::PASSWORD])->assertUnauthorized();
        $this->withToken('')->postJson('/api/v1/dms/external/NOSUCH/token', ['userId' => $this->actor->UserID, 'password' => self::PASSWORD])->assertUnauthorized();
        $this->withToken('')->postJson($url, ['userId' => $this->actor->UserID])->assertStatus(422);

        $response = $this->withToken('')->postJson($url, ['userId' => strtolower($this->actor->UserID), 'password' => self::PASSWORD])->assertOk();
        $this->assertSame('Bearer', $response->json('data.tokenType'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($response->json('data.expiresAt'), false), 1);

        // The response never echoes the password back.
        $this->assertStringNotContainsString(self::PASSWORD, $response->getContent());
    }

    public function test_review_goes_to_the_events_default_reviewer(): void
    {
        $reviewer = $this->makeUser();
        $this->event->update(['ReviewerUserId' => $reviewer->Id, 'ModifiedBy' => $this->actor->Id]);
        $document = $this->upload()->assertCreated()->json('data');

        $status = $this->postJson($this->base . '/documents/' . $document['documentId'] . '/review', ['actor' => 'CS.OFFICER', 'note' => 'please check the stamp'])
            ->assertCreated()->json('data.status');

        $this->assertSame('under_review', $status['state']);
        $this->assertSame('pending', $status['review']['status']);
        $this->assertSame($reviewer->Name, $status['review']['reviewer']);

        $review = DocumentReview::query()->latest('Id')->firstOrFail();
        $this->assertSame($reviewer->Id, $review->ReviewerId);
        $this->assertNull($review->ReviewerTeamId);
        $this->assertSame($this->system->ServiceUserId, $review->CreatedBy);
        $this->assertStringContainsString('CS.OFFICER', $review->RequestNote);

        // One review at a time, and while it is out the file cannot change.
        $this->postJson($this->base . '/documents/' . $document['documentId'] . '/review', ['actor' => 'CS.OFFICER'])
            ->assertStatus(409)->assertJsonPath('reason', 'under_review');
        $this->post($this->base . '/documents/' . $document['documentId'] . '/replace', ['actor' => 'CS.OFFICER', 'file' => $this->pdf()])
            ->assertStatus(409)->assertJsonPath('reason', 'under_review');
    }

    public function test_review_can_be_assigned_to_a_team_and_any_member_completes_it(): void
    {
        $member = $this->makeUser();
        $outsider = $this->makeUser();
        $team = $this->makeTeam([$member]);
        $this->event->update(['ReviewerTeamId' => $team->TeamID, 'ModifiedBy' => $this->actor->Id]);
        $document = $this->upload()->assertCreated()->json('data');

        $this->postJson($this->base . '/documents/' . $document['documentId'] . '/review', ['actor' => 'CS.OFFICER'])->assertCreated();

        $review = DocumentReview::query()->latest('Id')->firstOrFail();
        $this->assertNull($review->ReviewerId);
        $this->assertSame($team->TeamID, $review->ReviewerTeamId);
        $this->assertTrue($review->isReviewer($member));
        $this->assertFalse($review->isReviewer($outsider));
        $this->assertTrue($member->can('update', $review));
        $this->assertFalse($outsider->can('update', $review));

        // Completing it records who did, and must not check the file out to the service account.
        // The controller action is called directly: the DMS routes sit behind module-permission
        // middleware that a throwaway user does not hold, and that is not what is under test.
        $this->actingAs($member);
        $request = \Illuminate\Http\Request::create('/', 'PUT', ['ReviewComment' => 'looks fine']);
        $request->setUserResolver(fn () => $member);
        $response = app(\App\Http\Controllers\DMS\Review\DocumentReviewController::class)->update($request, $review);
        $this->assertTrue($response->isSuccessful(), $response->getContent());

        $review->refresh();
        $this->assertSame($member->Id, $review->ReviewerId);
        $this->assertTrue($review->isReviewed());

        $status = $this->getJson($this->base . '/documents/' . $document['documentId'])->json('data.status');
        $this->assertFalse($status['checkedOut']);
        $this->assertSame('active', $status['state']);
        $this->assertSame('reviewed', $status['review']['status']);
        $this->assertSame('looks fine', $status['review']['comment']);
        $this->assertTrue($status['canReplace']);
        // Still a reviewer of the round once someone else completed it.
        $this->assertTrue($review->isReviewer($member));
    }

    public function test_review_needs_a_reviewer_and_accepts_an_override(): void
    {
        $document = $this->upload()->assertCreated()->json('data');
        $url = $this->base . '/documents/' . $document['documentId'] . '/review';

        $this->postJson($url, ['actor' => 'CS.OFFICER'])->assertStatus(422);

        $override = $this->makeUser();
        $this->postJson($url, ['actor' => 'CS.OFFICER', 'reviewer' => strtolower($override->UserID)])->assertCreated();
        $this->assertSame($override->Id, DocumentReview::query()->latest('Id')->value('ReviewerId'));
    }

    public function test_review_rejects_unknown_or_self_reviewer(): void
    {
        $document = $this->upload()->assertCreated()->json('data');
        $url = $this->base . '/documents/' . $document['documentId'] . '/review';

        $this->postJson($url, ['actor' => 'CS.OFFICER', 'reviewer' => 'NOBODY.HERE'])->assertStatus(422)->assertJsonValidationErrors(['reviewer']);
        $this->postJson($url, ['actor' => 'CS.OFFICER', 'reviewer' => $this->actor->UserID])->assertStatus(422)->assertJsonValidationErrors(['reviewer']);
    }

    public function test_legal_hold_blocks_review_and_validation_requests(): void
    {
        $reviewer = $this->makeUser();
        $type = $this->makeValidationType();
        $this->event->update(['ReviewerUserId' => $reviewer->Id, 'ValidationTypeId' => $type->Id, 'ModifiedBy' => $this->actor->Id]);
        $document = $this->upload()->assertCreated()->json('data');
        $this->placeHold(Document::query()->where('DocumentId', $document['documentId'])->firstOrFail());
        $reviewsBefore = DocumentReview::query()->count();

        $this->postJson($this->base . '/documents/' . $document['documentId'] . '/review', ['actor' => 'CS.OFFICER'])
            ->assertStatus(409)->assertJsonPath('reason', 'legal_hold');
        $this->postJson($this->base . '/documents/' . $document['documentId'] . '/validation', ['actor' => 'CS.OFFICER'])
            ->assertStatus(409)->assertJsonPath('reason', 'legal_hold');

        $this->assertSame($reviewsBefore, DocumentReview::query()->count());
    }

    public function test_validation_uses_the_events_type_and_locks_the_file_while_pending(): void
    {
        $type = $this->makeValidationType();
        $this->event->update(['ValidationTypeId' => $type->Id, 'ModifiedBy' => $this->actor->Id]);
        $document = $this->upload()->assertCreated()->json('data');

        $catalog = $this->getJson($this->base . '/events?module=1330')->json('data.0');
        $this->assertTrue($catalog['validation']['available']);

        $status = $this->postJson($this->base . '/documents/' . $document['documentId'] . '/validation', ['actor' => 'CS.OFFICER', 'note' => 'KYC refresh'])
            ->assertCreated()->json('data.status');

        $this->assertSame('pending_validation', $status['state']);
        $this->assertTrue($status['pendingValidation']);
        $this->assertSame('pending', $status['validation']['status']);
        $this->assertFalse($status['canReplace']);

        $validation = \App\Models\DMS\DocumentValidation::query()->latest('Id')->firstOrFail();
        $this->assertEquals($type->Id, $validation->ValidationTypeId);
        $this->assertEquals(Document::query()->where('DocumentId', $document['documentId'])->value('Id'), $validation->DocumentId);

        $this->postJson($this->base . '/documents/' . $document['documentId'] . '/validation', ['actor' => 'CS.OFFICER'])
            ->assertStatus(409)->assertJsonPath('reason', 'pending_validation');
    }

    public function test_validation_needs_a_type_on_the_event(): void
    {
        $document = $this->upload()->assertCreated()->json('data');

        $this->postJson($this->base . '/documents/' . $document['documentId'] . '/validation', ['actor' => 'CS.OFFICER'])->assertStatus(422);
    }

    public function test_catalog_says_what_review_and_validation_are_available(): void
    {
        $reviewer = $this->makeUser();
        $this->event->update(['ReviewerUserId' => $reviewer->Id, 'ModifiedBy' => $this->actor->Id]);

        $catalog = $this->getJson($this->base . '/events?module=1330')->json('data.0');

        $this->assertSame($reviewer->Name, $catalog['review']['defaultReviewer']);
        $this->assertSame('user', $catalog['review']['defaultKind']);
        $this->assertFalse($catalog['validation']['available']);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function upload(array $override = [])
    {
        return $this->post($this->base . '/documents', $override + [
            'event' => 'ACC_ID',
            'module' => 1330,
            'entityId' => '0010000059251',
            'actor' => 'CS.OFFICER',
            'actorName' => 'Counter Officer',
            'properties' => $this->properties(),
            'file' => $this->pdf(),
        ]);
    }

    /**
     * @param array<string, string> $override
     */
    private function properties(array $override = []): string
    {
        $values = $override + ['cbs.class' => 'IDEN', 'cbs.received_by' => 'Teller One', 'cbs.received_date' => '2026-09-20 10:30'];

        return json_encode(collect($values)->map(fn ($value, $name) => ['name' => $name, 'value' => $value])->values()->all());
    }

    private function pdf(string $name = 'id.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    }

    private function makeUser(): User
    {
        $id = 'TU' . Str::upper(Str::random(8));

        $user = User::create([
            'UserID' => $id, 'Name' => 'Test ' . $id, 'Email' => Str::lower($id) . '@example.test',
            'Phone' => '9' . random_int(100000000, 999999999) . Str::upper(Str::random(4)),
            'Linked' => false, 'Notes' => 'created by ExternalDocumentApiTest', 'Password' => 'placeholder',
            'IsActive' => true, 'CreatedBy' => 1, 'ModifiedBy' => 1,
        ]);
        $user->update(['Password' => UserPasswordService::hash($user, self::PASSWORD)]);

        return $user;
    }

    /**
     * @param array<int, User> $members
     */
    private function makeTeam(array $members): Team
    {
        $team = Team::create([
            'Name' => 'Test team ' . Str::random(5), 'Email' => 'team-' . Str::lower(Str::random(8)) . '@example.test',
            'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id,
        ]);
        foreach ($members as $member) {
            $team->users()->attach($member->Id, ['CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);
        }

        return $team;
    }

    private function makeValidationType(): DocumentValidationType
    {
        return DocumentValidationType::create([
            'ValidationTypeId' => 'TV' . Str::upper(Str::random(6)), 'Name' => 'Test validation',
            'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id,
        ]);
    }

    private function placeHold(Document $document): void
    {
        $hold = LegalHold::create([
            'Name' => 'Test hold', 'Description' => 'Test', 'Ref' => 'TH-' . Str::random(5), 'Status' => LegalHoldStatusEnum::Active->value,
            'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id,
        ]);
        $document->holds()->attach($hold->Id, ['CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);
    }

    private function makeDocumentType(string $extensions, bool $expiryRequired = false, ?int $repositoryId = null, string $name = 'Test type'): DMSDocumentType
    {
        return DMSDocumentType::create([
            'Code' => 'T_' . Str::upper(Str::random(8)), 'Name' => $name, 'RepositoryId' => $repositoryId ?? Repository::query()->whereNull('DeletedOn')->orderBy('Id')->firstOrFail()->Id,
            'AllowedExtensions' => $extensions, 'MaxSizeMB' => 5, 'ExpiryRequired' => $expiryRequired, 'IsActive' => true,
            'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id,
        ]);
    }

    /** Gives the event a second document type with its own rules (PNG allowed, expiry required). */
    private function addSecondType(): DMSDocumentType
    {
        $second = $this->makeDocumentType('png', expiryRequired: true, name: 'Second type');
        $this->event->documentTypes()->attach($second->Id, ['CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);

        return $second;
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('scan.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    private function makeSystem(User $serviceUser): SourceSystem
    {
        return SourceSystem::create([
            'Code' => 'TST' . Str::upper(Str::random(6)), 'Name' => 'Test system',
            'ServiceUserId' => $serviceUser->Id, 'IsActive' => true,
            'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id,
        ]);
    }

    private function makeEvent(SourceSystem $system, string $code, int $module): EventType
    {
        $repository = Repository::query()->whereNull('DeletedOn')->orderBy('Id')->firstOrFail();

        $type = $this->makeDocumentType('pdf', repositoryId: $repository->Id);

        $event = EventType::create([
            'SourceSystemId' => $system->Id, 'Code' => $code, 'Name' => 'Event ' . $code,
            'ExternalEntityType' => 'ACCOUNT', 'AllowReplace' => true, 'IsActive' => true,
            'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id,
        ]);
        $event->documentTypes()->attach($type->Id, ['CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);
        $event->modules()->create(['SourceModuleId' => $module, 'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id]);

        $definitions = [
            ['cbs.class', 'Document class', DataTypesEnum::List, true, [['value' => 'IDEN', 'label' => 'Identification'], ['value' => 'OTHE', 'label' => 'Other']]],
            ['cbs.received_by', 'Received by', DataTypesEnum::String, true, null],
            ['cbs.received_date', 'Received date', DataTypesEnum::DateTime, true, null],
        ];
        foreach ($definitions as $order => [$name, $label, $dataType, $required, $options]) {
            $event->properties()->create([
                'Name' => $name, 'Label' => $label, 'DataType' => $dataType->value, 'IsRequired' => $required,
                'Options' => $options, 'SortOrder' => $order, 'CreatedBy' => $this->actor->Id, 'ModifiedBy' => $this->actor->Id,
            ]);
        }

        return $event;
    }
}

<?php

namespace Tests\Feature\Documents;

use App\Actions\Documents\DocumentVerificationFlow;
use App\Actions\Documents\StoreDocument;
use App\Enums\DocumentStatus;
use App\Enums\RequirementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\DocumentVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\DocumentRejected;
use App\Services\DocumentRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesOrderData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * DOC-01…04, INV-09: existence ≠ verification ≠ satisfaction; upload once, reuse by link.
 */
class DocumentRequirementTest extends TestCase
{
    use CreatesCrmData, CreatesOrderData, CreatesSalesData, RefreshDatabase;

    private User $manager;

    private User $salesman;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedReferenceData();
        $this->manager = $this->crmUser('Sales Manager');
        $this->salesman = $this->crmUser('Salesman', $this->manager);
    }

    public function test_upload_without_verification_need_satisfies_immediately(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        $photo = $this->requirement($order, 'PHOTO');

        $document = $this->uploadFor($this->salesman, $photo, file: UploadedFile::fake()->image('photo.jpg'));

        $photo->refresh()->load('document.type');
        $this->assertSame(RequirementStatus::Uploaded, $photo->status());
        $this->assertTrue($photo->isSatisfied());
        $this->assertMatchesRegularExpression('/^DOC\d{8}$/', $document->document_no);
        Storage::disk('local')->assertExists($document->versions->sole()->path);
        $this->assertSame(64, strlen($document->versions->sole()->checksum));
    }

    public function test_verification_required_document_is_not_satisfied_until_verified_by_someone_else(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        $aadhaar = $this->requirement($order, 'AADHAAR', 'SALES');
        $document = $this->uploadFor($this->manager, $aadhaar, ['reference_no' => '123412341234']);
        $flow = app(DocumentVerificationFlow::class);

        $this->assertFalse($aadhaar->fresh(['document.type'])->isSatisfied());
        $this->assertSame(1, DocumentRequirement::query()->blockingDelivery()->whereKey($aadhaar->id)->count());

        foreach ([[$this->salesman, 'not_verifier'], [$this->manager, 'self_verification']] as [$user, $rule]) {
            try {
                $flow->verify($user, $document->fresh(), null);
                $this->fail('Verification must be refused.');
            } catch (BusinessRuleException $exception) {
                $this->assertSame($rule, $exception->rule);
            }
        }

        $owner = $this->crmUser('Owner');
        $flow->start($owner, $document->fresh());
        $this->assertSame(RequirementStatus::UnderVerification, $aadhaar->fresh(['document.type'])->status());
        $flow->verify($owner, $document->fresh(), 'Matches original');

        $aadhaar = $aadhaar->fresh(['document.type']);
        $this->assertSame(RequirementStatus::Verified, $aadhaar->status());
        $this->assertTrue($aadhaar->isSatisfied());
        $this->assertSame(0, DocumentRequirement::query()->blockingDelivery()->whereKey($aadhaar->id)->count());
        $this->assertSame(['verified', 'started'], $document->verifications()->pluck('action')->map->value->all());
        $this->assertSame('••••••••1234', $document->fresh()->maskedReference());
        $this->assertNotSame('123412341234', Document::query()->toBase()->where('id', $document->id)->value('reference_no'));
    }

    public function test_rejection_needs_a_reason_and_notifies_the_uploader(): void
    {
        Notification::fake();
        $order = $this->bookOrder($this->salesman, $this->manager);
        $document = $this->uploadFor($this->salesman, $this->requirement($order, 'AADHAAR', 'SALES'));
        $flow = app(DocumentVerificationFlow::class);

        try {
            $flow->reject($this->manager, $document, ' ');
            $this->fail('Reason required.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('reason_required', $exception->rule);
        }

        $flow->reject($this->manager, $document->fresh(), 'Photo unreadable');

        $this->assertSame(DocumentStatus::Rejected, $document->fresh()->status);
        $this->assertSame(RequirementStatus::Rejected, $this->requirement($order, 'AADHAAR', 'SALES')->load('document.type')->status());
        Notification::assertSentTo($this->salesman, DocumentRejected::class);
    }

    public function test_new_version_keeps_old_versions_and_needs_verification_again(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        $document = $this->uploadFor($this->salesman, $this->requirement($order, 'AADHAAR', 'SALES'));
        app(DocumentVerificationFlow::class)->verify($this->manager, $document, null);

        app(StoreDocument::class)->addVersion($this->salesman, $document->fresh(), UploadedFile::fake()->create('rescan.pdf', 80, 'application/pdf'));

        $document->refresh();
        $this->assertSame(2, $document->current_version);
        $this->assertSame(DocumentStatus::Uploaded, $document->status);
        $this->assertNull($document->verified_by);
        $this->assertSame([2, 1], $document->versions()->pluck('version')->all());
        $this->assertSame(2, $document->currentVersion->version);
        $this->assertFalse($this->requirement($order, 'AADHAAR', 'SALES')->load('document.type')->isSatisfied());

        $this->expectException(LogicException::class);
        DocumentVersion::query()->first()->delete();
    }

    public function test_verified_customer_document_is_reused_on_the_next_order(): void
    {
        SystemSetting::put(DocumentRequirementService::AUTO_LINK_SETTING, false);
        $first = $this->bookOrder($this->salesman, $this->manager);
        $aadhaar = $this->uploadFor($this->salesman, $this->requirement($first, 'AADHAAR', 'SALES'));
        app(DocumentVerificationFlow::class)->verify($this->manager, $aadhaar, null);
        $photo = $this->uploadFor($this->salesman, $this->requirement($first, 'PHOTO'), file: UploadedFile::fake()->image('p.jpg'));
        $invoice = $this->uploadFor($this->salesman, $this->requirement($first, 'INVOICE', 'ACCOUNTS'));

        $second = $this->bookOrder($this->salesman, $this->manager, farmer: $first->farmer);
        $service = app(DocumentRequirementService::class);

        $this->assertSame($first->customer_id, $second->customer_id);
        $this->assertNull($this->requirement($second, 'AADHAAR', 'SALES')->document_id);
        $this->assertTrue($service->candidates($this->requirement($second, 'AADHAAR', 'SALES'))->contains($aadhaar));
        $this->assertTrue($service->candidates($this->requirement($second, 'AADHAAR', 'RTO'))->contains($aadhaar));
        $this->assertFalse($service->candidates($this->requirement($second, 'INVOICE', 'ACCOUNTS'))->contains($invoice)); // order-level, not reusable

        $service->link($this->salesman, $this->requirement($second, 'AADHAAR', 'SALES'), $aadhaar);
        $this->assertTrue($this->requirement($second, 'AADHAAR', 'SALES')->load('document.type')->isSatisfied());
        $this->assertSame(1, Document::query()->where('document_type_id', $aadhaar->document_type_id)->count());

        $this->expectException(BusinessRuleException::class);
        $service->link($this->salesman, $this->requirement($second, 'INVOICE', 'ACCOUNTS'), $invoice);
        $this->assertNotNull($photo);
    }

    public function test_auto_link_attaches_usable_reusable_documents_on_booking(): void
    {
        $first = $this->bookOrder($this->salesman, $this->manager);
        $aadhaar = $this->uploadFor($this->salesman, $this->requirement($first, 'AADHAAR', 'SALES'));
        $pending = $this->uploadFor($this->salesman, $this->requirement($first, 'ADDRESS_PROOF', 'RTO'));
        app(DocumentVerificationFlow::class)->verify($this->manager, $aadhaar, null);

        $second = $this->bookOrder($this->salesman, $this->manager, farmer: $first->farmer);

        $this->assertSame($aadhaar->id, $this->requirement($second, 'AADHAAR', 'SALES')->document_id);
        $this->assertSame($aadhaar->id, $this->requirement($second, 'AADHAAR', 'RTO')->document_id);
        $this->assertNull($this->requirement($second, 'ADDRESS_PROOF', 'RTO')->document_id); // not verified yet → not auto-linked
        $this->assertNotNull($pending);
    }

    public function test_expired_documents_stop_satisfying_requirements(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager, ['finance_required' => true, 'finance_amount' => '300000']);
        $do = $this->requirement($order, 'DO');

        try {
            $this->uploadFor($this->salesman, $do);
            $this->fail('Expiry date is required for a DO.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expiry_date', $exception->errors());
        }

        $document = $this->uploadFor($this->salesman, $do, ['expiry_date' => today()->addDays(5)->toDateString()]);
        app(DocumentVerificationFlow::class)->verify($this->manager, $document, null);
        $this->assertTrue($do->fresh(['document.type'])->isSatisfied());

        $this->travel(6)->days();
        $this->assertSame(RequirementStatus::Expired, $do->fresh(['document.type'])->status());
        $this->assertSame(1, DocumentRequirement::query()->withStatus(RequirementStatus::Expired)->count());

        $this->artisan('documents:mark-expired')->assertSuccessful();
        $this->artisan('documents:mark-expired')->assertSuccessful();

        $document->refresh();
        $this->assertSame(DocumentStatus::Expired, $document->status);
        $this->assertSame(1, $document->verifications()->where('action', 'expired')->count());
        $this->assertTrue(DocumentRequirement::query()->blockingDelivery()->whereKey($do->id)->exists());
    }

    public function test_file_type_and_size_follow_the_document_type(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        $requirement = $this->requirement($order, 'AADHAAR', 'SALES');

        foreach ([UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'), UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')] as $file) {
            try {
                $this->uploadFor($this->salesman, $requirement, file: $file);
                $this->fail('Upload should be refused.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('file', $exception->errors());
            }
        }

        $this->assertSame(0, Document::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_must_match_the_requirement(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);

        $this->expectException(BusinessRuleException::class);
        app(StoreDocument::class)->upload($this->salesman, $this->documentType('PAN'), $order->customer, $order,
            UploadedFile::fake()->create('pan.pdf', 10, 'application/pdf'), [], $this->requirement($order, 'AADHAAR', 'SALES'));
    }

    public function test_status_scopes_match_the_derived_status(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        $this->uploadFor($this->salesman, $this->requirement($order, 'AADHAAR', 'SALES'));

        $requirements = DocumentRequirement::query()->with('document.type')->get();

        foreach (RequirementStatus::cases() as $status) {
            $this->assertSame(
                $requirements->filter(fn (DocumentRequirement $requirement) => $requirement->status() === $status)->pluck('id')->sort()->values()->all(),
                DocumentRequirement::query()->withStatus($status)->pluck('id')->sort()->values()->all(),
                "Scope mismatch for {$status->value}",
            );
        }
    }
}

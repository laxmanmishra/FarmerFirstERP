<?php

namespace Tests\Feature\Documents;

use App\Actions\Documents\DocumentVerificationFlow;
use App\Actions\Documents\StoreDocument;
use App\Enums\DocumentStatus;
use App\Enums\RequirementState;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\DocumentTypes\Index as DocumentConfiguration;
use App\Livewire\Fulfilment\Documents\Checklist;
use App\Livewire\Fulfilment\Documents\Index as DocumentCenter;
use App\Livewire\Fulfilment\Documents\Show as DocumentShow;
use App\Livewire\Sales\Orders\Show as OrderShow;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DocumentAccessLog;
use App\Models\DocumentRequirementRule;
use App\Models\DocumentType;
use App\Models\FulfilmentTask;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\DocumentRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesOrderData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * Orders, Document Center and Document Configuration screens (DOC-05, DOC-06).
 */
class DocumentScreensTest extends TestCase
{
    use CreatesCrmData, CreatesOrderData, CreatesSalesData, RefreshDatabase;

    private User $manager;

    private User $salesman;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedReferenceData();
        $this->manager = $this->crmUser('Sales Manager');
        $this->salesman = $this->crmUser('Salesman', $this->manager);
        $this->order = $this->bookOrder($this->salesman, $this->manager);
    }

    public function test_order_screens_render_for_the_salesman_and_hide_other_orders(): void
    {
        $this->actingAs($this->salesman);

        $this->get(route('sales.orders.index'))->assertOk()->assertSee($this->order->order_no);

        foreach (['overview', 'fulfilment', 'documents', 'timeline'] as $tab) {
            $this->get(route('sales.orders.show', ['order' => $this->order, 'tab' => $tab]))->assertOk()->assertSee($this->order->order_no);
        }

        $this->actingAs($this->crmUser('Salesman', $this->manager));
        $this->get(route('sales.orders.show', $this->order))->assertNotFound();
        $this->get(route('sales.orders.index'))->assertOk()->assertDontSee($this->order->order_no);

        $this->actingAs($this->crmUser('Telecaller', department: 'TELECALLING'));
        $this->get(route('sales.orders.index'))->assertForbidden();
    }

    public function test_department_updates_its_task_from_the_order_screen(): void
    {
        $rto = $this->crmUser('RTO Employee', department: 'RTO');
        $task = $this->order->fulfilment->tasks()->whereHas('type', fn ($query) => $query->where('code', 'RTO'))->firstOrFail();
        $inProgress = WorkflowStage::findByCode('fulfilment_task', 'IN_PROGRESS');

        Livewire::actingAs($rto)->test(OrderShow::class, ['order' => $this->order])
            ->set('tab', 'fulfilment')
            ->call('openTask', $task->id, 'status')
            ->set('form.stage_id', (string) $inProgress->id)
            ->call('saveStatus')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('IN_PROGRESS', $task->fresh()->stage->code);
        $this->assertSame(Order::STAGE_IN_FULFILMENT, $this->order->fresh()->stage->code);
    }

    public function test_manager_changes_a_requirement_and_cancels_with_reason(): void
    {
        $task = $this->order->fulfilment->tasks()->whereHas('type', fn ($query) => $query->where('code', 'INSURANCE'))->firstOrFail();

        Livewire::actingAs($this->manager)->test(OrderShow::class, ['order' => $this->order])
            ->call('openTask', $task->id, 'requirement')
            ->set('form.state', 'not_required')
            ->call('saveRequirement')
            ->assertHasErrors('form.reason')
            ->set('form.reason', 'Customer has own policy')
            ->call('saveRequirement')
            ->assertHasNoErrors();

        $this->assertSame(RequirementState::NotRequired, $task->fresh()->requirement_state);

        $owner = $this->crmUser('Owner');
        Livewire::actingAs($owner)->test(OrderShow::class, ['order' => $this->order])
            ->call('openCancel')
            ->set('form.reason', 'Customer withdrew')
            ->call('cancelOrder')
            ->assertHasNoErrors();

        $this->assertTrue($this->order->fresh()->isCancelled());
        $this->assertTrue($this->order->fulfilment->tasks()->with('stage')->get()->every(fn (FulfilmentTask $task) => $task->stage->code === 'CANCELLED'));
    }

    public function test_checklist_upload_link_and_verify_flow(): void
    {
        $aadhaar = $this->requirement($this->order, 'AADHAAR', 'SALES');

        Livewire::actingAs($this->salesman)->test(Checklist::class, ['orderId' => $this->order->id])
            ->call('open', $aadhaar->id, 'upload')
            ->set('file', UploadedFile::fake()->create('notes.txt', 5, 'text/plain'))
            ->call('upload')
            ->assertHasErrors('file')
            ->set('file', UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'))
            ->set('meta.reference_no', '999988887777')
            ->call('upload')
            ->assertHasNoErrors()
            ->assertDispatched('documents-changed');

        $document = $aadhaar->fresh()->document;
        $this->assertSame(DocumentStatus::Uploaded, $document->status);

        // The RTO requirement for the same Aadhaar reuses the uploaded file once verified.
        Livewire::actingAs($this->manager)->test(Checklist::class, ['orderId' => $this->order->id])
            ->call('verify', $aadhaar->id)
            ->assertDispatched('toast', type: 'success');
        $rtoAadhaar = $this->requirement($this->order, 'AADHAAR', 'RTO');

        Livewire::actingAs($this->salesman)->test(Checklist::class, ['orderId' => $this->order->id])
            ->assertSee(__('Use existing (:n)', ['n' => 1]))
            ->call('open', $rtoAadhaar->id, 'link')
            ->call('link', $document->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame($document->id, $rtoAadhaar->fresh()->document_id);
    }

    public function test_sensitive_file_access_is_restricted_and_logged(): void
    {
        $document = $this->uploadFor($this->salesman, $this->requirement($this->order, 'AADHAAR', 'SALES'));

        $this->actingAs($this->salesman)->get(route('fulfilment.documents.file', $document))->assertOk(); // uploader
        $this->actingAs($this->manager)->get(route('fulfilment.documents.file', ['document' => $document, 'download' => 1]))->assertOk()->assertDownload('scan.pdf');

        $accounts = $this->crmUser('Accounts Employee', department: 'ACCOUNTS');
        $this->actingAs($accounts)->get(route('fulfilment.documents.show', $document))->assertOk()->assertSee(__('This is a sensitive document. You can see its details but not open the file.'));
        $this->actingAs($accounts)->get(route('fulfilment.documents.file', $document))->assertForbidden();

        $this->actingAs($this->crmUser('Salesman', $this->manager))->get(route('fulfilment.documents.file', $document))->assertNotFound();

        $this->assertSame(['view', 'download'], DocumentAccessLog::query()->orderBy('id')->pluck('action')->all());
        $this->assertSame(2, AuditLog::query()->where('event', 'sensitive_document_accessed')->count());
    }

    public function test_document_center_tabs_and_drill_down_filters(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('fulfilment.documents.index'))->assertOk()->assertSee(__('Blocking delivery'))->assertSee('Aadhaar Card');

        Livewire::actingAs($this->manager)->test(DocumentCenter::class)
            ->assertViewHas('blockingCount', fn (int $count) => $count > 0)
            ->set('tab', 'requirements')
            ->set('blocking', true)
            ->assertViewHas('requirements', fn ($paginator) => $paginator->total() > 0 && collect($paginator->items())->every(fn ($requirement) => $requirement->blocks_delivery));

        Livewire::actingAs($this->manager)->test(DocumentCenter::class, [])
            ->set('tab', 'requirements')
            ->set('status', 'pending')
            ->set('type', (string) DocumentType::query()->where('code', 'AADHAAR')->value('id'))
            ->assertViewHas('requirements', fn ($paginator) => $paginator->total() === 2);

        $document = $this->uploadFor($this->salesman, $this->requirement($this->order, 'ADDRESS_PROOF', 'RTO'));
        Livewire::actingAs($this->manager)->test(DocumentCenter::class)->set('tab', 'verification')
            ->assertViewHas('documents', fn ($paginator) => collect($paginator->items())->contains('id', $document->id));
        Livewire::actingAs($this->salesman)->test(DocumentCenter::class)
            ->assertSet('tab', 'requirements') // no dashboard / verification permission
            ->set('tab', 'repository')
            ->assertViewHas('documents', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_document_show_verifies_and_rejects(): void
    {
        $document = $this->uploadFor($this->salesman, $this->requirement($this->order, 'ADDRESS_PROOF', 'RTO'));

        Livewire::actingAs($this->manager)->test(DocumentShow::class, ['document' => $document])
            ->call('open', 'reject')
            ->call('reject')
            ->assertHasErrors('reason')
            ->set('reason', 'Blurred')
            ->call('reject')
            ->assertHasNoErrors();
        $this->assertSame(DocumentStatus::Rejected, $document->fresh()->status);

        Livewire::actingAs($this->salesman)->test(DocumentShow::class, ['document' => $document])
            ->call('open', 'version')
            ->set('file', UploadedFile::fake()->create('clear.pdf', 50, 'application/pdf'))
            ->call('uploadVersion')
            ->assertHasNoErrors();
        $this->assertSame(2, $document->fresh()->current_version);

        app(DocumentVerificationFlow::class)->verify($this->manager, $document->fresh(), null);
        $this->actingAs($this->manager)->get(route('fulfilment.documents.show', $document))->assertOk()->assertSee('v2')->assertSee(__('Verified'));
    }

    public function test_customer_and_deal_pages_show_the_order_and_documents(): void
    {
        $document = $this->uploadFor($this->salesman, $this->requirement($this->order, 'AADHAAR', 'SALES'));
        $this->actingAs($this->salesman);

        $this->get(route('sales.customers.show', ['customer' => $this->order->customer_id, 'tab' => 'orders']))->assertOk()->assertSee($this->order->order_no);
        $this->get(route('sales.customers.show', ['customer' => $this->order->customer_id, 'tab' => 'documents']))->assertOk()->assertSee($document->document_no);
        $this->get(route('sales.deals.show', $this->order->deal_id))->assertOk()->assertSee(__('Booked as :no', ['no' => $this->order->order_no]));
        $this->get(route('dashboard'))->assertOk()->assertSee(__('Documents blocking delivery'));
    }

    public function test_reusable_customer_document_can_be_uploaded_without_an_order(): void
    {
        $store = app(StoreDocument::class);
        $document = $store->upload($this->salesman, $this->documentType('PAN'), $this->order->customer, null, UploadedFile::fake()->create('pan.pdf', 20, 'application/pdf'));

        $this->assertNull($document->order_id);
        $this->assertTrue(app(DocumentRequirementService::class)->candidates($this->requirement($this->order, 'PAN'))->contains($document));

        $this->expectException(BusinessRuleException::class);
        $store->upload($this->salesman, $this->documentType('INVOICE'), $this->order->customer, null, UploadedFile::fake()->create('inv.pdf', 20, 'application/pdf'));
    }

    public function test_document_configuration_is_admin_only_and_saves(): void
    {
        $this->actingAs($this->salesman)->get(route('admin.document-types.index'))->assertForbidden();

        $owner = $this->crmUser('Owner');
        $this->actingAs($owner)->get(route('admin.document-types.index', ['tab' => 'rules']))->assertOk()->assertSee('Aadhaar Card');

        Livewire::actingAs($owner)->test(DocumentConfiguration::class)
            ->call('edit', 'type')
            ->set('form.code', 'WARRANTY_CARD')
            ->set('form.name', 'Warranty Card')
            ->call('saveType')
            ->assertHasNoErrors()
            ->call('edit', 'rule')
            ->set('form.document_type_id', (string) DocumentType::query()->where('code', 'WARRANTY_CARD')->value('id'))
            ->set('form.department_id', (string) Department::query()->where('code', 'DELIVERY')->value('id'))
            ->call('saveRule')
            ->assertHasNoErrors()
            ->call('edit', 'rule')
            ->set('form.document_type_id', (string) DocumentType::query()->where('code', 'AADHAAR')->value('id'))
            ->set('form.department_id', (string) Department::query()->where('code', 'SALES')->value('id'))
            ->call('saveRule')
            ->assertHasErrors('form.document_type_id');

        $this->assertTrue(DocumentRequirementRule::query()->whereHas('documentType', fn ($query) => $query->where('code', 'WARRANTY_CARD'))->exists());

        $next = $this->bookOrder($this->salesman, $this->manager);
        $this->assertSame(RequirementState::Required, $this->requirement($next, 'WARRANTY_CARD')->requirement_state);
        $this->assertFalse($this->order->documentRequirements()->whereHas('documentType', fn ($query) => $query->where('code', 'WARRANTY_CARD'))->exists());
    }
}

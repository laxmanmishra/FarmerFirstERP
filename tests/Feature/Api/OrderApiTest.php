<?php

namespace Tests\Feature\Api;

use App\Models\DocumentAccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesOrderData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use CreatesCrmData, CreatesOrderData, CreatesSalesData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedReferenceData();
    }

    public function test_orders_with_tasks_and_checklist_and_document_upload(): void
    {
        $manager = $this->crmUser('Sales Manager');
        $salesman = $this->crmUser('Salesman', $manager);
        $order = $this->bookOrder($salesman, $manager);
        $requirement = $this->requirement($order, 'AADHAAR', 'SALES');
        $token = $salesman->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.orders.index'))
            ->assertOk()->assertJsonPath('data.0.order_no', $order->order_no)->assertJsonPath('data.0.stage.code', 'BOOKED');

        $this->withToken($token)->getJson(route('api.v1.orders.show', $order))
            ->assertOk()->assertJsonCount(7, 'data.tasks')
            ->assertJsonFragment(['document_type' => 'AADHAAR', 'status' => 'pending', 'satisfied' => false]);

        $this->withToken($token)->postJson(route('api.v1.orders.documents.store', [$order, $requirement]), ['file' => UploadedFile::fake()->create('a.exe', 5)])
            ->assertUnprocessable()->assertJsonPath('type', 'validation_error')->assertJsonValidationErrors('file');

        $documentId = $this->withToken($token)->post(route('api.v1.orders.documents.store', [$order, $requirement]), [
            'file' => UploadedFile::fake()->image('aadhaar.jpg'), 'reference_no' => '111122223333',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'uploaded')->json('data.document_id');

        $this->withToken($token)->get(route('api.v1.documents.file', $documentId))->assertOk();
        $this->assertSame(1, DocumentAccessLog::query()->count());

        $other = $this->crmUser('Salesman', $manager)->createToken('phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson(route('api.v1.orders.show', $order))->assertNotFound();
        $this->withToken($other)->getJson(route('api.v1.documents.file', $documentId))->assertNotFound();
    }
}

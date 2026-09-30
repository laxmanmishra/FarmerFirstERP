<?php

namespace Tests\Concerns;

use App\Actions\Deals\DealApprovalFlow;
use App\Actions\Documents\StoreDocument;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Quotations\QuotationLifecycle;
use App\Enums\DealDecision;
use App\Models\Branch;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Farmer;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Orders booked through the real deal-approval flow. Requires CreatesCrmData and CreatesSalesData.
 */
trait CreatesOrderData
{
    /**
     * @param  array<string, mixed>  $terms
     */
    protected function bookOrder(User $salesman, User $approver, array $terms = [], ?Farmer $farmer = null, ?Product $product = null): Order
    {
        $enquiry = $this->validatedEnquiry($salesman, $farmer);
        $product ??= Product::factory()->create();
        $quotation = $this->quote($salesman, $enquiry, [
            ['line_type' => 'product', 'product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => '800000', 'discount_amount' => '0', 'tax_percent' => '12'],
            ['line_type' => 'charge', 'description' => 'RTO', 'quantity' => 1, 'unit_price' => '15000'],
        ], finance: ($terms['finance_required'] ?? false) ? '300000' : '0');
        $lifecycle = app(QuotationLifecycle::class);
        $lifecycle->issue($salesman, $quotation);
        $lifecycle->decide($salesman, $quotation->fresh(), true, null);
        $deal = $this->win($salesman, $enquiry);

        $flow = app(DealApprovalFlow::class);
        $flow->updateTerms($deal, $terms + [
            'expected_delivery_date' => today()->addWeek()->toDateString(),
            'booking_amount' => '25000', 'finance_required' => false, 'finance_amount' => '0',
            'rto_required' => true, 'insurance_required' => true, 'pdi_required' => true, 'remarks' => null,
        ]);
        $flow->submit($salesman, $deal->fresh(['stage', 'items']));
        $flow->decide($approver, $deal->fresh(['stage', 'items', 'enquiry.exchangeTractor', 'primarySalesman.user']), DealDecision::Approved, null);

        return Order::query()->where('deal_id', $deal->id)->firstOrFail();
    }

    /**
     * Receives available units of the product into a fresh location of the main branch.
     *
     * @return list<InventoryUnit>
     */
    protected function receiveUnits(User $actor, Product $product, int $count = 1): array
    {
        $location = StockLocation::query()->firstOrCreate(['code' => 'YARD-TEST'], ['branch_id' => Branch::query()->where('code', 'HO')->value('id'), 'name' => 'Test yard', 'type' => 'yard']);
        $units = [];

        for ($i = 0; $i < $count; $i++) {
            $units[] = ['product_id' => $product->id, 'product_variant_id' => null, 'chassis_no' => 'CH'.fake()->unique()->numerify('#########'), 'engine_no' => 'EN'.fake()->unique()->numerify('#########'), 'colour' => 'Red', 'model_year' => 2026, 'purchase_cost' => null];
        }

        return app(ReceiveStock::class)->handle($actor, $location, ['supplier_name' => 'OEM', 'supplier_invoice_no' => null, 'supplier_invoice_date' => null, 'received_on' => today()->toDateString(), 'remarks' => null], $units)->units->all();
    }

    protected function orderProduct(Order $order): Product
    {
        return Product::query()->findOrFail($order->items()->where('line_type', 'product')->value('product_id'));
    }

    protected function requirement(Order $order, string $typeCode, ?string $departmentCode = null): DocumentRequirement
    {
        return DocumentRequirement::query()
            ->where('order_id', $order->id)
            ->whereHas('documentType', fn ($query) => $query->where('code', $typeCode))
            ->when($departmentCode, fn ($query) => $query->whereHas('department', fn ($query) => $query->where('code', $departmentCode)))
            ->firstOrFail();
    }

    protected function uploadFor(User $actor, DocumentRequirement $requirement, array $meta = [], ?UploadedFile $file = null): Document
    {
        $requirement->loadMissing(['order.customer', 'documentType']);

        return app(StoreDocument::class)->upload(
            $actor,
            $requirement->documentType,
            $requirement->order->customer,
            $requirement->order,
            $file ?? UploadedFile::fake()->create('scan.pdf', 120, 'application/pdf'),
            $meta,
            $requirement,
        );
    }

    protected function documentType(string $code): DocumentType
    {
        return DocumentType::query()->where('code', $code)->firstOrFail();
    }
}

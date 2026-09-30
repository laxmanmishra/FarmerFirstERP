<?php

namespace Tests\Concerns;

use App\Actions\Deals\DealApprovalFlow;
use App\Actions\Documents\StoreDocument;
use App\Enums\DealDecision;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Farmer;
use App\Models\Order;
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
    protected function bookOrder(User $salesman, User $approver, array $terms = [], ?Farmer $farmer = null): Order
    {
        $enquiry = $this->validatedEnquiry($salesman, $farmer);
        $this->acceptedQuotation($salesman, $enquiry, ($terms['finance_required'] ?? false) ? '300000' : '0');
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

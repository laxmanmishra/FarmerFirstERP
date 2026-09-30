<?php

namespace Database\Seeders;

use App\Actions\Deals\DealApprovalFlow;
use App\Actions\Pipeline\MoveEnquiryStage;
use App\Actions\Quotations\QuotationLifecycle;
use App\Actions\Quotations\SaveQuotation;
use App\Enums\DealDecision;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Development-only: one cash deal approved by the Sales Manager, so an order with its
 * fulfilment tasks and document checklist exists — created through the real Actions.
 */
class DemoOrdersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo orders must never be seeded in production.');
        }

        if (Order::query()->exists()) {
            return;
        }

        $dwarika = User::query()->where('email', 'dwarika@farmerfirst.test')->firstOrFail();
        $manager = User::query()->where('email', 'sales.manager@farmerfirst.test')->firstOrFail();
        $enquiry = Enquiry::query()->with('requirements.product')->where('assigned_employee_id', $dwarika->employee->id)
            ->whereNotNull('pipeline_stage_id')->whereNull('closed_at')->whereDoesntHave('quotations')->oldest('id')->first();

        if ($enquiry === null) {
            return;
        }

        Auth::setUser($dwarika);
        $product = $enquiry->requirements->first()->product ?? Product::query()->where('product_type', 'tractor')->firstOrFail();
        $price = ProductPrice::resolve($product->id) ?? throw new RuntimeException('Demo price missing.');

        $quotation = app(SaveQuotation::class)->handle($dwarika, $enquiry, [
            ['line_type' => 'product', 'product_id' => $product->id, 'description' => $product->displayName(), 'quantity' => 1, 'unit_price' => $price->price, 'discount_amount' => '0', 'tax_percent' => $price->tax_percent],
            ['line_type' => 'charge', 'description' => 'RTO registration and number plate', 'quantity' => 1, 'unit_price' => '14500'],
            ['line_type' => 'charge', 'description' => 'Insurance (first year)', 'quantity' => 1, 'unit_price' => '21800'],
        ], today()->addDays(15), '0', '0', null, null);

        $lifecycle = app(QuotationLifecycle::class);
        $lifecycle->issue($dwarika, $quotation);
        $lifecycle->decide($dwarika, $quotation->fresh(), true, 'Cash purchase.');

        app(MoveEnquiryStage::class)->handle($dwarika, $enquiry->fresh(), WorkflowStage::findByCode(WorkflowDefinition::SALES_PIPELINE, 'WON'), 'Paid booking amount');

        $deal = $enquiry->fresh('deal')->deal;
        $flow = app(DealApprovalFlow::class);
        $flow->updateTerms($deal->fresh(['stage']), [
            'expected_delivery_date' => today()->addDays(7)->toDateString(),
            'booking_amount' => '25000', 'finance_required' => false, 'finance_amount' => '0',
            'rto_required' => true, 'insurance_required' => true, 'pdi_required' => true, 'remarks' => null,
        ]);
        $flow->submit($dwarika, $deal->fresh(['stage']));

        Auth::setUser($manager);
        $flow->decide($manager, $deal->fresh(['stage', 'items', 'enquiry.exchangeTractor', 'primarySalesman.user']), DealDecision::Approved, 'Cash deal, standard terms.');

        Auth::logout();
    }
}

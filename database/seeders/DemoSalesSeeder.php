<?php

namespace Database\Seeders;

use App\Actions\Deals\DealApprovalFlow;
use App\Actions\Pipeline\MoveEnquiryStage;
use App\Actions\Quotations\QuotationLifecycle;
use App\Actions\Quotations\SaveQuotation;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Development-only: price list, an accepted quotation, a won enquiry and a deal
 * waiting for the Sales Manager's approval — all created through the real Actions.
 */
class DemoSalesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo sales data must never be seeded in production.');
        }

        $prices = ['575 DI XP Plus' => '785000', '275 DI TU' => '645000', 'Arjun Novo 605' => '1045000', 'Rotavator 6 ft' => '118000',
            '744 FE' => '812000', '855 FE' => '905000', 'DI 745 III' => '798000', '5050 D' => '935000'];

        foreach (Product::query()->get() as $product) {
            if (isset($prices[$product->name])) {
                ProductPrice::query()->firstOrCreate(
                    ['product_id' => $product->id, 'product_variant_id' => null, 'branch_id' => null, 'effective_from' => today()->startOfMonth()],
                    ['price' => $prices[$product->name], 'tax_percent' => '12.00'],
                );
            }
        }

        $dwarika = User::query()->where('email', 'dwarika@farmerfirst.test')->firstOrFail();
        $enquiry = Enquiry::query()->with(['requirements', 'pipelineStage'])->where('assigned_employee_id', $dwarika->employee->id)
            ->whereNotNull('pipeline_stage_id')->whereNull('closed_at')->oldest('id')->first();

        if ($enquiry === null || $enquiry->quotations()->exists()) {
            return;
        }

        Auth::setUser($dwarika);
        $product = $enquiry->requirements->first()->product ?? Product::query()->where('product_type', 'tractor')->firstOrFail();
        $price = ProductPrice::resolve($product->id) ?? throw new RuntimeException('Demo price missing.');

        $quotation = app(SaveQuotation::class)->handle($dwarika, $enquiry, [
            ['line_type' => 'product', 'product_id' => $product->id, 'description' => $product->displayName(), 'quantity' => 1, 'unit_price' => $price->price, 'discount_amount' => '5000', 'tax_percent' => $price->tax_percent],
            ['line_type' => 'accessory', 'description' => 'Hitch, drawbar and tool kit', 'quantity' => 1, 'unit_price' => '12500', 'tax_percent' => '18'],
            ['line_type' => 'charge', 'description' => 'RTO registration and number plate', 'quantity' => 1, 'unit_price' => '14500'],
            ['line_type' => 'charge', 'description' => 'Insurance (first year)', 'quantity' => 1, 'unit_price' => '21800'],
        ], today()->addDays(15), '0', '600000', null, 'Customer prefers delivery before the kharif season.');

        $lifecycle = app(QuotationLifecycle::class);
        $lifecycle->issue($dwarika, $quotation);
        $lifecycle->decide($dwarika, $quotation->fresh(), true, 'Agreed on phone.');

        app(MoveEnquiryStage::class)->handle($dwarika, $enquiry->fresh(), WorkflowStage::findByCode(WorkflowDefinition::SALES_PIPELINE, 'WON'), 'Booking confirmed');

        $deal = $enquiry->fresh('deal')->deal;
        $flow = app(DealApprovalFlow::class);
        $flow->updateTerms($deal->fresh(['stage']), [
            'expected_delivery_date' => today()->addDays(10)->toDateString(),
            'booking_amount' => '51000', 'finance_required' => true, 'finance_amount' => '600000',
            'rto_required' => true, 'insurance_required' => true, 'pdi_required' => true, 'remarks' => null,
        ]);
        $flow->submit($dwarika, $deal->fresh(['stage']));

        Auth::logout();
    }
}

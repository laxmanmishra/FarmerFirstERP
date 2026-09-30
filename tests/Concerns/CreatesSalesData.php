<?php

namespace Tests\Concerns;

use App\Actions\Pipeline\MoveEnquiryStage;
use App\Actions\Quotations\QuotationLifecycle;
use App\Actions\Quotations\SaveQuotation;
use App\Actions\Telecaller\ClaimEnquiry;
use App\Actions\Telecaller\RecordCallAttempt;
use App\Models\Deal;
use App\Models\Enquiry;
use App\Models\Farmer;
use App\Models\Quotation;
use App\Models\User;

/**
 * Sales records built through the real Actions. Requires CreatesCrmData.
 */
trait CreatesSalesData
{
    protected function validatedEnquiry(User $salesman, ?Farmer $farmer = null, array $overrides = []): Enquiry
    {
        $telecaller = User::query()->role('Telecaller')->whereHas('employee')->first() ?? $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($salesman, $farmer, $overrides);
        app(ClaimEnquiry::class)->handle($telecaller->employee, $enquiry);
        app(RecordCallAttempt::class)->handle($telecaller, $enquiry->fresh(), $this->validationStage('VALID'), 'ok');
        $this->actingAs($salesman);

        return $enquiry->fresh();
    }

    /**
     * @param  list<array<string, mixed>>|null  $lines
     */
    protected function quote(User $actor, Enquiry $enquiry, ?array $lines = null, string $finance = '0', string $exchange = '0'): Quotation
    {
        return app(SaveQuotation::class)->handle($actor, $enquiry->fresh(), $lines ?? [
            ['line_type' => 'product', 'description' => 'Tractor 575', 'quantity' => 1, 'unit_price' => '800000', 'discount_amount' => '0', 'tax_percent' => '12'],
            ['line_type' => 'charge', 'description' => 'RTO', 'quantity' => 1, 'unit_price' => '15000'],
        ], today()->addDays(15), $exchange, $finance, null, null);
    }

    protected function acceptedQuotation(User $salesman, Enquiry $enquiry, string $finance = '0'): Quotation
    {
        $quotation = $this->quote($salesman, $enquiry, finance: $finance);
        $lifecycle = app(QuotationLifecycle::class);
        $lifecycle->issue($salesman, $quotation);
        $lifecycle->decide($salesman, $quotation->fresh(), true, null);

        return $quotation->fresh();
    }

    protected function win(User $salesman, Enquiry $enquiry): Deal
    {
        app(MoveEnquiryStage::class)->handle($salesman, $enquiry->fresh(), $this->pipelineStage('WON'), 'Booked');

        return $enquiry->fresh('deal')->deal->load('stage');
    }
}

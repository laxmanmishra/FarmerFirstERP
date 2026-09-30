<?php

namespace Tests\Feature\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\NumberSeries;
use App\Services\NumberSeriesService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NumberSeriesServiceTest extends TestCase
{
    use RefreshDatabase;

    private NumberSeriesService $numbers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->numbers = app(NumberSeriesService::class);
    }

    public function test_numbers_are_sequential_and_formatted_with_financial_year(): void
    {
        $date = CarbonImmutable::parse('2026-09-30');

        $this->assertSame('ORD/2026-27/00001', $this->numbers->next('order', date: $date));
        $this->assertSame('ORD/2026-27/00002', $this->numbers->next('order', date: $date));
        $this->assertSame('ORD/2026-27/00003', $this->numbers->preview('order', date: $date));
    }

    public function test_counter_resets_when_financial_year_changes(): void
    {
        $this->assertSame('ENQ/2026-27/00001', $this->numbers->next('enquiry', date: CarbonImmutable::parse('2027-03-31')));
        $this->assertSame('ENQ/2027-28/00001', $this->numbers->next('enquiry', date: CarbonImmutable::parse('2027-04-01')));
        $this->assertSame('ENQ/2026-27/00002', $this->numbers->next('enquiry', date: CarbonImmutable::parse('2026-12-15')));
    }

    public function test_never_reset_series_keeps_counting_across_years(): void
    {
        $this->assertSame('CUS000001', $this->numbers->next('customer', date: CarbonImmutable::parse('2026-05-01')));
        $this->assertSame('CUS000002', $this->numbers->next('customer', date: CarbonImmutable::parse('2027-05-01')));
    }

    public function test_branch_series_count_per_branch(): void
    {
        $ho = $this->headOffice();
        $second = Branch::create(['company_id' => $ho->company_id, 'code' => 'BPL', 'name' => 'Bhopal']);
        $date = CarbonImmutable::parse('2026-09-30');

        $this->assertSame('RCP/HO/2026-27/00001', $this->numbers->next('receipt', $ho, $date));
        $this->assertSame('RCP/BPL/2026-27/00001', $this->numbers->next('receipt', $second, $date));
        $this->assertSame('RCP/HO/2026-27/00002', $this->numbers->next('receipt', $ho, $date));
    }

    public function test_branch_series_requires_a_branch(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->numbers->next('receipt');
    }

    public function test_inactive_or_missing_series_is_refused(): void
    {
        NumberSeries::query()->where('entity', 'deal')->update(['is_active' => false]);

        $this->expectException(BusinessRuleException::class);

        $this->numbers->next('deal');
    }

    public function test_rolled_back_transaction_does_not_consume_a_number(): void
    {
        try {
            DB::transaction(function (): void {
                $this->numbers->next('quotation', date: CarbonImmutable::parse('2026-09-30'));

                throw new \RuntimeException('insert failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('QT/2026-27/00001', $this->numbers->next('quotation', date: CarbonImmutable::parse('2026-09-30')));
    }

    public function test_financial_year_start_month_is_configurable(): void
    {
        Company::current()->update(['financial_year_start_month' => 1]);

        $this->assertSame('ORD/2026/00001', $this->numbers->next('order', date: CarbonImmutable::parse('2026-02-01')));
    }
}

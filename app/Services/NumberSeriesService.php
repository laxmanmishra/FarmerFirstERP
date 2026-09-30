<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\NumberSeries;
use App\Models\NumberSeriesCounter;
use App\Support\FinancialYear;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Issues business document numbers (SRS v6.1 §8).
 *
 * The counter row is locked FOR UPDATE inside a transaction so concurrent
 * requests cannot receive the same number; the consuming table's unique
 * index on its number column is the final safety net. Call next() inside the
 * same transaction that inserts the record so a rollback also releases the
 * number rather than leaving a gap behind a failed insert.
 */
class NumberSeriesService
{
    public function next(string $entity, ?Branch $branch = null, ?CarbonInterface $date = null): string
    {
        return DB::transaction(function () use ($entity, $branch, $date): string {
            $series = $this->series($entity);
            $date = CarbonImmutable::instance($date ?? now());
            $scopeKey = $this->scopeKey($series, $branch, $date);

            $counter = $this->lockCounter($series, $scopeKey);
            $value = $counter->next_value;
            $counter->update(['next_value' => $value + 1]);

            return $this->format($series, $value, $branch, $date);
        });
    }

    /**
     * The number the next call would issue, without consuming it (for UI previews).
     */
    public function preview(string $entity, ?Branch $branch = null, ?CarbonInterface $date = null): string
    {
        $series = $this->series($entity);
        $date = CarbonImmutable::instance($date ?? now());

        $nextValue = $series->counters()->where('scope_key', $this->scopeKey($series, $branch, $date))->value('next_value') ?? 1;

        return $this->format($series, (int) $nextValue, $branch, $date);
    }

    public function format(NumberSeries $series, int $value, ?Branch $branch, CarbonInterface $date): string
    {
        return strtr($series->format, [
            '{prefix}' => $series->prefix,
            '{branch}' => $branch?->code ?? '',
            '{fy}' => $this->financialYear($date)->label(),
            '{yyyy}' => $date->format('Y'),
            '{seq}' => str_pad((string) $value, $series->padding, '0', STR_PAD_LEFT),
        ]);
    }

    private function series(string $entity): NumberSeries
    {
        $series = NumberSeries::query()->active()->where('entity', $entity)->first();

        if ($series === null) {
            throw new BusinessRuleException("No active number series is configured for [{$entity}].", 'number_series_missing');
        }

        return $series;
    }

    private function scopeKey(NumberSeries $series, ?Branch $branch, CarbonInterface $date): string
    {
        if ($series->per_branch && $branch === null) {
            throw new BusinessRuleException("Number series [{$series->entity}] is branch-specific; a branch is required.", 'number_series_branch_required');
        }

        $branchPart = $series->per_branch ? 'b'.$branch->id : 'all';
        $periodPart = $series->reset_policy === NumberSeries::RESET_FINANCIAL_YEAR
            ? 'fy'.$this->financialYear($date)->label()
            : 'all';

        return $branchPart.'|'.$periodPart;
    }

    private function lockCounter(NumberSeries $series, string $scopeKey): NumberSeriesCounter
    {
        $query = fn () => NumberSeriesCounter::query()
            ->where('number_series_id', $series->id)
            ->where('scope_key', $scopeKey)
            ->lockForUpdate()
            ->first();

        if ($counter = $query()) {
            return $counter;
        }

        // Concurrent first use: the unique index lets exactly one insert win.
        NumberSeriesCounter::query()->insertOrIgnore([
            'number_series_id' => $series->id,
            'scope_key' => $scopeKey,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $query();
    }

    private function financialYear(CarbonInterface $date): FinancialYear
    {
        return FinancialYear::forDate($date, Company::current()?->financial_year_start_month ?? 4);
    }
}

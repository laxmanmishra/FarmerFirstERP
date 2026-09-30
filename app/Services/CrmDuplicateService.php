<?php

namespace App\Services;

use App\Enums\DealType;
use App\Models\Enquiry;
use App\Models\Farmer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Potential-duplicate detection shown to the user before saving (SRS §9, §12).
 * Legitimate new records may still proceed after an explicit confirmation.
 */
class CrmDuplicateService
{
    /**
     * Farmers sharing a mobile number (primary or alternate), or the same name in the same village.
     *
     * @return Collection<int, Farmer>
     */
    public function farmers(?string $mobile, ?string $alternateMobile = null, ?string $name = null, ?int $villageId = null, ?int $exceptId = null): Collection
    {
        $numbers = array_values(array_filter([$mobile, $alternateMobile]));

        if ($numbers === [] && ($name === null || $villageId === null)) {
            return new Collection;
        }

        return Farmer::query()
            ->with('village.tehsil.district')
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->where(function (Builder $query) use ($numbers, $name, $villageId): void {
                if ($numbers !== []) {
                    $query->whereIn('mobile', $numbers)->orWhereIn('alternate_mobile', $numbers);
                }

                if ($name !== null && $villageId !== null && trim($name) !== '') {
                    $query->orWhere(fn (Builder $query) => $query->where('village_id', $villageId)->where('name', trim($name)));
                }
            })
            ->limit(10)
            ->get();
    }

    /**
     * Open enquiries of the same farmer (or a farmer sharing the mobile) for the same
     * deal type and overlapping products whose purchase date falls in the configured window.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Enquiry>
     */
    public function enquiries(Farmer $farmer, DealType $dealType, array $productIds, CarbonInterface $expectedDate, ?int $exceptId = null): Collection
    {
        $window = config('erp.crm.duplicate_window_days');
        $numbers = array_values(array_filter([$farmer->mobile, $farmer->alternate_mobile]));

        return Enquiry::query()
            ->with(['farmer', 'assignee', 'validationStage', 'pipelineStage', 'requirements.product.brand'])
            ->open()
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->where(fn (Builder $query) => $query
                ->where('farmer_id', $farmer->id)
                ->orWhereHas('farmer', fn (Builder $query) => $query->whereIn('mobile', $numbers)->orWhereIn('alternate_mobile', $numbers)))
            ->where('deal_type', $dealType)
            ->whereBetween('expected_purchase_date', [$expectedDate->copy()->subDays($window), $expectedDate->copy()->addDays($window)])
            ->when($productIds !== [], fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereHas('requirements', fn (Builder $query) => $query->whereIn('product_id', $productIds))
                ->orWhereDoesntHave('requirements', fn (Builder $query) => $query->whereNotNull('product_id'))))
            ->latest('id')
            ->limit(10)
            ->get();
    }
}

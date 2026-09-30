<?php

namespace App\Actions\Farmers;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Farmer;
use App\Services\CrmDuplicateService;
use App\Services\NumberSeriesService;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a farmer. A potential duplicate (same mobile, or same name in the
 * same village) is refused unless the user confirms with a reason (SRS §9).
 */
class SaveFarmer
{
    public function __construct(
        private readonly NumberSeriesService $numbers,
        private readonly CrmDuplicateService $duplicates,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, ?Branch $branch, ?Farmer $farmer = null, bool $confirmedNotDuplicate = false): Farmer
    {
        if (! $confirmedNotDuplicate) {
            $matches = $this->duplicates->farmers(
                $attributes['mobile'] ?? null,
                $attributes['alternate_mobile'] ?? null,
                $attributes['name'] ?? null,
                $attributes['village_id'] ?? null,
                $farmer?->id,
            );

            if ($matches->isNotEmpty()) {
                throw new BusinessRuleException(
                    __('A farmer with this mobile number or name already exists in this village.'),
                    'duplicate_farmer',
                    ['farmer_ids' => $matches->pluck('id')->all()],
                );
            }
        }

        return DB::transaction(function () use ($attributes, $branch, $farmer): Farmer {
            if ($farmer !== null) {
                $farmer->update($attributes);

                return $farmer;
            }

            if ($branch === null) {
                throw new BusinessRuleException(__('Select a working branch before creating farmers.'), 'branch_required');
            }

            return Farmer::create([
                ...$attributes,
                'farmer_no' => $this->numbers->next('farmer', $branch),
                'branch_id' => $branch->id,
            ]);
        });
    }
}

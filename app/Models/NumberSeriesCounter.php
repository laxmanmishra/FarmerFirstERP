<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['number_series_id', 'scope_key', 'next_value'])]
class NumberSeriesCounter extends Model
{
    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }

    /**
     * @return BelongsTo<NumberSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'number_series_id');
    }
}

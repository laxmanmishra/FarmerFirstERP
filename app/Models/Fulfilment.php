<?php

namespace App\Models;

use App\Enums\FulfilmentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FULFILMENT_ID for an order (SRS §23): the container of the parallel department tasks.
 */
#[Fillable(['fulfilment_no', 'order_id', 'status', 'closed_at'])]
class Fulfilment extends Model
{
    use Auditable, HasUserstamps;

    protected function casts(): array
    {
        return ['status' => FulfilmentStatus::class, 'closed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<FulfilmentTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(FulfilmentTask::class)->orderBy('id');
    }
}

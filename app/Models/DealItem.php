<?php

namespace App\Models;

use App\Models\Concerns\HasCommercialLines;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Approved commercial snapshot line, copied from the accepted quotation.
 */
#[Fillable(['deal_id', 'line_type', 'product_id', 'product_variant_id', 'description', 'quantity', 'unit_price', 'discount_amount', 'tax_percent', 'tax_amount', 'line_total', 'sort_order'])]
class DealItem extends Model
{
    use HasCommercialLines;
}

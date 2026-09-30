<?php

namespace App\Livewire\Sales\Quotations;

use App\Actions\Quotations\SaveQuotation;
use App\Enums\LineType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\DiscountLimit;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Services\QuotationCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Prepare or edit a draft quotation for a validated enquiry (SRS v6.1 §4).
 */
class Form extends Component
{
    use InteractsWithUi;

    public const DEFAULT_TERMS = "Prices are ex-showroom unless stated. Registration, insurance and accessories as listed.\nSubject to stock availability. Valid until the date shown.";

    #[Locked]
    public int $enquiryId;

    #[Locked]
    public ?int $quotationId = null;

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public string $valid_until = '';

    public string $exchange_value = '0';

    public string $finance_amount = '0';

    public string $terms = '';

    public string $remarks = '';

    public function mount(?Quotation $quotation = null): void
    {
        if ($quotation?->exists) {
            $this->authorize('quotations.create');
            abort_unless(Quotation::query()->visibleTo(Auth::user())->whereKey($quotation->id)->exists(), 404);
            abort_unless($quotation->status->isEditable(), 403, __('Only drafts can be edited.'));

            $this->quotationId = $quotation->id;
            $this->enquiryId = $quotation->enquiry_id;
            $this->lines = $quotation->items->map(fn ($item) => [
                'line_type' => $item->line_type->value,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'tax_percent' => $item->tax_percent,
            ])->all();
            $this->valid_until = $quotation->valid_until->toDateString();
            $this->exchange_value = $quotation->exchange_value;
            $this->finance_amount = $quotation->finance_amount;
            $this->terms = (string) $quotation->terms;
            $this->remarks = (string) $quotation->remarks;

            return;
        }

        $this->authorize('quotations.create');
        $enquiry = Enquiry::query()->visibleTo(Auth::user())->with('requirements')->findOrFail(request()->integer('enquiry'));
        $this->enquiryId = $enquiry->id;
        $this->valid_until = today()->addDays(15)->toDateString();
        $this->terms = self::DEFAULT_TERMS;

        foreach ($enquiry->requirements as $requirement) {
            $this->addLine(LineType::Product->value, $requirement->product_id, $requirement->product_variant_id, $requirement->quantity);
        }

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function addLine(string $type = 'product', ?int $productId = null, ?int $variantId = null, int $quantity = 1): void
    {
        $this->lines[] = [
            'line_type' => $type, 'product_id' => $productId, 'product_variant_id' => $variantId,
            'description' => '', 'quantity' => $quantity, 'unit_price' => '0', 'discount_amount' => '0', 'tax_percent' => '0',
        ];

        if ($productId) {
            $this->applyPrice(array_key_last($this->lines));
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function updatedLines(mixed $value, string $key): void
    {
        [$index, $field] = explode('.', $key) + [null, null];

        if ($field === 'line_type' && $value === LineType::Charge->value) {
            $this->lines[$index]['product_id'] = null;
            $this->lines[$index]['product_variant_id'] = null;
        }

        if ($field === 'product_id') {
            $this->lines[$index]['product_variant_id'] = null;
            $this->applyPrice((int) $index);
        }

        if ($field === 'product_variant_id') {
            $this->applyPrice((int) $index);
        }
    }

    public function save(SaveQuotation $save): mixed
    {
        $this->authorize('quotations.create');

        $this->validate([
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.line_type' => ['required', Rule::enum(LineType::class)],
            'lines.*.product_id' => ['nullable', Rule::exists('products', 'id')],
            'lines.*.product_variant_id' => ['nullable', Rule::exists('product_variants', 'id')],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valid_until' => ['required', 'date', 'after_or_equal:today'],
            'exchange_value' => ['nullable', 'numeric', 'min:0'],
            'finance_amount' => ['nullable', 'numeric', 'min:0'],
            'terms' => ['nullable', 'string', 'max:3000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['lines.*.description' => __('description'), 'lines.*.unit_price' => __('price'), 'lines.*.quantity' => __('quantity')]);

        try {
            $quotation = $save->handle(
                Auth::user(),
                Enquiry::query()->findOrFail($this->enquiryId),
                $this->lines,
                CarbonImmutable::parse($this->valid_until),
                $this->exchange_value,
                $this->finance_amount,
                $this->terms ?: null,
                $this->remarks ?: null,
                $this->quotationId ? Quotation::query()->findOrFail($this->quotationId) : null,
            );
        } catch (BusinessRuleException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return null;
        }

        session()->flash('toast', ['type' => 'success', 'message' => $quotation->status->value === 'pending_approval'
            ? __('Quotation saved. The discount exceeds your limit and was sent for approval.')
            : __('Quotation :no saved.', ['no' => $quotation->reference()])]);

        return $this->redirectRoute('sales.quotations.show', $quotation, navigate: true);
    }

    public function render(QuotationCalculator $calculator): mixed
    {
        $enquiry = Enquiry::query()->with(['farmer.village', 'exchangeTractor', 'requirements'])->findOrFail($this->enquiryId);
        $calculation = null;
        $error = null;

        try {
            $calculation = $calculator->calculate(array_map(fn ($line) => $line + ['description' => $line['description'] ?: '—'], $this->lines), $this->exchange_value, $this->finance_amount);
        } catch (BusinessRuleException $exception) {
            $error = $exception->getMessage();
        } catch (\Throwable) {
            $error = __('Complete the amounts to see totals.');
        }

        $productIds = array_filter(array_column($this->lines, 'product_id'));

        return view('livewire.sales.quotations.form', [
            'enquiry' => $enquiry,
            'totals' => $calculation['totals'] ?? null,
            'calcError' => $error,
            'withinLimit' => $calculation ? DiscountLimit::allows(Auth::user(), $calculation['totals']['discount_total'], $calculation['totals']['discount_percent']) : true,
            'products' => Product::query()->active()->with('brand:id,name')->orderBy('name')->get(),
            'variants' => $productIds === [] ? collect() : ProductVariant::query()->active()->whereIn('product_id', $productIds)->orderBy('name')->get(),
            'lineTypes' => LineType::options(),
        ])->title($this->quotationId ? __('Edit quotation') : __('New quotation'));
    }

    /**
     * Fills unit price, tax and description from the price master for the chosen product/variant.
     */
    private function applyPrice(int $index): void
    {
        $line = $this->lines[$index];

        if (! $line['product_id']) {
            return;
        }

        $product = Product::query()->with('brand')->find($line['product_id']);
        $variant = $line['product_variant_id'] ? ProductVariant::query()->find($line['product_variant_id']) : null;
        $price = ProductPrice::resolve((int) $line['product_id'], $variant?->id, Enquiry::query()->whereKey($this->enquiryId)->value('branch_id'));

        $this->lines[$index]['description'] = trim($product->brand->name.' '.$product->name.($variant ? ' — '.$variant->name : ''));

        if ($price !== null) {
            $this->lines[$index]['unit_price'] = $price->price;
            $this->lines[$index]['tax_percent'] = $price->tax_percent;
        }
    }
}

<?php

namespace App\Actions\Enquiries;

use App\Enums\DealType;
use App\Enums\ProductType;
use App\Models\LookupValue;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Validated enquiry input shared by the web form and the API.
 */
final readonly class EnquiryData
{
    /**
     * @param  list<array{requirement_type: string, brand_id: ?int, product_id: ?int, product_variant_id: ?int, quantity: int, description: ?string}>  $requirements
     * @param  array{brand_name: string, model_name: string, manufacturing_year: ?int, hours_used: ?int, registration_number: ?string, condition: ?string, customer_expected_price: ?string, remarks: ?string}|null  $exchange
     */
    public function __construct(
        public string $sourceCode,
        public DealType $dealType,
        public CarbonImmutable $expectedPurchaseDate,
        public ?string $budget,
        public ?string $remarks,
        public ?int $villageId,
        public array $requirements,
        public ?array $exchange = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            sourceCode: $input['source_code'],
            dealType: DealType::from($input['deal_type']),
            expectedPurchaseDate: CarbonImmutable::parse($input['expected_purchase_date'])->startOfDay(),
            budget: ($input['budget'] ?? '') === '' ? null : (string) $input['budget'],
            remarks: ($input['remarks'] ?? '') === '' ? null : $input['remarks'],
            villageId: isset($input['village_id']) && $input['village_id'] !== '' ? (int) $input['village_id'] : null,
            requirements: array_values(array_map(fn (array $line): array => [
                'requirement_type' => $line['requirement_type'],
                'brand_id' => self::nullableInt($line['brand_id'] ?? null),
                'product_id' => self::nullableInt($line['product_id'] ?? null),
                'product_variant_id' => self::nullableInt($line['product_variant_id'] ?? null),
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'description' => ($line['description'] ?? '') === '' ? null : $line['description'],
            ], $input['requirements'] ?? [])),
            exchange: ($input['deal_type'] ?? null) === DealType::Exchange->value ? array_map(
                fn ($value) => $value === '' ? null : $value,
                array_intersect_key($input['exchange'] ?? [], array_flip([
                    'brand_name', 'model_name', 'manufacturing_year', 'hours_used', 'registration_number',
                    'condition', 'customer_expected_price', 'remarks',
                ])),
            ) : null,
        );
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_values(array_filter(array_column($this->requirements, 'product_id')));
    }

    /**
     * Shared validation rules (web form and API).
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}source_code" => ['required', 'string', Rule::exists('lookup_values', 'code')->where('type', LookupValue::ENQUIRY_SOURCE)->where('is_active', true)],
            "{$prefix}deal_type" => ['required', Rule::enum(DealType::class)],
            "{$prefix}expected_purchase_date" => ['required', 'date', 'after_or_equal:today'],
            "{$prefix}budget" => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            "{$prefix}remarks" => ['nullable', 'string', 'max:2000'],
            "{$prefix}village_id" => ['nullable', Rule::exists('villages', 'id')],
            "{$prefix}requirements" => ['required', 'array', 'min:1', 'max:10'],
            "{$prefix}requirements.*.requirement_type" => ['required', Rule::enum(ProductType::class)],
            "{$prefix}requirements.*.brand_id" => ['nullable', Rule::exists('brands', 'id')],
            "{$prefix}requirements.*.product_id" => ['nullable', Rule::exists('products', 'id')],
            "{$prefix}requirements.*.product_variant_id" => ['nullable', Rule::exists('product_variants', 'id')],
            "{$prefix}requirements.*.quantity" => ['required', 'integer', 'min:1', 'max:50'],
            "{$prefix}requirements.*.description" => ['nullable', 'string', 'max:500'],
            "{$prefix}exchange.brand_name" => ["required_if:{$prefix}deal_type,exchange", 'nullable', 'string', 'max:100'],
            "{$prefix}exchange.model_name" => ["required_if:{$prefix}deal_type,exchange", 'nullable', 'string', 'max:100'],
            "{$prefix}exchange.manufacturing_year" => ['nullable', 'integer', 'min:1960', 'max:'.now()->year],
            "{$prefix}exchange.hours_used" => ['nullable', 'integer', 'min:0', 'max:200000'],
            "{$prefix}exchange.registration_number" => ['nullable', 'string', 'max:20'],
            "{$prefix}exchange.condition" => ['nullable', 'string', 'max:30'],
            "{$prefix}exchange.customer_expected_price" => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            "{$prefix}exchange.remarks" => ['nullable', 'string', 'max:1000'],
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}

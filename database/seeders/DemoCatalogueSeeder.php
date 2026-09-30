<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Development-only sample catalogue. The real catalogue is maintained in
 * Administration → Products.
 */
class DemoCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            ['MAH', 'Mahindra', true, [
                ['575 DI XP Plus', ProductType::Tractor, 47, ['2WD', '4WD']],
                ['275 DI TU', ProductType::Tractor, 39, ['Standard']],
                ['Arjun Novo 605', ProductType::Tractor, 57, ['2WD', '4WD']],
                ['Rotavator 6 ft', ProductType::Implement, null, ['42 blade']],
            ]],
            ['SWA', 'Swaraj', true, [
                ['744 FE', ProductType::Tractor, 48, ['Standard']],
                ['855 FE', ProductType::Tractor, 52, ['Standard', '4WD']],
            ]],
            ['SON', 'Sonalika', false, [['DI 745 III', ProductType::Tractor, 50, ['Standard']]]],
            ['JDR', 'John Deere', false, [['5050 D', ProductType::Tractor, 50, ['Standard']]]],
        ];

        foreach ($catalogue as [$code, $brandName, $isDealerBrand, $products]) {
            $brand = Brand::query()->firstOrCreate(['code' => $code], ['name' => $brandName, 'is_dealer_brand' => $isDealerBrand]);

            foreach ($products as [$name, $type, $hp, $variants]) {
                $product = Product::query()->firstOrCreate(['brand_id' => $brand->id, 'name' => $name], ['product_type' => $type, 'hp' => $hp]);

                foreach ($variants as $variant) {
                    ProductVariant::query()->firstOrCreate(['product_id' => $product->id, 'name' => $variant]);
                }
            }
        }
    }
}

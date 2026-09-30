<?php

namespace Database\Seeders;

use App\Actions\Accounts\PaymentFlow;
use App\Actions\Inventory\AllocationFlow;
use App\Actions\Inventory\ReceiveStock;
use App\Enums\LineType;
use App\Models\Branch;
use App\Models\Financer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Development-only: financers, stock locations, a goods receipt of demo tractors, the
 * demo order's unit allocation and one customer payment waiting for verification —
 * all through the real Actions.
 */
class DemoFulfilmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo fulfilment data must never be seeded in production.');
        }

        if (StockLocation::query()->exists()) {
            return;
        }

        foreach ([
            ['HDFC', 'HDFC Bank', 'bank', [['Rakesh Singh', 'Tractor loans officer', '9811000001', 'Sehore']]],
            ['SBI', 'State Bank of India', 'bank', [['Meena Kulkarni', 'Agri loans', '9811000002', 'Bhopal']]],
            ['MMFSL', 'Mahindra Finance', 'nbfc', [['Arvind Jain', 'Field executive', '9811000003', 'Raisen'], ['Sunita Mishra', 'Branch manager', '9811000004', 'Bhopal']]],
        ] as [$code, $name, $type, $contacts]) {
            $financer = Financer::create(['code' => $code, 'name' => $name, 'type' => $type]);

            foreach ($contacts as [$contact, $designation, $mobile, $area]) {
                $financer->contacts()->create(['name' => $contact, 'designation' => $designation, 'mobile' => $mobile, 'area' => $area]);
            }
        }

        $branch = Branch::query()->where('code', 'HO')->firstOrFail();
        $yard = StockLocation::create(['branch_id' => $branch->id, 'code' => 'HO-YARD', 'name' => 'Main yard', 'type' => 'yard']);
        StockLocation::create(['branch_id' => $branch->id, 'code' => 'HO-SHOW', 'name' => 'Showroom floor', 'type' => 'showroom']);

        $storekeeper = User::query()->where('email', 'inventory@farmerfirst.test')->firstOrFail();
        $order = Order::query()->with('items')->oldest('id')->first();
        $products = Product::query()->where('product_type', 'tractor')->where('is_active', true)->limit(3)->get();

        if ($order !== null && ($ordered = $order->items->firstWhere('line_type', LineType::Product)?->product_id) !== null) {
            $products = $products->push(Product::query()->findOrFail($ordered))->unique('id');
        }

        $units = [];

        foreach ($products->values() as $index => $product) {
            foreach (['Red', 'Blue'] as $colour) {
                $serial = str_pad((string) (count($units) + 1), 4, '0', STR_PAD_LEFT);
                $units[] = ['product_id' => $product->id, 'product_variant_id' => null, 'chassis_no' => "DEMO{$index}CH{$serial}", 'engine_no' => "DEMO{$index}EN{$serial}",
                    'colour' => $colour, 'model_year' => (int) now()->year, 'purchase_cost' => null];
            }
        }

        Auth::setUser($storekeeper);
        $inward = app(ReceiveStock::class)->handle($storekeeper, $yard, [
            'supplier_name' => 'OEM regional depot', 'supplier_invoice_no' => 'OEM/INV/1001', 'supplier_invoice_date' => today()->subDays(3)->toDateString(),
            'received_on' => today()->subDays(2)->toDateString(), 'remarks' => 'Demo stock',
        ], $units);

        if ($order !== null) {
            $unit = $inward->units->first(fn ($unit) => $order->items->contains('product_id', $unit->product_id));

            if ($unit !== null) {
                app(AllocationFlow::class)->allocate($storekeeper, $order, $unit);
            }

            $cashier = User::query()->where('email', 'accounts@farmerfirst.test')->firstOrFail();
            Auth::setUser($cashier);
            app(PaymentFlow::class)->record($cashier, $order->accountFile()->firstOrFail(), [
                'payer_type' => 'customer', 'mode' => 'upi', 'amount' => $order->booking_amount, 'reference_no' => 'UPI-DEMO-0001',
                'instrument_date' => null, 'bank_name' => null, 'received_on' => today()->toDateString(), 'remarks' => 'Booking amount',
            ]);
        }

        Auth::logout();
    }
}

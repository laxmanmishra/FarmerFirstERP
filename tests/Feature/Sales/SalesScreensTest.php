<?php

namespace Tests\Feature\Sales;

use App\Livewire\Admin\Products\Prices;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\DemoCatalogueSeeder;
use Database\Seeders\DemoCrmSeeder;
use Database\Seeders\DemoSalesSeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed([DemoUsersSeeder::class, DemoCatalogueSeeder::class, DemoCrmSeeder::class, DemoSalesSeeder::class]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function lists(): array
    {
        return [
            'customers' => ['sales.customers.index', 'customers.view'],
            'quotations' => ['sales.quotations.index', 'quotations.view'],
            'deals' => ['sales.deals.index', 'deals.view'],
            'deal approvals' => ['sales.deal-approvals.index', 'deals.approve'],
        ];
    }

    #[DataProvider('lists')]
    public function test_list_renders_for_manager_and_is_forbidden_without_permission(string $route): void
    {
        $this->actingAs($this->demo('sales.manager'))->get(route($route))->assertOk();
        $this->actingAs($this->userWithPermissions(['dashboard.view']))->get(route($route))->assertForbidden();
    }

    public function test_detail_pages_render_with_demo_data(): void
    {
        $manager = $this->demo('sales.manager');
        $deal = Deal::query()->firstOrFail();

        $this->actingAs($manager)->get(route('sales.deals.show', $deal))->assertOk()->assertSee($deal->deal_no)->assertSee(__('Approve'));
        $this->actingAs($manager)->get(route('sales.deal-approvals.index'))->assertOk()->assertSee($deal->deal_no);
        $this->actingAs($manager)->get(route('sales.quotations.show', Quotation::query()->firstOrFail()))->assertOk();
        $this->actingAs($manager)->get(route('sales.customers.show', Customer::query()->firstOrFail()))->assertOk()->assertSee(__('Approved business'));
        $this->actingAs($manager)->get(route('sales.customers.show', ['customer' => Customer::query()->firstOrFail(), 'tab' => 'timeline']))->assertOk();
        $this->actingAs($manager)->get(route('crm.enquiries.show', $deal->enquiry_id))->assertOk()->assertSee($deal->deal_no);
        $this->actingAs($this->demo('owner'))->get(route('admin.products.index', ['tab' => 'prices']))->assertOk();
        $this->actingAs($this->demo('owner'))->get(route('admin.settings.index', ['tab' => 'discounts']))->assertOk()->assertSee('Sales Manager');
    }

    public function test_salesman_only_sees_own_customers_and_deals(): void
    {
        $this->actingAs($this->demo('dwarika'))->get(route('sales.deals.index'))->assertOk()->assertSee(Deal::query()->first()->deal_no);
        $this->actingAs($this->demo('salesman2'))->get(route('sales.deals.index'))->assertOk()->assertDontSee(Deal::query()->first()->deal_no);
        $this->actingAs($this->demo('salesman2'))->get(route('sales.customers.show', Customer::query()->first()))->assertNotFound();
    }

    public function test_price_resolution_prefers_variant_and_branch_and_respects_dates(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => '4WD']);
        $branch = $this->headOffice();

        ProductPrice::create(['product_id' => $product->id, 'price' => '700000', 'effective_from' => today()->subYear()]);
        ProductPrice::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'price' => '760000', 'effective_from' => today()->subMonth()]);
        ProductPrice::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'branch_id' => $branch->id, 'price' => '765000', 'effective_from' => today()->subWeek()]);
        ProductPrice::create(['product_id' => $product->id, 'price' => '999999', 'effective_from' => today()->addMonth()]);

        $this->assertSame('700000.00', ProductPrice::resolve($product->id)->price);
        $this->assertSame('760000.00', ProductPrice::resolve($product->id, $variant->id)->price);
        $this->assertSame('765000.00', ProductPrice::resolve($product->id, $variant->id, $branch->id)->price);
    }

    public function test_new_price_ends_the_previous_open_price(): void
    {
        $product = Product::factory()->create();
        $old = ProductPrice::create(['product_id' => $product->id, 'price' => '700000', 'effective_from' => today()->subYear()]);

        Livewire::actingAs($this->demo('owner'))->test(Prices::class)
            ->call('create')
            ->set('form.product_id', $product->id)
            ->set('form.price', '720000')
            ->set('form.tax_percent', '12')
            ->set('form.effective_from', today()->addDay()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(today()->toDateString(), $old->fresh()->effective_to->toDateString());
        $this->assertSame('700000.00', ProductPrice::resolve($product->id)->price);
        $this->travel(2)->days();
        $this->assertSame('720000.00', ProductPrice::resolve($product->id)->price);
    }

    private function demo(string $key): User
    {
        return User::query()->where('email', "{$key}@farmerfirst.test")->firstOrFail();
    }
}

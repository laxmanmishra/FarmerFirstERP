<?php

namespace Tests\Feature\Crm;

use App\Models\Enquiry;
use App\Models\Farmer;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Database\Seeders\DemoCatalogueSeeder;
use Database\Seeders\DemoCrmSeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every CRM and Phase 2 admin screen renders with realistic demo data, and is
 * refused without its permission.
 */
class CrmScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed([DemoUsersSeeder::class, DemoCatalogueSeeder::class, DemoCrmSeeder::class]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function screens(): array
    {
        return [
            'farmers' => ['crm.farmers.index', 'farmers.view'],
            'enquiries' => ['crm.enquiries.index', 'enquiries.view_all'],
            'new enquiry' => ['crm.enquiries.create', 'enquiries.create'],
            'telecaller' => ['crm.telecaller.index', 'telecaller.queue'],
            'follow-ups' => ['crm.follow-ups.index', 'follow_ups.view'],
            'pipeline' => ['crm.pipeline.index', 'pipeline.view'],
            'reopen requests' => ['crm.reopen-requests.index', 'enquiries.reopen'],
            'territory' => ['crm.territory.index', 'territory.view'],
            'products' => ['admin.products.index', 'products.view'],
            'workflows' => ['admin.workflows.index', 'workflow.view'],
            'geography import' => ['admin.geography.import', 'geography.import'],
        ];
    }

    #[DataProvider('screens')]
    public function test_screen_renders_for_owner(string $route, string $permission): void
    {
        $owner = $this->demoUser('owner');
        $this->assertTrue($owner->can($permission));
        $this->actingAs($owner)->get(route($route))->assertOk();
    }

    #[DataProvider('screens')]
    public function test_screen_is_forbidden_without_permission(string $route, string $permission): void
    {
        $this->actingAs($this->userWithPermissions(['dashboard.view']))->get(route($route))->assertForbidden();
    }

    public function test_detail_pages_render(): void
    {
        $owner = $this->demoUser('owner');
        $enquiry = Enquiry::query()->whereNotNull('pipeline_stage_id')->firstOrFail();

        $this->actingAs($owner)->get(route('crm.enquiries.show', $enquiry))->assertOk()->assertSee($enquiry->enquiry_no);
        $this->actingAs($owner)->get(route('crm.enquiries.edit', $enquiry))->assertOk();
        $this->actingAs($owner)->get(route('crm.farmers.show', Farmer::query()->firstOrFail()))->assertOk();
        $this->actingAs($owner)->get(route('admin.workflows.edit', WorkflowDefinition::byCode(WorkflowDefinition::SALES_PIPELINE)))->assertOk()->assertSee('NEGOTIATION');
        $this->actingAs($owner)->get(route('admin.settings.index', ['tab' => 'lists']))->assertOk()->assertSee('Walk-in');
    }

    public function test_dashboard_shows_live_crm_kpis_for_salesman(): void
    {
        $this->actingAs($this->demoUser('dwarika'))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Open enquiries')
            ->assertSee(route('crm.follow-ups.index', ['tab' => 'overdue']), false)
            ->assertDontSee('Reopen requests');
    }

    private function demoUser(string $key): User
    {
        return User::query()->where('email', "{$key}@farmerfirst.test")->firstOrFail();
    }
}

<?php

namespace Tests\Feature\Crm;

use App\Actions\Enquiries\CreateEnquiry;
use App\Actions\Enquiries\EnquiryData;
use App\Livewire\Crm\Enquiries\Show;
use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\EnquiryAttachment;
use App\Models\Farmer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesCrmData;
use Tests\TestCase;

/**
 * Record-level scope: own / team / all, within permitted branches (docs/RBAC_MATRIX.md).
 */
class VisibilityTest extends TestCase
{
    use CreatesCrmData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_salesman_sees_own_manager_sees_team_telecaller_sees_all(): void
    {
        $manager = $this->crmUser('Sales Manager');
        $dwarika = $this->crmUser('Salesman', $manager);
        $ramesh = $this->crmUser('Salesman', $manager);
        $outsider = $this->crmUser('Salesman');
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');

        $mine = $this->makeEnquiry($dwarika);
        $colleagues = $this->makeEnquiry($ramesh);
        $foreign = $this->makeEnquiry($outsider);

        $this->assertEqualsCanonicalizing([$mine->id], Enquiry::query()->visibleTo($dwarika)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$mine->id, $colleagues->id, $foreign->id], Enquiry::query()->visibleTo($manager)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$mine->id, $colleagues->id, $foreign->id], Enquiry::query()->visibleTo($telecaller)->pluck('id')->all());

        // Sales Manager holds view_all by default; a manager limited to view_team sees only their team.
        $teamLead = $this->userWithPermissions(['enquiries.view_team']);
        $leadEmployee = $this->employeeFor($teamLead);
        $dwarika->employee->update(['reports_to_id' => $leadEmployee->id]);
        $ramesh->employee->update(['reports_to_id' => $leadEmployee->id]);

        $this->assertEqualsCanonicalizing([$mine->id, $colleagues->id], Enquiry::query()->visibleTo($teamLead->fresh())->pluck('id')->all());
    }

    public function test_salesman_cannot_open_another_salesmans_enquiry(): void
    {
        $other = $this->makeEnquiry($this->crmUser('Salesman'));
        $salesman = $this->crmUser('Salesman');

        $this->actingAs($salesman)->get(route('crm.enquiries.show', $other))->assertNotFound();
        $this->actingAs($salesman)->getJson(route('api.v1.enquiries.show', $other))->assertNotFound();
    }

    public function test_branch_scope_hides_other_branches(): void
    {
        $second = Branch::create(['company_id' => $this->headOffice()->company_id, 'code' => 'BR2', 'name' => 'Second']);
        $telecaller = $this->crmUser('Telecaller', department: 'TELECALLING');
        $enquiry = $this->makeEnquiry($this->crmUser('Salesman'));
        $enquiry->forceFill(['branch_id' => $second->id])->save();

        $this->assertFalse(Enquiry::query()->visibleTo($telecaller)->whereKey($enquiry->id)->exists());
        $this->assertTrue(Enquiry::query()->visibleTo($this->userWithRole('Owner'))->whereKey($enquiry->id)->exists());
    }

    public function test_exchange_photos_are_private_and_served_only_to_permitted_users(): void
    {
        Storage::fake('local');
        $salesman = $this->crmUser('Salesman');
        $this->actingAs($salesman);
        $enquiry = app(CreateEnquiry::class)->handle($salesman, Farmer::factory()->create(), EnquiryData::fromArray([
            'source_code' => 'WALK_IN', 'deal_type' => 'exchange', 'expected_purchase_date' => today()->addWeek()->toDateString(),
            'requirements' => [['requirement_type' => 'tractor', 'quantity' => 1]],
            'exchange' => ['brand_name' => 'Eicher', 'model_name' => '380'],
        ]), photos: [UploadedFile::fake()->image('old.jpg')]);

        $photo = EnquiryAttachment::query()->where('enquiry_id', $enquiry->id)->sole();
        Storage::disk('local')->assertExists($photo->path);
        $this->assertStringNotContainsString('public', $photo->path);

        $this->actingAs($salesman)->get(route('crm.enquiries.attachments.show', $photo))->assertOk();
        $this->actingAs($this->crmUser('Salesman'))->get(route('crm.enquiries.attachments.show', $photo))->assertNotFound();
    }

    public function test_show_page_hides_actions_the_user_lacks(): void
    {
        $salesman = $this->crmUser('Salesman');
        $enquiry = $this->makeEnquiry($salesman);

        Livewire::actingAs($salesman)->test(Show::class, ['enquiry' => $enquiry])
            ->assertDontSeeHtml("openModal('assign')")
            ->assertSeeHtml("openModal('follow-up')")
            ->call('openModal', 'assign')
            ->assertForbidden();
    }
}

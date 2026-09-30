<?php

namespace Tests\Feature\Crm;

use App\Actions\FollowUps\CompleteFollowUp;
use App\Actions\FollowUps\ScheduleFollowUp;
use App\Enums\FollowUpStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\FollowUp;
use App\Notifications\FollowUpReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCrmData;
use Tests\TestCase;

class FollowUpTest extends TestCase
{
    use CreatesCrmData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_follow_up_is_scheduled_completed_and_the_next_one_created(): void
    {
        $salesman = $this->crmUser('Salesman');
        $enquiry = $this->makeEnquiry($salesman);
        $followUp = app(ScheduleFollowUp::class)->handle($salesman, $enquiry, $salesman->employee, 'CALL', now()->addHour(), 'Share price');

        app(CompleteFollowUp::class)->handle($salesman, $followUp, 'Price shared, wants demo', ['type_code' => 'DEMO', 'due_at' => now()->addDays(2), 'purpose' => 'Demo at farm']);

        $this->assertSame(FollowUpStatus::Completed, $followUp->fresh()->status);
        $this->assertSame(1, FollowUp::query()->pending()->where('purpose', 'Demo at farm')->count());
    }

    public function test_past_due_time_is_rejected(): void
    {
        $salesman = $this->crmUser('Salesman');

        $this->expectException(BusinessRuleException::class);
        app(ScheduleFollowUp::class)->handle($salesman, $this->makeEnquiry($salesman), $salesman->employee, 'CALL', now()->subMinute(), 'Late');
    }

    public function test_only_assignee_or_their_manager_can_complete(): void
    {
        $manager = $this->crmUser('Sales Manager');
        $salesman = $this->crmUser('Salesman', $manager);
        $stranger = $this->crmUser('Salesman');
        $followUp = app(ScheduleFollowUp::class)->handle($salesman, $this->makeEnquiry($salesman), $salesman->employee, 'CALL', now()->addHour(), 'Call');

        try {
            app(CompleteFollowUp::class)->handle($stranger, $followUp, 'Not mine');
            $this->fail('Stranger should not complete the follow-up.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('not_follow_up_owner', $exception->rule);
        }

        app(CompleteFollowUp::class)->handle($manager, $followUp, 'Handled by manager');
        $this->assertSame(FollowUpStatus::Completed, $followUp->fresh()->status);
    }

    public function test_overdue_is_derived_and_reminders_are_sent_once(): void
    {
        Notification::fake();
        $salesman = $this->crmUser('Salesman');
        $followUp = app(ScheduleFollowUp::class)->handle($salesman, $this->makeEnquiry($salesman), $salesman->employee, 'CALL', now()->addMinutes(30), 'Call back');

        $this->artisan('crm:follow-up-reminders')->assertSuccessful();
        $this->artisan('crm:follow-up-reminders')->assertSuccessful();
        Notification::assertSentToTimes($salesman, FollowUpReminder::class, 1);

        $this->travel(2)->hours();
        $this->assertTrue($followUp->fresh()->isOverdue());
        $this->assertSame(1, FollowUp::query()->overdue()->count());

        $this->artisan('crm:follow-up-reminders')->assertSuccessful();
        $this->artisan('crm:follow-up-reminders')->assertSuccessful();
        Notification::assertSentToTimes($salesman, FollowUpReminder::class, 2);
        Notification::assertSentTo($salesman, FollowUpReminder::class, fn (FollowUpReminder $notification) => $notification->overdue);
    }

    public function test_follow_ups_page_lists_overdue_for_the_salesman(): void
    {
        $salesman = $this->crmUser('Salesman');
        $followUp = app(ScheduleFollowUp::class)->handle($salesman, $this->makeEnquiry($salesman), $salesman->employee, 'CALL', now()->addMinutes(5), 'Ring the farmer');
        $this->travel(1)->hour();

        $this->actingAs($salesman)->get(route('crm.follow-ups.index', ['tab' => 'overdue']))
            ->assertOk()
            ->assertSee('Ring the farmer');

        $this->assertTrue($followUp->fresh()->isOverdue());
    }
}

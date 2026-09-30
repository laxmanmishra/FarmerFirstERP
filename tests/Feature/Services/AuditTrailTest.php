<?php

namespace Tests\Feature\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_model_changes_record_old_and_new_values_with_actor(): void
    {
        $user = $this->userWithRole('Owner');
        $this->actingAs($user);

        $department = Department::query()->where('code', 'RTO')->firstOrFail();
        $department->update(['name' => 'RTO & Registration']);

        $entry = AuditLog::query()->where('event', 'updated')->where('auditable_id', $department->id)->where('auditable_type', $department->getMorphClass())->firstOrFail();

        $this->assertSame('departments', $entry->module);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(['name' => 'RTO / Back Office'], $entry->old_values);
        $this->assertSame(['name' => 'RTO & Registration'], $entry->new_values);
        $this->assertSame($user->id, $department->fresh()->updated_by);
    }

    public function test_no_op_save_writes_no_audit_entry(): void
    {
        $branch = $this->headOffice();
        $before = AuditLog::query()->count();

        $branch->update(['name' => $branch->name]);

        $this->assertSame($before, AuditLog::query()->count());
    }

    public function test_audit_entries_cannot_be_updated(): void
    {
        $entry = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $entry->update(['reason' => 'tampered']);
    }

    public function test_audit_entries_cannot_be_deleted(): void
    {
        $entry = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_request_id_is_attached_to_audit_rows_and_response(): void
    {
        $owner = $this->userWithRole('Owner');

        $response = $this->actingAs($owner)->withHeader('X-Request-Id', 'test-correlation-123')
            ->post(route('branch.switch'), ['branch_id' => $this->headOffice()->id]);

        $response->assertHeader('X-Request-Id', 'test-correlation-123');
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $owner->id, 'module' => 'users', 'request_id' => 'test-correlation-123']);
    }

    public function test_user_cannot_switch_to_a_branch_they_do_not_belong_to(): void
    {
        $other = Branch::create(['company_id' => $this->headOffice()->company_id, 'code' => 'BR2', 'name' => 'Second']);
        $user = $this->userWithRole('Salesman');
        $this->employeeFor($user);

        $this->actingAs($user)->post(route('branch.switch'), ['branch_id' => $other->id])->assertForbidden();
    }
}

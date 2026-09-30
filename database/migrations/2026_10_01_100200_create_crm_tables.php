<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM: farmers, enquiries (SRS §7–11), telecaller call attempts, follow-ups,
 * assignments and reopen requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->string('farmer_no', 30)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('mobile', 10)->index();
            $table->string('alternate_mobile', 10)->nullable()->index();
            $table->string('whatsapp_number', 10)->nullable();
            $table->foreignId('village_id')->constrained()->restrictOnDelete();
            $table->string('address', 500)->nullable();
            $table->string('pin_code', 6)->nullable();
            $table->decimal('land_acres', 8, 2)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_no', 30)->unique();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('village_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('source_code', 50);
            $table->string('deal_type', 20);
            $table->date('expected_purchase_date');
            $table->string('temperature', 20)->index();
            $table->decimal('budget', 14, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('validation_stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')->nullable()->constrained('workflow_stages')->restrictOnDelete();
            $table->foreignId('claimed_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('callback_at')->nullable()->index();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason_code', 50)->nullable();
            $table->text('close_remarks')->nullable();
            $table->string('duplicate_override_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
            $table->index('expected_purchase_date');
        });

        Schema::create('enquiry_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->string('requirement_type', 20);
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('exchange_tractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand_name', 100);
            $table->string('model_name', 100);
            $table->unsignedSmallInteger('manufacturing_year')->nullable();
            $table->unsignedInteger('hours_used')->nullable();
            $table->string('registration_number', 20)->nullable();
            $table->string('condition', 30)->nullable();
            $table->decimal('customer_expected_price', 14, 2)->nullable();
            $table->decimal('approved_exchange_value', 14, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('enquiry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('enquiry_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('call_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('called_at');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->foreignId('outcome_stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('next_callback_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'called_at']);
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->morphs('followable');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('type_code', 50);
            $table->timestamp('due_at');
            $table->string('purpose', 500);
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 1000)->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['assigned_employee_id', 'status', 'due_at']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('reopen_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_remarks', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reopen_requests');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('call_attempts');
        Schema::dropIfExists('enquiry_assignments');
        Schema::dropIfExists('enquiry_attachments');
        Schema::dropIfExists('exchange_tractors');
        Schema::dropIfExists('enquiry_requirements');
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('farmers');
    }
};

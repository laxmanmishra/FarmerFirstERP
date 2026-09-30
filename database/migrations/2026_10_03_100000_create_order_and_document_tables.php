<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders, fulfilment tasks and the Document Center (SRS §15, §23, §185–217).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30)->unique();
            $table->foreignId('deal_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('primary_salesman_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->date('order_date');
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('charges_total', 14, 2)->default(0);
            $table->decimal('exchange_value', 14, 2)->default(0);
            $table->decimal('order_value', 14, 2)->default(0);
            $table->boolean('finance_required')->default(false);
            $table->decimal('finance_amount', 14, 2)->default(0);
            $table->decimal('customer_contribution', 14, 2)->default(0);
            $table->decimal('booking_amount', 14, 2)->default(0);
            $table->date('expected_delivery_date')->nullable();
            $table->boolean('rto_required')->default(true);
            $table->boolean('insurance_required')->default(true);
            $table->boolean('pdi_required')->default(true);
            $table->json('deal_snapshot');
            $table->text('remarks')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['branch_id', 'stage_id']);
            $table->index('customer_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('line_type', 20);
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fulfilment_task_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('condition', 30);
            $table->boolean('blocks_delivery')->default(true);
            $table->string('update_permission', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('fulfilments', function (Blueprint $table) {
            $table->id();
            $table->string('fulfilment_no', 30)->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->timestamp('closed_at')->nullable();
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('fulfilment_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfilment_id')->constrained()->restrictOnDelete();
            $table->foreignId('fulfilment_task_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            // Requirement State (Required / Not Required / Conditional / Waived) is independent of the operational stage (SRS §234–235).
            $table->string('requirement_state', 20);
            $table->foreignId('stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->boolean('blocks_delivery')->default(true);
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('requirement_remarks', 1000)->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->unique(['fulfilment_id', 'fulfilment_task_type_id']);
            $table->index(['department_id', 'requirement_state']);
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->string('category', 40);
            $table->string('level', 20);
            $table->boolean('is_reusable')->default(false);
            $table->boolean('expiry_applicable')->default(false);
            $table->boolean('verification_required')->default(true);
            $table->string('verification_permission', 100)->default('documents.verify');
            $table->string('sensitivity', 20)->default('normal');
            $table->string('allowed_extensions', 100)->default('pdf,jpg,jpeg,png');
            $table->unsignedInteger('max_size_kb')->default(5120);
            $table->foreignId('default_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_no', 30)->unique();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->index();
            $table->text('reference_no')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->unsignedSmallInteger('current_version')->default(1);
            $table->string('remarks', 1000)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['customer_id', 'document_type_id']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('disk', 30);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'version']);
        });

        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 20);
            $table->string('remarks', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
        });

        Schema::create('document_requirement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            // Null = required on every order; otherwise follows that fulfilment task's requirement state.
            $table->foreignId('fulfilment_task_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('blocks_delivery')->default(false);
            $table->unsignedSmallInteger('due_offset_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->unique(['document_type_id', 'department_id']);
        });

        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_requirement_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fulfilment_task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requirement_state', 20);
            $table->boolean('blocks_delivery')->default(false);
            $table->date('due_date')->nullable();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->unique(['order_id', 'document_type_id', 'department_id'], 'document_requirements_scope_unique');
            $table->index('document_id');
            $table->index(['requirement_state', 'blocks_delivery']);
        });

        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');
        Schema::dropIfExists('document_requirements');
        Schema::dropIfExists('document_requirement_rules');
        Schema::dropIfExists('document_verifications');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('fulfilment_tasks');
        Schema::dropIfExists('fulfilments');
        Schema::dropIfExists('fulfilment_task_types');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

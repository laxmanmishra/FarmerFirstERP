<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retail & Finance (SRS §57, §65–73), Accounts (§91–113) and Inventory (§53–56).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfilment_task_types', function (Blueprint $table) {
            // Tasks whose progress is driven by a department file instead of manual status updates.
            $table->string('driven_by', 30)->nullable()->after('update_permission');
        });

        Schema::create('financers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('financer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financer_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('designation', 100)->nullable();
            $table->string('mobile', 10);
            $table->string('email')->nullable();
            $table->string('area', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('finance_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_no', 30)->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('fulfilment_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->foreignId('financer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('financer_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('loan_amount', 14, 2)->default(0);
            $table->decimal('sanctioned_amount', 14, 2)->nullable();
            $table->decimal('down_payment', 14, 2)->nullable();
            $table->unsignedSmallInteger('tenure_months')->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('emi_amount', 14, 2)->nullable();
            $table->string('loan_account_no', 50)->nullable();
            $table->string('do_number', 50)->nullable();
            $table->date('do_date')->nullable();
            $table->decimal('do_amount', 14, 2)->nullable();
            $table->date('do_valid_until')->nullable();
            $table->decimal('disbursed_amount', 14, 2)->nullable();
            $table->date('disbursed_on')->nullable();
            $table->text('remarks')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['branch_id', 'stage_id']);
        });

        Schema::create('file_queries', function (Blueprint $table) {
            $table->id();
            $table->morphs('queryable');
            $table->string('raised_by_party', 150);
            $table->string('subject', 255);
            $table->text('description')->nullable();
            $table->string('status', 20)->index();
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('response')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('account_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_no', 30)->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('fulfilment_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->decimal('receivable_amount', 14, 2);
            $table->decimal('customer_share', 14, 2);
            $table->decimal('finance_share', 14, 2)->default(0);
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['branch_id', 'stage_id']);
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->string('refund_no', 30)->unique();
            $table->foreignId('account_file_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('reason', 1000);
            $table->string('status', 20)->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_remarks', 1000)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 30)->unique();
            $table->foreignId('account_file_id')->constrained()->restrictOnDelete();
            // receipt | reversal | refund — reversals and refunds are negative and never edit the original (INV-06).
            $table->string('kind', 20);
            $table->string('payer_type', 20);
            $table->string('mode', 30);
            $table->decimal('amount', 14, 2);
            $table->string('reference_no', 100)->nullable();
            $table->date('instrument_date')->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->date('received_on');
            $table->string('status', 30)->index();
            $table->string('status_reason', 1000)->nullable();
            $table->foreignId('reverses_payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->foreignId('refund_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('remarks', 1000)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['account_file_id', 'status']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 40)->unique();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('account_file_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('stock_inwards', function (Blueprint $table) {
            $table->id();
            $table->string('grn_no', 30)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('supplier_name', 150);
            $table->string('supplier_invoice_no', 60)->nullable();
            $table->date('supplier_invoice_date')->nullable();
            $table->date('received_on');
            $table->string('remarks', 1000)->nullable();
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('chassis_no', 60)->unique();
            $table->string('engine_no', 60)->unique();
            $table->string('colour', 40)->nullable();
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->string('status_reason', 1000)->nullable();
            $table->foreignId('stock_inward_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->date('received_on');
            $table->userstamps();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });

        Schema::create('stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            // Equals inventory_unit_id while the allocation is active, NULL once released: the unique
            // index makes a double allocation impossible even under concurrency (INV-04).
            $table->unsignedBigInteger('active_unit_key')->nullable()->unique();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'released_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignId('from_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('remarks', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_unit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_allocations');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('stock_inwards');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('account_files');
        Schema::dropIfExists('file_queries');
        Schema::dropIfExists('finance_files');
        Schema::dropIfExists('financer_contacts');
        Schema::dropIfExists('financers');
        Schema::table('fulfilment_task_types', fn (Blueprint $table) => $table->dropColumn('driven_by'));
    }
};

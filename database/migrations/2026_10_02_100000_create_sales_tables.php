<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales (SRS §12, §14, v6.1 §4): customers, price master, discount limits,
 * versioned quotations, deals with approval history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            // Stages the application refers to by code (e.g. deal approval). Renamable, never deactivated or deleted.
            $table->boolean('is_system')->default(false)->after('is_active');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_no', 30)->unique();
            $table->foreignId('farmer_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('mobile', 10)->index();
            $table->string('alternate_mobile', 10)->nullable()->index();
            $table->string('whatsapp_number', 10)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('village_id')->constrained()->restrictOnDelete();
            $table->string('address', 500)->nullable();
            $table->string('pin_code', 6)->nullable();
            $table->string('pan', 10)->nullable()->unique();
            $table->date('customer_since');
            $table->foreignId('source_enquiry_id')->nullable()->constrained('enquiries')->nullOnDelete();
            $table->foreignId('possible_duplicate_of_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->index('name');
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('farmer_id')->constrained()->nullOnDelete();
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('price', 14, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->index(['product_id', 'product_variant_id', 'effective_from'], 'product_prices_lookup_idx');
        });

        Schema::create('discount_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->decimal('max_percent', 5, 2)->default(0);
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no', 30);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('enquiry_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('prepared_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 30)->index();
            $table->date('valid_until');
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('items_total', 14, 2)->default(0);
            $table->decimal('charges_total', 14, 2)->default(0);
            $table->decimal('exchange_value', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('finance_amount', 14, 2)->default(0);
            $table->decimal('customer_contribution', 14, 2)->default(0);
            $table->decimal('discount_percent', 6, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('discount_approved_at')->nullable();
            $table->string('approval_remarks', 1000)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_remarks', 1000)->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->unique(['quotation_no', 'version']);
            $table->index(['enquiry_id', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
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

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('deal_no', 30)->unique();
            $table->foreignId('enquiry_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('primary_salesman_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('charges_total', 14, 2)->default(0);
            $table->decimal('exchange_value', 14, 2)->default(0);
            $table->decimal('deal_value', 14, 2)->default(0);
            $table->boolean('finance_required')->default(false);
            $table->decimal('finance_amount', 14, 2)->default(0);
            $table->decimal('customer_contribution', 14, 2)->default(0);
            $table->decimal('booking_amount', 14, 2)->default(0);
            $table->date('expected_delivery_date')->nullable();
            $table->boolean('rto_required')->default(true);
            $table->boolean('insurance_required')->default(true);
            $table->boolean('pdi_required')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['branch_id', 'stage_id']);
            $table->index('customer_id');
        });

        Schema::create('deal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
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

        Schema::create('deal_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['deal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_approvals');
        Schema::dropIfExists('deal_items');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('discount_limits');
        Schema::dropIfExists('product_prices');
        Schema::table('enquiries', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('customers');
        Schema::table('workflow_stages', fn (Blueprint $table) => $table->dropColumn('is_system'));
    }
};

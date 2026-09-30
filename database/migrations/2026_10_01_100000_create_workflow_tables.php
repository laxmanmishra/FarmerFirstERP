<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic configurable status engine (SRS §76–78, §88). Business code relies on
 * the semantic flags of a stage, never on its name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('module', 50)->index();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->boolean('controlled_transitions')->default(false);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('color', 20)->default('slate');
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('is_completion')->default(false);
            $table->boolean('is_hold')->default(false);
            $table->boolean('is_rejection')->default(false);
            $table->boolean('blocks_delivery')->default(false);
            $table->boolean('requires_remark')->default(false);
            $table->boolean('requires_followup')->default(false);
            $table->boolean('requires_document')->default(false);
            $table->unsignedInteger('sla_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'code']);
            $table->index(['workflow_definition_id', 'sequence']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('workflow_stages')->cascadeOnDelete();
            $table->foreignId('to_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->json('allowed_roles')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->index(['workflow_definition_id', 'from_stage_id']);
        });

        Schema::create('workflow_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->restrictOnDelete();
            $table->morphs('subject');
            $table->foreignId('from_stage_id')->nullable()->constrained('workflow_stages')->restrictOnDelete();
            $table->foreignId('to_stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workflow_definition_id', 'to_stage_id', 'created_at'], 'wf_history_definition_stage_created_idx');
        });

        Schema::create('lookup_values', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('code', 50);
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->unique(['type', 'code']);
            $table->index(['type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_values');
        Schema::dropIfExists('workflow_status_histories');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflow_definitions');
    }
};

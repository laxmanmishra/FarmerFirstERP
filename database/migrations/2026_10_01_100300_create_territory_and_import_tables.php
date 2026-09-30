<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Salesman territory at District, Tehsil or Village level (SRS §6).
         * `primary_scope` is "{level}:{area_id}" only while the row is an active
         * primary assignment and NULL otherwise, so the unique index enforces
         * "one primary salesman per area" on MySQL and SQLite alike.
         */
        Schema::create('territory_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('level', 20);
            $table->foreignId('district_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('tehsil_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('village_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('primary_scope', 40)->nullable()->unique();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('end_reason', 500)->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['level', 'is_active']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('status', 20);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('errors')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('territory_assignments');
    }
};

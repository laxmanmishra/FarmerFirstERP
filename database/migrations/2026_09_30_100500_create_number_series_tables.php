<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable, transaction-safe number series (SRS v6.1 §8). A counter
 * row exists per series and scope (branch and/or financial year), and is
 * locked for update while a number is issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_series', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 50)->unique();
            $table->string('name', 100);
            $table->string('prefix', 20);
            $table->string('format', 100)->default('{prefix}/{fy}/{seq}');
            $table->unsignedTinyInteger('padding')->default(5);
            $table->string('reset_policy', 20)->default('financial_year');
            $table->boolean('per_branch')->default(false);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('number_series_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('number_series_id')->constrained('number_series')->cascadeOnDelete();
            $table->string('scope_key', 64);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['number_series_id', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_series_counters');
        Schema::dropIfExists('number_series');
    }
};

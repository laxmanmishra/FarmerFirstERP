<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Territory hierarchy State → District → Tehsil → Village (SRS §6).
 * Village uniqueness "District + Tehsil + Village Name" is enforced by
 * UQ(tehsil_id, name) because a tehsil belongs to exactly one district.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->nullable()->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->unique(['state_id', 'name']);
        });

        Schema::create('tehsils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->nullable()->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->unique(['district_id', 'name']);
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tehsil_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->nullable()->unique();
            $table->string('name', 150);
            $table->string('pin_code', 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->unique(['tehsil_id', 'name']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
        Schema::dropIfExists('tehsils');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('states');
    }
};

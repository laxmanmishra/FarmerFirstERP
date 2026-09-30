<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product catalogue (brand → model → variant). Physical units are a separate
 * inventory concern (Phase 5) and never live here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100)->unique();
            $table->boolean('is_dealer_brand')->default(false);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('product_type', 20)->index();
            $table->string('name', 150);
            $table->unsignedSmallInteger('hp')->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->unique(['brand_id', 'name']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
    }
};

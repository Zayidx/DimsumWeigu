<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('label', 48)->default('Rumah');
            $table->string('recipient_name');
            $table->string('phone', 32);
            $table->string('province');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('subdistrict')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->text('address');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('ingredient_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('code', 48)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('buy_price', 18, 8)->default(0);
            $table->decimal('stock', 15, 3)->default(0);
            $table->decimal('reorder_point', 15, 3)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->decimal('yield_quantity', 15, 3)->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_id', 'version']);
        });
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('base_quantity', 15, 3)->nullable();
            $table->decimal('waste_percent', 7, 4)->default(0);
            $table->timestamps();
            $table->unique(['recipe_version_id', 'ingredient_id']);
        });
        Schema::create('recipe_product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('waste_percent', 7, 4)->default(0);
            $table->timestamps();
            $table->unique(['recipe_version_id', 'product_id']);
        });

        Schema::create('production_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('open')->index();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['warehouse_id', 'opened_at']);
        });

        Schema::create('production_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->char('payload_hash', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('production_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipe_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
        });
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('production_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique('production_request_id');
            $table->foreignId('production_shift_id')->nullable()->constrained('production_shifts')->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipe_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('planned_quantity', 15, 3);
            $table->decimal('produced_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('production_request_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('required_quantity', 15, 3);
            $table->decimal('available_quantity', 15, 3)->default(0);
            $table->decimal('purchase_quantity', 15, 3)->default(0);
            $table->decimal('reserved_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 18, 8)->default(0);
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['production_request_id', 'ingredient_id'], 'prm_request_ingredient_unique');
        });

        Schema::create('outlet_stock_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->text('reason');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('outlet_stock_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_stock_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'outlet_stock_return_items', 'outlet_stock_returns', 'production_order_items', 'production_orders',
            'production_request_items', 'production_requests', 'recipe_items', 'recipe_versions', 'ingredients',
            'ingredient_categories', 'customer_credits', 'customer_addresses',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

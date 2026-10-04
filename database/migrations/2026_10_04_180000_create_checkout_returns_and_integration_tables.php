<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->string('hold_label', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'cashier_shift_id', 'status']);
        });
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('manual_discount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['cart_id', 'product_id', 'unit_id']);
        });

        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('goods_receiving_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->string('resolution', 20)->default('credit');
            $table->text('reason');
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receiving_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->string('idempotency_key', 96)->unique();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 160);
            $table->string('payload_hash', 64);
            $table->boolean('signature_verified')->default(false);
            $table->string('status', 20)->default('received')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('public_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 32)->index();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('expires_at')->useCurrent()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('module', 48);
            $table->string('source_name');
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('error_report_path')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('ingredient_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('stock', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['ingredient_id', 'warehouse_id']);
        });
        Schema::create('ingredient_inventory_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->decimal('quantity_delta', 15, 3);
            $table->decimal('quantity_before', 15, 3);
            $table->decimal('quantity_after', 15, 3);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach ([
            'ingredient_inventory_ledgers', 'ingredient_warehouses', 'import_batches', 'public_access_tokens',
            'webhook_events', 'cash_handovers', 'supplier_return_items', 'supplier_returns', 'cart_items', 'carts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

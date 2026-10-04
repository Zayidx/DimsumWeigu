<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('map_url')->nullable();
            $table->json('opening_hours')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_sales_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->createIfMissing('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 24)->default('branch');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_sales_enabled')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['outlet_id', 'is_active']);
        });

        $this->createIfMissing('user_outlets', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->primary(['user_id', 'outlet_id']);
        });

        $this->createIfMissing('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope_key', 48)->default('global');
            $table->string('group', 64)->default('general');
            $table->string('key');
            $table->json('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scope_key', 'key']);
            $table->index(['group', 'outlet_id']);
        });

        $this->createIfMissing('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->createIfMissing('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('symbol', 16)->unique();
            $table->string('dimension', 24)->default('quantity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->createIfMissing('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('factor', 20, 8);
            $table->timestamps();
            $table->unique(['from_unit_id', 'to_unit_id']);
        });

        $this->createIfMissing('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 96)->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('buy_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('stock', 15, 3)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->decimal('max_stock', 15, 3)->nullable();
            $table->decimal('reorder_point', 15, 3)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_sellable')->default(true)->index();
            $table->boolean('track_stock')->default(true);
            $table->boolean('track_batches')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'is_active']);
        });

        $this->createIfMissing('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('conversion_factor', 20, 8);
            $table->decimal('sell_price', 15, 2)->nullable();
            $table->string('barcode', 96)->nullable()->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'unit_id']);
        });

        $this->createIfMissing('product_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('stock', 15, 3)->default(0);
            $table->decimal('reserved_stock', 15, 3)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->decimal('reorder_point', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'stock']);
        });

        $this->createIfMissing('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 80);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('quantity_remaining', 15, 3)->default(0);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id', 'batch_number']);
            $table->index(['warehouse_id', 'product_id', 'expires_at']);
        });

        $this->createIfMissing('inventory_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->decimal('quantity_delta', 15, 3);
            $table->decimal('quantity_before', 15, 3);
            $table->decimal('quantity_after', 15, 3);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['warehouse_id', 'product_id', 'created_at']);
        });

        $this->createIfMissing('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->decimal('quantity', 15, 3);
            $table->decimal('stock_before', 15, 3);
            $table->decimal('stock_after', 15, 3);
            $table->string('reason')->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->timestamps();
        });

        $this->createIfMissing('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('loyalty_points', 15, 2)->default(0);
            $table->string('member_tier', 24)->default('regular');
            $table->boolean('marketing_consent')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->createIfMissing('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('tax_number', 64)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->createIfMissing('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('open_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->default(0);
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closing_note')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'status', 'opened_at']);
            $table->index(['user_id', 'status']);
        });

        $this->createIfMissing('shift_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('warehouse_cash_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'created_at']);
        });

        $this->createIfMissing('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source', 24)->default('pos');
            $table->string('status', 24)->default('pending')->index();
            $table->string('payment_status', 24)->default('unpaid')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('shipping_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_total', 15, 2)->default(0);
            $table->decimal('change_total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('client_uuid', 64)->nullable()->unique();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->string('public_token_hash', 64)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'occurred_at', 'status']);
            $table->index(['warehouse_id', 'occurred_at']);
        });

        $this->createIfMissing('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('product_sku', 64)->nullable();
            $table->string('unit_name', 32);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('transaction_tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('method', 32);
            $table->decimal('amount', 15, 2);
            $table->string('status', 24)->default('pending');
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['method', 'status']);
        });

        $this->createIfMissing('transaction_detail_batch_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_detail_id')->constrained(indexName: 'detail_batch_detail_fk')->restrictOnDelete();
            $table->foreignId('product_batch_id')->constrained(indexName: 'detail_batch_batch_fk')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
            $table->unique(['transaction_detail_id', 'product_batch_id'], 'detail_batch_unique');
        });

        $this->createIfMissing('profits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_detail_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->decimal('revenue', 15, 2);
            $table->decimal('cost', 15, 2);
            $table->decimal('profit', 15, 2);
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    // MySQL DDL is not transactional: resume an interrupted initial migration safely.
    private function createIfMissing(string $name, Closure $definition): void
    {
        if (Schema::hasTable($name)) {
            if (DB::table($name)->exists()) {
                throw new RuntimeException("Cannot resume initial migration over populated table: {$name}");
            }
            $blueprint = new Blueprint(Schema::getConnection(), $name, $definition);
            $blueprint->toSql();
            $indexes = array_column(Schema::getIndexes($name), 'name');
            $foreignKeys = array_column(Schema::getForeignKeys($name), 'name');
            foreach ($blueprint->getCommands() as $command) {
                if ($command->name === 'foreign' && ! in_array($command->index, $foreignKeys, true)) {
                    Schema::table($name, function (Blueprint $table) use ($command) {
                        $foreign = $table->foreign($command->columns, $command->index)->references($command->references)->on($command->on);
                        if ($command->onDelete) {
                            $foreign->onDelete($command->onDelete);
                        }
                        if ($command->onUpdate) {
                            $foreign->onUpdate($command->onUpdate);
                        }
                    });
                } elseif (in_array($command->name, ['unique', 'index'], true) && ! in_array($command->index, $indexes, true)) {
                    Schema::table($name, fn (Blueprint $table) => $table->{$command->name}($command->columns, $command->index));
                }
            }

            return;
        }
        Schema::create($name, $definition);
    }

    public function down(): void
    {
        foreach ([
            'profits', 'transaction_detail_batch_allocations', 'transaction_tenders', 'transaction_details',
            'transactions', 'warehouse_cash_ledgers', 'shift_cash_movements', 'cashier_shifts',
            'suppliers', 'customers', 'stock_mutations', 'inventory_ledgers', 'product_batches',
            'product_warehouses', 'product_units', 'products', 'unit_conversions', 'units', 'categories',
            'settings', 'user_outlets', 'warehouses', 'outlets',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_sent', 15, 3)->default(0);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['stock_transfer_id', 'product_id']);
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('variance', 15, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['stock_opname_id', 'product_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('fees_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_ordered', 15, 3);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'product_id']);
        });

        Schema::create('goods_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('completed')->index();
            $table->timestamp('received_at')->useCurrent()->index();
            $table->string('idempotency_key', 96)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('goods_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receiving_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('accepted_quantity', 15, 3);
            $table->decimal('rejected_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->string('qc_status', 20)->default('accepted');
            $table->text('qc_note')->nullable();
            $table->timestamps();
        });

        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receiving_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 48)->unique();
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('due_at')->nullable()->index();
            $table->string('status', 20)->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('payable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('method', 32);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->string('idempotency_key', 96)->unique();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 48)->unique();
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('due_at')->nullable()->index();
            $table->string('status', 20)->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 32);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->string('idempotency_key', 96)->unique();
            $table->timestamp('paid_at')->useCurrent()->index();
            $table->timestamps();
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->string('resolution', 20)->default('refund');
            $table->decimal('total', 15, 2)->default(0);
            $table->text('reason');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_detail_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->string('restock_destination', 24)->default('sellable');
            $table->timestamps();
        });

        Schema::create('outlet_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('category', 80);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->date('occurred_on')->index();
            $table->timestamps();
        });

        Schema::create('outlet_waste_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('reason', 48);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'outlet_waste_records', 'outlet_expenses', 'sales_return_items', 'sales_returns',
            'receivable_payments', 'receivables', 'payable_payments', 'payables',
            'goods_receiving_items', 'goods_receivings', 'purchase_order_items', 'purchase_orders',
            'stock_opname_items', 'stock_opnames', 'stock_transfer_items', 'stock_transfers',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

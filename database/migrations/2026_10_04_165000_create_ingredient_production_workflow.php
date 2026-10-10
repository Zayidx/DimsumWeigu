<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('production_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('ordered')->index();
            $table->string('invoice_reference', 100)->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('ingredient_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_purchase_order_id')->constrained(indexName: 'ipoi_order_fk')->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 18, 8);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->unique(['ingredient_purchase_order_id', 'ingredient_id'], 'ipoi_order_ingredient_unique');
        });
        Schema::create('ingredient_goods_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('ingredient_purchase_order_id')->constrained(indexName: 'igr_po_fk')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->char('payload_hash', 64)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('invoice_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ingredient_goods_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_goods_receiving_id')->constrained(indexName: 'igri_receiving_fk')->restrictOnDelete();
            $table->foreignId('ingredient_purchase_order_item_id')->constrained(indexName: 'igri_po_item_fk')->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('sent_quantity', 15, 3);
            $table->decimal('accepted_quantity', 15, 3)->default(0);
            $table->string('qc_status', 24)->default('good');
            $table->decimal('unit_cost', 18, 8);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['ingredient_goods_receiving_id', 'ingredient_purchase_order_item_id'], 'ingredient_receiving_po_item_unique');
        });
    }

    public function down(): void
    {
        foreach (['ingredient_goods_receiving_items', 'ingredient_goods_receivings', 'ingredient_purchase_order_items', 'ingredient_purchase_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

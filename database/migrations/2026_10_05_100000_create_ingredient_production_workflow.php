<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('production_request_items', 'recipe_version_id')) {
            Schema::table('production_request_items', function (Blueprint $table) {
                $table->foreignId('recipe_version_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
            });
        }
        if (! Schema::hasColumn('ingredient_warehouses', 'reserved_stock')) {
            Schema::table('ingredient_warehouses', fn (Blueprint $table) => $table->decimal('reserved_stock', 15, 3)->default(0)->after('stock'));
        }
        if (! Schema::hasColumn('production_requests', 'idempotency_key')) {
            Schema::table('production_requests', function (Blueprint $table) {
                $table->string('idempotency_key', 96)->nullable();
            });
        }
        if (! Schema::hasIndex('production_requests', 'production_requests_idempotency_key_unique')) {
            Schema::table('production_requests', fn (Blueprint $table) => $table->unique('idempotency_key'));
        }
        if (! Schema::hasColumn('production_requests', 'payload_hash')) {
            Schema::table('production_requests', fn (Blueprint $table) => $table->char('payload_hash', 64)->nullable());
        }
        if (! Schema::hasTable('production_request_materials')) Schema::create('production_request_materials', function (Blueprint $table) {
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
        if (! Schema::hasIndex('production_request_materials', 'prm_request_ingredient_unique')) {
            Schema::table('production_request_materials', fn (Blueprint $table) => $table->unique(['production_request_id', 'ingredient_id'], 'prm_request_ingredient_unique'));
        }

        if (! Schema::hasTable('ingredient_purchase_orders')) Schema::create('ingredient_purchase_orders', function (Blueprint $table) {
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
        if (! Schema::hasTable('ingredient_purchase_order_items')) Schema::create('ingredient_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_purchase_order_id')->constrained(indexName: 'ipoi_order_fk')->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 18, 8);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->unique(['ingredient_purchase_order_id', 'ingredient_id'], 'ipoi_order_ingredient_unique');
        });
        if (! Schema::hasTable('ingredient_goods_receivings')) Schema::create('ingredient_goods_receivings', function (Blueprint $table) {
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
        if (! Schema::hasTable('ingredient_goods_receiving_items')) Schema::create('ingredient_goods_receiving_items', function (Blueprint $table) {
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
        if (! Schema::hasColumn('payables', 'ingredient_goods_receiving_id')) {
            Schema::table('payables', function (Blueprint $table) {
                $table->foreignId('ingredient_goods_receiving_id')->nullable()->constrained()->restrictOnDelete();
            });
        }
        if (! Schema::hasIndex('payables', 'payables_ingredient_goods_receiving_id_unique')) {
            Schema::table('payables', fn (Blueprint $table) => $table->unique('ingredient_goods_receiving_id'));
        }
        if (! Schema::hasIndex('production_orders', 'production_orders_production_request_id_unique')) {
            Schema::table('production_orders', fn (Blueprint $table) => $table->unique('production_request_id'));
        }
        if (! Schema::hasColumn('production_order_items', 'recipe_version_id')) {
            Schema::table('production_order_items', function (Blueprint $table) {
                $table->foreignId('recipe_version_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('production_order_materials')) Schema::create('production_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipe_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_quantity', 15, 3);
            $table->decimal('actual_quantity', 15, 3)->default(0);
            $table->decimal('remaining_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost_snapshot', 18, 8);
            $table->decimal('actual_unit_cost', 18, 8)->nullable();
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['production_order_item_id', 'ingredient_id'], 'pom_item_ingredient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_materials');
        Schema::table('production_order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('recipe_version_id'));
        Schema::table('production_orders', fn (Blueprint $table) => $table->dropUnique(['production_request_id']));
        Schema::table('payables', fn (Blueprint $table) => $table->dropConstrainedForeignId('ingredient_goods_receiving_id'));
        foreach (['ingredient_goods_receiving_items', 'ingredient_goods_receivings', 'ingredient_purchase_order_items', 'ingredient_purchase_orders', 'production_request_materials'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('production_requests', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'payload_hash']);
        });
        Schema::table('production_request_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('recipe_version_id'));
        Schema::table('ingredient_warehouses', fn (Blueprint $table) => $table->dropColumn('reserved_stock'));
    }
};

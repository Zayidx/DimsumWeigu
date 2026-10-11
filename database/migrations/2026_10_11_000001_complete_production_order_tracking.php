<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_order_materials')) {
            Schema::create('production_order_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
                $table->foreignId('production_order_item_id')->constrained()->restrictOnDelete();
                $table->foreignId('recipe_version_id')->constrained()->restrictOnDelete();
                $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
                $table->decimal('planned_quantity', 15, 3);
                $table->decimal('actual_quantity', 15, 3)->default(0);
                $table->decimal('remaining_quantity', 15, 3)->default(0);
                $table->decimal('unit_cost_snapshot', 18, 8)->default(0);
                $table->decimal('actual_unit_cost', 18, 8)->nullable();
                $table->decimal('actual_cost', 15, 2)->default(0);
                $table->timestamps();
            });
        }
        Schema::table('production_orders', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable();
        });
        Schema::table('production_order_items', function (Blueprint $table) {
            $table->decimal('variance_quantity', 15, 3)->nullable();
            $table->decimal('yield_percent', 21, 2)->nullable();
            $table->text('variance_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('production_order_items', function (Blueprint $table) {
            $table->dropColumn(['variance_quantity', 'yield_percent', 'variance_note']);
        });
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
        Schema::dropIfExists('production_order_materials');
    }
};

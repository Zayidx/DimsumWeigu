<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique();
            // Source PAK and PKT share a display symbol; codes identify the unit.
            $table->dropUnique(['symbol']);
            $table->index('symbol');
        });
        Schema::table('ingredients', fn (Blueprint $table) => $table->text('description')->nullable());
        Schema::table('product_units', function (Blueprint $table) {
            $table->decimal('buy_price', 15, 2)->nullable();
            $table->boolean('is_base')->default(false);
            $table->string('sku_suffix', 32)->nullable();
        });
        Schema::table('recipe_versions', function (Blueprint $table) {
            $table->decimal('yield_quantity', 15, 3)->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('recipe_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('base_quantity', 15, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recipe_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('base_quantity');
        });
        Schema::table('recipe_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['yield_quantity', 'notes']);
        });
        Schema::table('product_units', fn (Blueprint $table) => $table->dropColumn(['buy_price', 'is_base', 'sku_suffix']));
        Schema::table('ingredients', fn (Blueprint $table) => $table->dropColumn('description'));
        // Keep symbols non-unique: valid source units can share their display symbol.
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};

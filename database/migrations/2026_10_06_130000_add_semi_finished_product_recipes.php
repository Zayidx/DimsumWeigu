<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_semi_finished')->default(false)->after('is_sellable')->index();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_product_items');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('is_semi_finished'));
    }
};

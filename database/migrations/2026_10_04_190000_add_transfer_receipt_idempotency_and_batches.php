<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('receive_idempotency_key', 96)->nullable()->unique();
        });
        Schema::create('stock_transfer_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_sent', 15, 3);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['stock_transfer_item_id', 'product_batch_id'], 'transfer_batch_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_batch_items');
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropUnique(['receive_idempotency_key']);
            $table->dropColumn('receive_idempotency_key');
        });
    }
};

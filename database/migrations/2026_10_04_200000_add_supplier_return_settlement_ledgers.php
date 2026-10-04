<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->string('request_fingerprint', 64)->nullable();
        });
        Schema::create('supplier_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_return_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('available_amount', 15, 2);
            $table->timestamps();
        });
        Schema::create('supplier_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('method', 32);
            $table->decimal('amount', 15, 2);
            $table->string('reference', 160)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('supplier_refunds');
        Schema::dropIfExists('supplier_credit_notes');
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'request_fingerprint']);
        });
    }
};

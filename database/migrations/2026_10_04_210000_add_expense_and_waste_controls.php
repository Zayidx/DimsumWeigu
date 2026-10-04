<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
        });
        Schema::table('outlet_expenses', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('method', 32)->default('bank-transfer');
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->string('request_fingerprint', 64)->nullable();
        });
        Schema::table('outlet_waste_records', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->string('request_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outlet_waste_records', fn (Blueprint $table) => $table->dropColumn(['unit_cost', 'cost_total', 'idempotency_key', 'request_fingerprint']));
        Schema::table('outlet_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_category_id');
            $table->dropConstrainedForeignId('cashier_shift_id');
            $table->dropColumn(['method', 'idempotency_key', 'request_fingerprint']);
        });
        Schema::dropIfExists('expense_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_finance_cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->decimal('variance_amount', 15, 2)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->text('verification_note')->nullable();
            $table->string('idempotency_key', 96)->unique();
            $table->char('payload_hash', 64);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('finance_cash_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_finance_cash_handover_id')->unique('finance_cash_handover_unique')->constrained('warehouse_finance_cash_handovers', 'id', 'finance_cash_handover_fk')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_cash_ledgers');
        Schema::dropIfExists('warehouse_finance_cash_handovers');
    }
};

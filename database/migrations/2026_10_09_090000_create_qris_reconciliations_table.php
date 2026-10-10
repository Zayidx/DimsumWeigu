<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qris_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->date('sales_date');
            $table->date('settlement_date');
            $table->decimal('reported_gross', 15, 2);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('net_received', 15, 2);
            $table->decimal('pos_gross_at_reconciliation', 15, 2);
            $table->string('merchant_reference', 160)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['outlet_id', 'sales_date']);
            $table->index(['outlet_id', 'settlement_date']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('qris_reconciliations');
    }
};

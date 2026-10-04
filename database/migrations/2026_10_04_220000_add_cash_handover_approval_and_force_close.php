<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('receiving_shift_id')->nullable()->constrained('cashier_shifts')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('request_fingerprint', 64)->nullable();
        });
        Schema::table('cashier_shifts', fn (Blueprint $table) => $table->boolean('is_force_closed')->default(false));
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', fn (Blueprint $table) => $table->dropColumn('is_force_closed'));
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('receiving_shift_id');
            $table->dropColumn(['approved_at', 'request_fingerprint']);
        });
    }
};

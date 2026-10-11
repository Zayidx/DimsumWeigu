<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->decimal('variance_amount', 15, 2)->nullable();
            $table->text('verification_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->dropColumn(['actual_amount', 'variance_amount', 'verification_note']);
        });
    }
};

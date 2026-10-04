<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_cash_movements', fn (Blueprint $table) => $table->string('idempotency_key', 96)->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('shift_cash_movements', fn (Blueprint $table) => $table->dropColumn('idempotency_key'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', fn (Blueprint $table) => $table->decimal('buy_price', 18, 8)->default(0)->change());
    }

    public function down(): void
    {
        // Cost precision is intentionally retained when rolling back to avoid silent rounding.
    }
};

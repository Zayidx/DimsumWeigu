<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('open')->index();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['warehouse_id', 'opened_at']);
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->foreignId('production_shift_id')->nullable()->after('production_request_id')->constrained('production_shifts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_shift_id');
        });

        Schema::dropIfExists('production_shifts');
    }
};

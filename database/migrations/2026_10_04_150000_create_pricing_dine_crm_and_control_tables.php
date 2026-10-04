<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamps();
            $table->unique(['price_list_id', 'product_id', 'unit_id']);
        });
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32);
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('minimum_subtotal', 15, 2)->default(0);
            $table->boolean('stackable')->default(false);
            $table->json('conditions')->nullable();
            $table->timestamps();
        });
        Schema::create('pricing_rule_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->boolean('is_reward')->default(false);
            $table->timestamps();
        });
        Schema::create('pricing_rule_buy_get_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->string('kind', 12);
            $table->timestamps();
        });
        Schema::create('pricing_rule_qty_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_rule_id')->constrained()->cascadeOnDelete();
            $table->decimal('minimum_quantity', 15, 3);
            $table->decimal('discount_value', 15, 2);
            $table->string('discount_type', 16)->default('fixed');
            $table->timestamps();
            $table->unique(['pricing_rule_id', 'minimum_quantity']);
        });

        Schema::create('customer_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 48)->unique();
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('minimum_subtotal', 15, 2)->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
        Schema::create('loyalty_point_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->decimal('points', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16)->default('manual');
            $table->json('rules')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('customer_segment_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_segment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            $table->unique(['customer_segment_id', 'customer_id'], 'segment_customer_unique');
        });
        Schema::create('customer_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_segment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('channel', 24)->default('whatsapp');
            $table->text('message');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('queued')->index();
            $table->string('provider_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->string('idempotency_key', 96)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dine_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
        });
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dine_area_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->decimal('position_x', 8, 3)->default(0);
            $table->decimal('position_y', 8, 3)->default(0);
            $table->string('status', 20)->default('available')->index();
            $table->string('public_token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['dine_area_id', 'code']);
        });
        Schema::create('dine_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 48)->unique();
            $table->foreignId('dining_table_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->string('status', 20)->default('submitted')->index();
            $table->string('payment_method', 24)->default('pay_at_counter');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('customer_note')->nullable();
            $table->text('staff_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('idempotency_key', 96)->unique();
            $table->timestamp('submitted_at')->useCurrent()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('dine_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dine_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope_key', 48)->default('global');
            $table->string('provider', 32);
            $table->string('mode', 16)->default('sandbox');
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->timestamps();
            $table->unique(['scope_key', 'provider']);
        });
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('provider', 32)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('requires_confirmation')->default(false);
            $table->timestamps();
        });
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('operational_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
            $table->index(['outlet_id', 'created_at']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 96)->index();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['outlet_id', 'created_at']);
        });
        Schema::create('discount_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('requested_discount', 15, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'discount_approval_logs', 'audit_logs', 'operational_notifications', 'bank_accounts', 'payment_methods',
            'payment_settings', 'dine_order_items', 'dine_orders', 'dining_tables', 'dine_areas',
            'customer_campaign_logs', 'customer_campaigns', 'customer_segment_memberships', 'customer_segments',
            'loyalty_point_histories', 'customer_vouchers', 'pricing_rule_qty_breaks', 'pricing_rule_buy_get_items',
            'pricing_rule_bundle_items', 'pricing_rules', 'price_list_items', 'price_lists',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

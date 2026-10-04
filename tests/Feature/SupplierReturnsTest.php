<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\SupplierReturnsIndex;
use App\Models\CashierShift;
use App\Models\GoodsReceiving;
use App\Models\Outlet;
use App\Models\Payable;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierReturn;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Finance\SettlementService;
use App\Services\Purchasing\PurchasingService;
use App\Services\Purchasing\SupplierReturnService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SupplierReturnsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private CashierShift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::create(['name' => 'Return operator', 'email' => 'returns@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $user->assignRole('super-admin');
        $outlet = Outlet::create(['code' => 'RETURN', 'slug' => 'return', 'name' => 'Return outlet', 'is_active' => true, 'is_sales_enabled' => true]);
        $user->outlets()->attach($outlet->id, ['is_default' => true]);
        $this->warehouse = Warehouse::create(['outlet_id' => $outlet->id, 'code' => 'RETURN-WH', 'name' => 'Return warehouse', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true]);
        $unit = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs', 'dimension' => 'count', 'is_active' => true]);
        $this->product = Product::create(['unit_id' => $unit->id, 'sku' => 'RETURN-1', 'title' => 'Return product', 'buy_price' => 1000, 'sell_price' => 2000, 'is_active' => true, 'track_stock' => true]);
        $this->supplier = Supplier::create(['code' => 'RETURN-SUP', 'name' => 'Supplier', 'is_active' => true]);
        $this->shift = CashierShift::create(['user_id' => $user->id, 'open_user_id' => $user->id, 'outlet_id' => $outlet->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'open', 'opening_cash' => 0, 'expected_cash' => 0, 'opened_at' => now()]);
        $this->actingAs($user)->withSession(['active_outlet_id' => $outlet->id]);
    }

    private function receipt(int $quantity = 3, bool $batch = false, string $cost = '1000', string $tax = '11'): GoodsReceiving
    {
        $this->product->update(['track_batches' => $batch]);
        $service = app(PurchasingService::class);
        $order = $service->createDraft($this->supplier->id, $this->warehouse->id, [['product_id' => $this->product->id, 'quantity' => (string) $quantity, 'unit_cost' => $cost, 'tax_rate' => $tax]]);
        $service->order($order->id);

        return $service->receive($order->id, [$order->items->first()->id => ['quantity' => (string) $quantity, 'accepted' => (string) $quantity, 'batch_number' => 'B-'.Str::random(8), 'expires_at' => today()->addDays(5)->toDateString()]], (string) Str::uuid())->load('items');
    }

    public function test_return_approval_offsets_unpaid_invoice_and_debits_stock_once(): void
    {
        $receipt = $this->receipt();
        $service = app(SupplierReturnService::class);
        $lines = [['receiving_item_id' => $receipt->items->first()->id, 'quantity' => '1']];
        $key = (string) Str::uuid();
        $return = $service->draft($receipt->id, $lines, 'Damaged packaging', 'credit', $key);
        $this->assertSame($return->id, $service->draft($receipt->id, $lines, 'Damaged packaging', 'credit', $key)->id);
        $service->approve($return->id);
        $service->complete($return->id);
        $service->complete($return->id);
        $this->assertDatabaseHas('product_warehouses', ['warehouse_id' => $this->warehouse->id, 'stock' => '2.000']);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'stock' => '2.000']);
        $this->assertDatabaseHas('payables', ['goods_receiving_id' => $receipt->id, 'amount' => '3330.00', 'paid_amount' => '1110.00', 'status' => 'partial']);
        $this->assertDatabaseCount('supplier_credit_notes', 0);
        $this->assertDatabaseCount('payable_payments', 1);
    }

    public function test_paid_receipt_creates_credit_and_credit_can_settle_same_supplier_once(): void
    {
        $receipt = $this->receipt();
        $payable = Payable::where('goods_receiving_id', $receipt->id)->firstOrFail();
        app(SettlementService::class)->payPayable($payable->id, '3330', 'bank-transfer', (string) Str::uuid(), 'Bank payment');
        $service = app(SupplierReturnService::class);
        $return = $service->draft($receipt->id, [['receiving_item_id' => $receipt->items->first()->id, 'quantity' => '1']], 'Wrong item', 'credit', (string) Str::uuid());
        $service->approve($return->id);
        $service->complete($return->id);
        $credit = SupplierCreditNote::firstOrFail();
        $next = $this->receipt(1);
        $nextPayable = Payable::where('goods_receiving_id', $next->id)->firstOrFail();
        $key = (string) Str::uuid();
        $payment = $service->applyCredit($credit->id, $nextPayable->id, '1110', $key);
        $this->assertSame($payment->id, $service->applyCredit($credit->id, $nextPayable->id, '1110', $key)->id);
        $this->assertSame('0.00', $credit->fresh()->available_amount);
        $this->assertSame('paid', $nextPayable->fresh()->status);
    }

    public function test_pending_requests_reserve_return_quantity_and_cancellation_releases_it(): void
    {
        $receipt = $this->receipt(1);
        $service = app(SupplierReturnService::class);
        $lines = [['receiving_item_id' => $receipt->items->first()->id, 'quantity' => '1']];
        $first = $service->draft($receipt->id, $lines, 'Return', 'credit', (string) Str::uuid());
        try {
            $service->draft($receipt->id, $lines, 'Duplicate', 'credit', (string) Str::uuid());
            $this->fail('Excess return must be blocked.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('supplier_returns', 1);
        }
        $service->cancel($first->id);
        $this->assertSame('draft', $service->draft($receipt->id, $lines, 'Replacement', 'credit', (string) Str::uuid())->status);
    }

    public function test_expired_source_batch_can_be_returned_without_using_another_batch(): void
    {
        $receipt = $this->receipt(2, true);
        $item = $receipt->items->first();
        $item->batch->update(['expires_at' => today()->subDay()]);
        $service = app(SupplierReturnService::class);
        $return = $service->draft($receipt->id, [['receiving_item_id' => $item->id, 'quantity' => '1']], 'Expired', 'credit', (string) Str::uuid());
        $service->approve($return->id);
        $service->complete($return->id);
        $this->assertSame('1.000', $item->batch->fresh()->quantity_remaining);
        $this->assertDatabaseHas('inventory_ledgers', ['product_batch_id' => $item->product_batch_id, 'type' => 'supplier_return', 'quantity_delta' => '-1.000']);
    }

    public function test_cash_supplier_refund_updates_shift_and_cash_ledger_once(): void
    {
        $receipt = $this->receipt(1);
        $payable = Payable::where('goods_receiving_id', $receipt->id)->firstOrFail();
        app(SettlementService::class)->payPayable($payable->id, '1110', 'bank-transfer', (string) Str::uuid());
        $service = app(SupplierReturnService::class);
        $return = $service->draft($receipt->id, [['receiving_item_id' => $receipt->items->first()->id, 'quantity' => '1']], 'Refund agreed', 'refund', (string) Str::uuid());
        $service->approve($return->id);
        $service->complete($return->id, 'cash');
        $service->complete($return->id, 'cash');
        $this->assertSame('1110.00', $this->shift->fresh()->expected_cash);
        $this->assertDatabaseCount('supplier_refunds', 1);
        $this->assertDatabaseHas('warehouse_cash_ledgers', ['type' => 'supplier_refund', 'amount' => '1110.00', 'balance_after' => '1110.00']);
    }

    public function test_staff_screen_completes_return_workflow(): void
    {
        $receipt = $this->receipt(1);
        $this->get('/supplier-returns')->assertOk()->assertSee('Retur pemasok');
        $screen = Livewire::test(SupplierReturnsIndex::class)->call('create')->set('receivingId', $receipt->id)
            ->set('quantities.'.$receipt->items->first()->id, '1')->set('reason', 'Damaged')->call('saveDraft')->assertHasNoErrors();
        $return = SupplierReturn::firstOrFail();
        $screen->call('approve', $return->id)->call('beginCompletion', $return->id)->call('complete')->assertHasNoErrors();
        $this->assertSame('completed', $return->fresh()->status);
        $this->assertSame('paid', Payable::where('goods_receiving_id', $receipt->id)->first()->status);
    }

    public function test_cash_refund_without_shift_rolls_back_stock_and_finance(): void
    {
        $receipt = $this->receipt(1);
        $payable = Payable::where('goods_receiving_id', $receipt->id)->firstOrFail();
        app(SettlementService::class)->payPayable($payable->id, '1110', 'bank-transfer', (string) Str::uuid());
        $service = app(SupplierReturnService::class);
        $return = $service->draft($receipt->id, [['receiving_item_id' => $receipt->items->first()->id, 'quantity' => '1']], 'Return refund', 'refund', (string) Str::uuid());
        $service->approve($return->id);
        $this->shift->update(['status' => 'closed', 'open_user_id' => null]);
        try {
            $service->complete($return->id, 'cash');
            $this->fail('Refund requires an open shift.');
        } catch (ValidationException) {
            $this->assertSame('approved', $return->fresh()->status);
            $this->assertSame('1.000', $this->product->fresh()->stock);
            $this->assertDatabaseCount('supplier_refunds', 0);
            $this->assertDatabaseMissing('inventory_ledgers', ['type' => 'supplier_return']);
        }
    }

    public function test_forged_return_from_another_outlet_is_hidden(): void
    {
        $receipt = $this->receipt(1);
        $other = Outlet::create(['code' => 'OTHER', 'slug' => 'other', 'name' => 'Other', 'is_active' => true]);
        session(['active_outlet_id' => $other->id]);
        try {
            app(SupplierReturnService::class)->draft($receipt->id, [['receiving_item_id' => $receipt->items->first()->id, 'quantity' => '1']], 'Forged', 'credit', (string) Str::uuid());
            $this->fail('Cross-outlet return must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
            $this->assertDatabaseCount('supplier_returns', 0);
        }
    }

    public function test_split_returns_never_refund_more_than_original_rounded_invoice_value(): void
    {
        $receipt = $this->receipt(3, false, '0.05', '10');
        $service = app(SupplierReturnService::class);
        for ($index = 0; $index < 3; $index++) {
            $return = $service->draft($receipt->id, [['receiving_item_id' => $receipt->items->first()->id, 'quantity' => '1']], 'Partial return', 'credit', (string) Str::uuid());
            $service->approve($return->id);
            $service->complete($return->id);
        }
        $payable = Payable::where('goods_receiving_id', $receipt->id)->firstOrFail();
        $this->assertSame('0.17', $payable->amount);
        $this->assertSame('0.17', $payable->paid_amount);
        $this->assertDatabaseCount('supplier_credit_notes', 0);
        $this->assertSame('0.000', $this->product->fresh()->stock);
    }
}

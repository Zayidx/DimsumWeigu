<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductWarehouse;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobilePosApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Outlet $outlet;

    private Warehouse $warehouse;

    private Product $product;

    private Unit $packageUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::create(['name' => 'API cashier', 'email' => 'api-cashier@example.test', 'password' => 'Test-password-123!', 'is_active' => true, 'must_change_password' => false]);
        $this->user->assignRole('cashier');
        $this->outlet = Outlet::create(['code' => 'MOBILE', 'slug' => 'mobile', 'name' => 'Mobile outlet', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->user->outlets()->attach($this->outlet->id, ['is_default' => true]);
        $this->warehouse = Warehouse::create(['outlet_id' => $this->outlet->id, 'code' => 'MOBILE-WH', 'name' => 'Mobile warehouse', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true]);
        $unit = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs', 'dimension' => 'count', 'is_active' => true]);
        $this->product = Product::create(['unit_id' => $unit->id, 'sku' => 'API-1', 'barcode' => '12345', 'title' => 'API dimsum', 'buy_price' => 1000, 'sell_price' => 2000, 'tax_rate' => 0, 'stock' => 10, 'is_active' => true, 'is_sellable' => true, 'track_stock' => true]);
        $this->packageUnit = Unit::create(['name' => 'Paket isi 2', 'symbol' => 'paket 2', 'dimension' => 'count', 'is_active' => true]);
        ProductUnit::create(['product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id, 'conversion_factor' => 2, 'sell_price' => 4000, 'is_base' => false, 'is_default' => false]);
        ProductWarehouse::create(['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => 10, 'reserved_stock' => 0]);
        foreach (['cash', 'pay-later'] as $code) {
            PaymentMethod::create(['code' => $code, 'name' => $code, 'is_enabled' => true]);
        }
        $this->bearer($this->user, ['pos-access']);
    }

    private function bearer(User $user, array $abilities): void
    {
        Auth::forgetGuards();
        $this->withToken($user->createToken('API test', $abilities)->plainTextToken);
    }

    private function url(string $path): string
    {
        return '/api/v1/pos/'.$path.'?outlet_id='.$this->outlet->id;
    }

    private function open(): void
    {
        $this->postJson($this->url('shift/open'), ['warehouse_id' => $this->warehouse->id, 'opening_cash' => '1000'])->assertCreated();
    }

    private function preparedCart(): array
    {
        $this->open();
        $cart = $this->getJson($this->url('cart'))->assertOk()->json('data');
        $response = $this->postJson($this->url('carts/'.$cart['id'].'/items'), ['product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id])->assertOk();
        $item = $response->json('data.items.0.id');
        $this->patchJson($this->url('carts/'.$cart['id'].'/items/'.$item), ['quantity' => '2'])->assertOk()->assertJsonPath('data.items.0.quantity', '2.000');

        return $cart;
    }

    public function test_mobile_api_requires_a_package_unit_for_sales(): void
    {
        $this->open();
        $cart = $this->getJson($this->url('cart'))->assertOk()->json('data');
        $this->postJson($this->url('carts/'.$cart['id'].'/items'), ['product_id' => $this->product->id])
            ->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_mobile_cash_sale_sync_replay_history_and_shift_close(): void
    {
        $cart = $this->preparedCart();
        $this->getJson($this->url('catalog').'&barcode=12345')->assertOk()->assertJsonPath('data.data.0.id', $this->product->id);
        $payload = ['method' => 'cash', 'received' => '9000', 'client_uuid' => $cart['uuid'], 'unit_price' => 1, 'grand_total' => 2];
        $sale = $this->postJson($this->url('carts/'.$cart['id'].'/checkout'), $payload)->assertOk()->assertJsonPath('data.grand_total', '8000.00')->assertJsonPath('data.change_total', '1000.00')->json('data');
        $this->postJson($this->url('sync'), $payload + ['cart_id' => $cart['id']])->assertOk()->assertJsonPath('data.id', $sale['id']);
        $this->getJson($this->url('transactions'))->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson($this->url('transactions/'.$sale['id']))->assertOk()->assertJsonPath('data.items.0.quantity', '2.000');
        $this->postJson($this->url('shift/close'), ['actual_cash' => '9000', 'password' => 'wrong-password'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson($this->url('shift/close'), ['actual_cash' => '9000', 'password' => 'Test-password-123!'])->assertOk()->assertJsonPath('data.expected_cash', '9000.00');
        // Acknowledging a successful checkout remains possible after closing its shift.
        $this->postJson($this->url('sync'), $payload + ['cart_id' => $cart['id']])->assertOk()->assertJsonPath('data.id', $sale['id']);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('transaction_tenders', 1);
        $this->assertSame('6.000', $this->product->fresh()->stock);
        $this->assertDatabaseHas('warehouse_cash_ledgers', ['type' => 'sale_cash', 'amount' => 8000, 'balance_after' => 9000]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'transaction.completed', 'outlet_id' => $this->outlet->id]);
    }

    public function test_hold_resume_remove_and_discard_use_server_cart(): void
    {
        $cart = $this->preparedCart();
        $this->postJson($this->url('carts/'.$cart['id'].'/hold'), ['label' => 'Table one'])->assertOk()->assertJsonPath('data.status', 'held');
        $this->getJson($this->url('held'))->assertOk()->assertJsonPath('data.0.id', $cart['id']);
        $new = $this->getJson($this->url('cart'))->assertOk()->json('data');
        $this->assertNotSame($cart['id'], $new['id']);
        $resumed = $this->postJson($this->url('carts/'.$cart['id'].'/resume'))->assertOk()->assertJsonPath('data.status', 'active');
        $item = $resumed->json('data.items.0.id');
        $this->deleteJson($this->url('carts/'.$cart['id'].'/items/'.$item))->assertOk()->assertJsonCount(0, 'data.items');
        $this->postJson($this->url('carts/'.$cart['id'].'/items'), ['product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id])->assertOk();
        $this->postJson($this->url('carts/'.$cart['id'].'/hold'), ['label' => 'Cancel'])->assertOk();
        $this->deleteJson($this->url('carts/'.$cart['id'].'/hold'))->assertOk();
        $this->assertSame('discarded', Cart::find($cart['id'])->status);
        $this->assertSame('10.000', $this->product->fresh()->stock);
    }

    public function test_token_ability_live_permission_inactive_and_password_change_are_enforced(): void
    {
        $this->bearer($this->user, ['user:read']);
        $this->getJson($this->url('shift'))->assertForbidden();
        $this->bearer($this->user, ['pos-access']);
        $this->user->syncRoles([]);
        Auth::forgetGuards();
        $this->getJson($this->url('shift'))->assertForbidden();
        $this->user->assignRole('cashier');
        $this->user->update(['is_active' => false]);
        Auth::forgetGuards();
        $this->getJson($this->url('shift'))->assertForbidden();
        $this->user->update(['is_active' => true, 'must_change_password' => true]);
        Auth::forgetGuards();
        $this->getJson($this->url('shift'))->assertForbidden();
    }

    public function test_cross_outlet_and_other_cashiers_cart_and_history_are_hidden(): void
    {
        $cart = $this->preparedCart();
        $saleId = $this->postJson($this->url('carts/'.$cart['id'].'/checkout'), ['client_uuid' => $cart['uuid'], 'method' => 'cash', 'received' => '8000'])->assertOk()->json('data.id');
        $other = User::create(['name' => 'Other cashier', 'email' => 'other-api@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $other->assignRole('cashier');
        $other->outlets()->attach($this->outlet->id, ['is_default' => true]);
        $this->bearer($other, ['pos-access']);
        $this->postJson($this->url('carts/'.$cart['id'].'/items'), ['product_id' => $this->product->id])->assertNotFound();
        $outlet = Outlet::create(['code' => 'FORBIDDEN', 'slug' => 'forbidden', 'name' => 'Forbidden outlet', 'is_active' => true]);
        $this->getJson('/api/v1/pos/shift?outlet_id='.$outlet->id)->assertNotFound();
        $this->getJson('/api/v1/pos/shift')->assertUnprocessable()->assertJsonValidationErrors('outlet_id');
        $this->getJson($this->url('transactions'))->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson($this->url('transactions/'.$saleId))->assertNotFound();
    }

    public function test_uuid_payment_conflict_and_failed_stock_checkout_do_not_duplicate_sale(): void
    {
        $cart = $this->preparedCart();
        $path = $this->url('carts/'.$cart['id'].'/checkout');
        $payload = ['method' => 'cash', 'received' => '4000', 'client_uuid' => $cart['uuid']];
        $this->postJson($path, array_replace($payload, ['client_uuid' => (string) Str::uuid()]))->assertUnprocessable()->assertJsonValidationErrors('client_uuid');
        $payload['received'] = '8000';
        ProductWarehouse::where('warehouse_id', $this->warehouse->id)->update(['stock' => 3]);
        $this->product->update(['stock' => 3]);
        $this->postJson($path, $payload)->assertUnprocessable()->assertJsonValidationErrors('stock');
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_tenders', 0);
        ProductWarehouse::where('warehouse_id', $this->warehouse->id)->update(['stock' => 10]);
        $this->product->update(['stock' => 10]);
        $this->postJson($path, $payload)->assertOk();
        $this->postJson($path, array_replace($payload, ['received' => '9000']))->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('6.000', $this->product->fresh()->stock);
    }

    public function test_prepared_cart_with_closed_shift_cannot_create_new_offline_sale(): void
    {
        $cart = $this->preparedCart();
        $this->postJson($this->url('shift/close'), ['actual_cash' => '1000', 'password' => 'Test-password-123!'])->assertOk();
        $this->postJson($this->url('sync'), ['cart_id' => $cart['id'], 'client_uuid' => $cart['uuid'], 'method' => 'cash', 'received' => '8000'])->assertForbidden();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('10.000', $this->product->fresh()->stock);
    }

    public function test_pay_later_checkout_uses_customer_credit_and_permission_checks(): void
    {
        $customer = Customer::create(['code' => 'API-CUST', 'name' => 'Member', 'is_active' => true, 'credit_limit' => 3000]);
        $cart = $this->preparedCart();
        $this->patchJson($this->url('carts/'.$cart['id']), ['customer_id' => $customer->id, 'notes' => 'Delivery'])->assertOk()->assertJsonPath('data.customer_id', $customer->id);
        $payload = ['method' => 'pay-later', 'client_uuid' => $cart['uuid']];
        $this->postJson($this->url('carts/'.$cart['id'].'/checkout'), $payload)->assertUnprocessable()->assertJsonValidationErrors('customer');
        $customer->update(['credit_limit' => 10000]);
        $this->postJson($this->url('carts/'.$cart['id'].'/checkout'), $payload)->assertOk()->assertJsonPath('data.payment_status', 'unpaid')->assertJsonPath('data.paid_total', '0.00');
        $this->assertDatabaseHas('receivables', ['customer_id' => $customer->id, 'amount' => 8000, 'status' => 'unpaid']);
        $this->assertDatabaseCount('customer_credits', 1);
    }

    public function test_resume_rechecks_changed_stock_and_retains_hold_when_insufficient(): void
    {
        $cart = $this->preparedCart();
        $this->postJson($this->url('carts/'.$cart['id'].'/hold'), ['label' => 'Waiting'])->assertOk();
        ProductWarehouse::where('warehouse_id', $this->warehouse->id)->update(['stock' => 1]);
        $this->product->update(['stock' => 1]);
        $this->postJson($this->url('carts/'.$cart['id'].'/resume'))->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertSame('held', Cart::find($cart['id'])->status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_close_password_attempts_have_separate_limit_from_normal_pos_requests(): void
    {
        $this->open();
        for ($i = 0; $i < 12; $i++) {
            $this->getJson($this->url('shift'))->assertOk();
        }
        for ($i = 0; $i < 8; $i++) {
            $this->postJson($this->url('shift/close'), ['actual_cash' => '1000', 'password' => 'wrong-password'])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson($this->url('shift/close'), ['actual_cash' => '1000', 'password' => 'wrong-password'])->assertTooManyRequests();
        $this->getJson($this->url('shift'))->assertOk()->assertJsonPath('data.status', 'open');
    }
}

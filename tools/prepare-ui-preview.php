<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Disposable UI preview only. Never bootstrap against the operational connection.
require __DIR__.'/../vendor/autoload.php';
$previewPassword = trim((string) (getenv('UI_AUDIT_PASSWORD') ?: stream_get_contents(STDIN)));
$preview = realpath(__DIR__.'/../storage/app/ui-audit/preview.sqlite');
if (! $preview || $previewPassword === '') {
    throw new RuntimeException('Create storage/app/ui-audit/preview.sqlite and provide UI_AUDIT_PASSWORD.');
}
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$preview);
putenv('APP_ENV=local');
$_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $preview;
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'sqlite' || realpath($db->getDatabaseName()) !== $preview) {
    throw new RuntimeException('Refusing to modify a database outside the disposable preview.');
}
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
$outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
$warehouse = Warehouse::where('code', 'WH-GAL-BP')->firstOrFail();
foreach (User::all() as $user) {
    $user->forceFill(['password' => Hash::make($previewPassword)])->save();
}
$cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
$shift = CashierShift::firstOrCreate(['open_user_id' => $cashier->id, 'status' => 'open'], [
    'user_id' => $cashier->id, 'outlet_id' => $outlet->id, 'warehouse_id' => $warehouse->id,
    'opening_cash' => 100000, 'expected_cash' => 100000, 'opened_at' => now(),
]);
$cart = Cart::firstOrCreate(['user_id' => $cashier->id, 'cashier_shift_id' => $shift->id, 'status' => 'active'], [
    'uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'warehouse_id' => $warehouse->id,
]);
foreach (Product::all() as $product) {
    ProductWarehouse::firstOrCreate(['product_id' => $product->id, 'warehouse_id' => $warehouse->id], ['stock' => 100]);
    $product->update(['stock' => 100]);
}
$product = Product::where('sku', 'TEST-DOR')->firstOrFail();
$portion = Unit::where('code', 'P4')->firstOrFail();
CartItem::firstOrCreate(['cart_id' => $cart->id, 'product_id' => $product->id, 'unit_id' => $portion->id], ['quantity' => 2]);
for ($index = 1; $index <= 24; $index++) {
    Customer::firstOrCreate(['email' => 'preview'.$index.'@example.invalid'], ['code' => 'PREVIEW-'.$index, 'name' => 'Pelanggan preview '.$index, 'phone' => '0812345678', 'is_active' => true]);
}
echo "Disposable preview prepared: SQLite, 13 stocked products, package cart and 24 customers. Operational MySQL unchanged.\n";

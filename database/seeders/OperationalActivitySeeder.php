<?php

namespace Database\Seeders;

use App\Models\CashHandover;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Payable;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductionRequest;
use App\Models\Receivable;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashier\CartService;
use App\Services\Cashier\CashHandoverService;
use App\Services\Cashier\CashierShiftService;
use App\Services\Cashier\CheckoutService;
use App\Services\Finance\SettlementService;
use App\Services\Inventory\StockTransferService;
use App\Services\Production\ProductionOrderService;
use App\Services\Production\ProductionRequestService;
use App\Services\Purchasing\PurchasingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/** Run explicitly with db:seed --class=OperationalActivitySeeder; never from DatabaseSeeder. */
class OperationalActivitySeeder extends Seeder
{
    private const PREFIX = 'operational-demo-v2';

    private const OUTLETS = [
        'GAL-BP' => 'WH-GAL-BP',
        'GAL-HER' => 'WH-GAL-HER',
        'PEK-JAYA' => 'WH-PEK-JAYA',
        'JL-RAYA-PEK' => 'WH-RAYA-PEK',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('OperationalActivitySeeder hanya boleh dijalankan di local atau testing.');
        }

        $central = Warehouse::query()->where('code', 'PUSAT')->where('type', 'central')->where('is_active', true)->first();
        if (! $central || Outlet::query()->whereIn('code', array_keys(self::OUTLETS))->where('is_active', true)->count() !== count(self::OUTLETS)) {
            throw new RuntimeException('Jalankan seeder utama terlebih dahulu agar gudang pusat dan empat outlet tersedia.');
        }

        $finalKey = self::PREFIX.':finance-pickup';
        if ($completed = CashHandover::query()->where('idempotency_key', $finalKey)->first()) {
            if ($completed->status !== 'confirmed') {
                throw new RuntimeException('Simulasi sebelumnya belum selesai. Periksa serah terima kas sebelum mengulang.');
            }
            $this->seedCashierRestock($central);
            $this->seedAllMenuComponentStock($central);
            $this->command?->info('Simulasi penjualan sudah ada; stok dimsum, lumpia, dan Chiquro tersedia di setiap outlet.');

            return;
        }
        if (ProductionRequest::query()->where('idempotency_key', self::PREFIX.':request:GAL-BP')->exists()) {
            throw new RuntimeException('Simulasi sebelumnya belum selesai. Periksa aktivitas sebelum mengulang.');
        }
        $previousUser = Auth::user();
        $previousOutlet = session('active_outlet_id');
        $previousConfirmation = session('auth.password_confirmed_at');

        try {
            DB::transaction(function () use ($central, $finalKey): void {
                $this->call(TestDataSeeder::class);
                $outlets = Outlet::query()->whereIn('code', array_keys(self::OUTLETS))->get()->keyBy('code');
                $warehouse = $this->actor('warehouse', $outlets->pluck('id')->all());
                $finance = $this->actor('finance', $outlets->pluck('id')->all());
                $cashiers = [];
                foreach ($outlets as $outlet) {
                    $cashiers[$outlet->code] = $this->actor('cashier', [$outlet->id], $outlet->code);
                }

                $supplier = Supplier::query()->firstOrCreate(['code' => 'DEMO-OPERASIONAL'], ['name' => 'Pemasok Simulasi Operasional', 'is_active' => true]);
                $this->seedQualityControl($central, $supplier, $warehouse, $finance);
                $creditCustomer = Customer::query()->firstOrCreate(['code' => 'DEMO-PIUTANG'], [
                    'name' => 'Pelanggan Kredit Simulasi', 'email' => 'piutang@example.invalid',
                    'credit_limit' => '5000000', 'is_active' => true,
                ]);

                $production = app(ProductionRequestService::class);
                $orders = app(ProductionOrderService::class);
                $transfers = app(StockTransferService::class);
                $shifts = app(CashierShiftService::class);
                $carts = app(CartService::class);
                $checkout = app(CheckoutService::class);
                $cash = app(CashHandoverService::class);
                $payLaterMethod = PaymentMethod::query()->firstOrCreate(['code' => 'pay-later'], [
                    'name' => 'Bayar nanti', 'provider' => null, 'is_enabled' => true, 'requires_confirmation' => true,
                ]);
                $payLaterWasCreated = $payLaterMethod->wasRecentlyCreated;
                $payLaterWasEnabled = $payLaterMethod->is_enabled;
                if (! $payLaterWasEnabled) {
                    $payLaterMethod->forceFill(['is_enabled' => true])->save();
                }
                $products = Product::query()->whereIn('sku', ['TEST-DME', 'TEST-LME', 'TEST-DPL', 'TEST-DWO'])->get()->keyBy('sku');
                if ($products->count() !== 4) {
                    throw new RuntimeException('Menu uji tidak lengkap setelah TestDataSeeder.');
                }

                foreach (self::OUTLETS as $code => $warehouseCode) {
                    $outlet = $outlets[$code];
                    $branch = Warehouse::query()->where('code', $warehouseCode)->where('outlet_id', $outlet->id)->where('is_sales_enabled', true)->firstOrFail();
                    $this->seedOutletMenuIngredients($branch, $warehouse);

                    $this->as($warehouse, $outlet);
                    $request = $production->create($warehouse, $outlet->id, $central->id, [
                        ['product_id' => $products['TEST-DPL']->id, 'quantity' => '100'],
                        ['product_id' => $products['TEST-DWO']->id, 'quantity' => '100'],
                    ], '[SIMULASI] Produksi dimsum wortel dan beef setengah jadi untuk '.$outlet->name, self::PREFIX.':request:'.$code);

                    $this->as($finance, $outlet);
                    $purchase = in_array($request->status, ['requested', 'purchasing'], true)
                        ? $production->approveAndPurchase($finance, $request->id, $supplier->id)
                        : null;
                    if ($purchase) {
                        $this->as($warehouse, $outlet);
                        $production->receive($warehouse, $purchase->id, $purchase->items->map(fn ($item) => [
                            'purchase_order_item_id' => $item->id,
                            'sent_quantity' => (string) $item->quantity,
                            'accepted_quantity' => (string) $item->quantity,
                            'qc_status' => 'good',
                            'notes' => '[SIMULASI] Bahan diterima setelah pemeriksaan QC.',
                        ])->all(), 'SIM-'.$code, '[SIMULASI] Penerimaan kekurangan bahan produksi', self::PREFIX.':material-receive:'.$code);
                    }

                    $this->as($warehouse, $outlet);
                    $order = $orders->startFromRequest($warehouse, $request->id);
                    $orders->complete($warehouse, $order->id,
                        $order->items->flatMap->materials->map(fn ($row) => ['production_order_material_id' => $row->id, 'actual_quantity' => (string) $row->planned_quantity])->all(),
                        $order->items->map(fn ($row) => ['production_order_item_id' => $row->id, 'actual_output' => (string) $row->planned_quantity])->all(),
                    );

                    $transfer = $transfers->createDraft($central->id, $branch->id, [
                        ['product_id' => $products['TEST-DPL']->id, 'quantity' => '100'],
                        ['product_id' => $products['TEST-DWO']->id, 'quantity' => '100'],
                    ], '[SIMULASI] Kirim komponen dimsum setengah jadi ke '.$outlet->name);
                    $transfers->send($transfer->id);
                    $transfers->receive($transfer->id, self::PREFIX.':transfer:'.$code);

                    $this->as($cashiers[$code], $outlet);
                    $shift = $shifts->open($outlet->id, $branch->id, '100000');
                    $packageUnitId = Unit::query()->where('code', 'P4')->value('id');
                    // 24 penjualan tunai + 1 penjualan kredit; setiap transaksi satu paket isi 4.
                    for ($sale = 0; $sale < 25; $sale++) {
                        $cart = $carts->activeFor($shift);
                        $carts->add($cart, $products['TEST-DME']->id, $packageUnitId);
                        if ($sale === 24) {
                            $carts->updateDetails($cart, $creditCustomer->id, '[SIMULASI] Penjualan kredit untuk contoh piutang.');
                            $checkout->payLater($cart);
                        } else {
                            $checkout->cash($cart, '25000');
                        }
                    }

                    $receivable = Receivable::query()->whereHas('transaction', fn ($query) => $query->where('outlet_id', $outlet->id))
                        ->where('customer_id', $creditCustomer->id)->latest('id')->firstOrFail();
                    $this->as($finance, $outlet);
                    app(SettlementService::class)->collectReceivable($receivable->id, '5000', 'bank-transfer', self::PREFIX.':receivable:'.$code, 'SIM-AR-'.$code);

                    $this->as($cashiers[$code], $outlet);
                    $handover = $cash->request($branch->id, $central->id, '500000', '[SIMULASI] Setoran omset tunai '.$outlet->name, self::PREFIX.':handover:'.$code);
                    $this->as($finance, $outlet);
                    $cash->approve($handover->id);
                    $this->as($warehouse, $outlet);
                    $cash->confirm($handover->id);
                }

                // Enable pay-later only while seeding credit-sale examples.
                if ($payLaterWasCreated) {
                    $payLaterMethod->delete();
                } elseif (! $payLaterWasEnabled) {
                    $payLaterMethod->forceFill(['is_enabled' => false])->save();
                }

                $financeVault = Warehouse::query()->firstOrCreate(['code' => 'DEMO-KAS-FINANCE'], [
                    'name' => 'Kas Finance (Simulasi)', 'type' => 'central', 'outlet_id' => null,
                    'is_active' => true, 'is_sales_enabled' => false,
                ]);
                $this->as($warehouse, $outlets['GAL-BP']);
                $pickup = $cash->request($central->id, $financeVault->id, '1500000', '[SIMULASI] Pengambilan kas gudang oleh finance', $finalKey);
                $this->as($finance, $outlets['GAL-BP']);
                $cash->approve($pickup->id);
                $cash->confirm($pickup->id);
            }, attempts: 3);
        } finally {
            if ($previousUser) {
                Auth::login($previousUser);
            } else {
                Auth::logout();
            }
            session()->put('active_outlet_id', $previousOutlet);
            session()->put('auth.password_confirmed_at', $previousConfirmation);
        }

        $this->seedCashierRestock($central);
        $this->seedAllMenuComponentStock($central);
        $this->command?->info('Simulasi selesai: 4 outlet menerima stok komponen dimsum, lumpia, dan Chiquro untuk uji POS.');
    }

    /** Produce and transfer the semi-finished components needed by every configured POS menu. */
    private function seedAllMenuComponentStock(Warehouse $central): void
    {
        $requestPrefix = self::PREFIX.':all-menu-stock:request:';
        $existingCount = ProductionRequest::query()->where('idempotency_key', 'like', $requestPrefix.'%')->count();
        if ($existingCount === count(self::OUTLETS)) {
            return;
        }
        if ($existingCount !== 0) {
            throw new RuntimeException('Penambahan stok menu POS sebelumnya belum lengkap. Periksa permintaan dengan awalan '.$requestPrefix);
        }

        $previousUser = Auth::user();
        $previousOutlet = session('active_outlet_id');
        $previousConfirmation = session('auth.password_confirmed_at');

        try {
            DB::transaction(function () use ($central, $requestPrefix): void {
                $this->call(TestDataSeeder::class);
                $outlets = Outlet::query()->whereIn('code', array_keys(self::OUTLETS))->get()->keyBy('code');
                $warehouse = $this->actor('warehouse', $outlets->pluck('id')->all());
                $finance = $this->actor('finance', $outlets->pluck('id')->all());
                $supplier = Supplier::query()->firstOrCreate(['code' => 'DEMO-OPERASIONAL'], [
                    'name' => 'Pemasok Simulasi Operasional', 'is_active' => true,
                ]);
                $productSkus = ['TEST-DPL', 'TEST-DWO', 'TEST-DBE', 'TEST-LPS', 'TEST-CHB', 'TEST-CHS'];
                $products = Product::query()->whereIn('sku', $productSkus)->get()->keyBy('sku');
                if ($products->count() !== count($productSkus)) {
                    throw new RuntimeException('Komponen setengah jadi dimsum, lumpia, dan Chiquro belum lengkap.');
                }

                $production = app(ProductionRequestService::class);
                $orders = app(ProductionOrderService::class);
                $transfers = app(StockTransferService::class);
                foreach (self::OUTLETS as $code => $warehouseCode) {
                    $outlet = $outlets[$code];
                    $branch = Warehouse::query()->where('code', $warehouseCode)->where('outlet_id', $outlet->id)
                        ->where('is_sales_enabled', true)->firstOrFail();
                    $requestKey = $requestPrefix.$code;
                    $quantity = '30';
                    $lines = collect($productSkus)->map(fn ($sku) => [
                        'product_id' => $products[$sku]->id, 'quantity' => $quantity,
                    ])->all();

                    $this->as($warehouse, $outlet);
                    $request = $production->create(
                        $warehouse, $outlet->id, $central->id, $lines,
                        '[SIMULASI] Produksi komponen menu dimsum, lumpia, dan Chiquro untuk '.$outlet->name,
                        $requestKey,
                    );

                    $this->as($finance, $outlet);
                    $purchase = in_array($request->status, ['requested', 'purchasing'], true)
                        ? $production->approveAndPurchase($finance, $request->id, $supplier->id)
                        : null;
                    if ($purchase) {
                        $this->as($warehouse, $outlet);
                        $production->receive($warehouse, $purchase->id, $purchase->items->map(fn ($item) => [
                            'purchase_order_item_id' => $item->id,
                            'sent_quantity' => (string) $item->quantity,
                            'accepted_quantity' => (string) $item->quantity,
                            'qc_status' => 'good',
                            'notes' => '[SIMULASI] Bahan produksi menu uji diterima.',
                        ])->all(), 'SIM-MENU-'.$code, '[SIMULASI] Penerimaan bahan komponen POS', $requestKey.':material-receive');
                    }

                    $this->as($warehouse, $outlet);
                    $order = $orders->startFromRequest($warehouse, $request->id);
                    $orders->complete($warehouse, $order->id,
                        $order->items->flatMap->materials->map(fn ($row) => [
                            'production_order_material_id' => $row->id,
                            'actual_quantity' => (string) $row->planned_quantity,
                        ])->all(),
                        $order->items->map(fn ($row) => [
                            'production_order_item_id' => $row->id,
                            'actual_output' => (string) $row->planned_quantity,
                        ])->all(),
                    );

                    $transfer = $transfers->createDraft($central->id, $branch->id, collect($productSkus)->map(fn ($sku) => [
                        'product_id' => $products[$sku]->id, 'quantity' => $quantity,
                    ])->all(), '[SIMULASI] Komponen menu POS dikirim ke '.$outlet->name);
                    $transfers->send($transfer->id);
                    $transfers->receive($transfer->id, self::PREFIX.':all-menu-stock:transfer:'.$code);
                }
            }, attempts: 3);
        } finally {
            if ($previousUser) {
                Auth::login($previousUser);
            } else {
                Auth::logout();
            }
            session()->put('active_outlet_id', $previousOutlet);
            session()->put('auth.password_confirmed_at', $previousConfirmation);
        }

        $this->command?->info('Stok POS siap: masing-masing outlet menerima 30 pcs per komponen dimsum, lumpia, dan Chiquro.');
    }

    private function seedCashierRestock(Warehouse $central): void
    {
        $requestPrefix = self::PREFIX.':cashier-stock:request:';
        $existingCount = ProductionRequest::query()->where('idempotency_key', 'like', $requestPrefix.'%')->count();
        if ($existingCount === count(self::OUTLETS)) {
            return;
        }
        if ($existingCount !== 0) {
            throw new RuntimeException('Penambahan stok POS sebelumnya belum lengkap. Periksa permintaan dengan awalan '.self::PREFIX.':cashier-stock.');
        }

        $previousUser = Auth::user();
        $previousOutlet = session('active_outlet_id');
        $previousConfirmation = session('auth.password_confirmed_at');

        try {
            DB::transaction(function () use ($central): void {
                $this->call(TestDataSeeder::class);
                $outlets = Outlet::query()->whereIn('code', array_keys(self::OUTLETS))->get()->keyBy('code');
                $warehouse = $this->actor('warehouse', $outlets->pluck('id')->all());
                $finance = $this->actor('finance', $outlets->pluck('id')->all());
                $supplier = Supplier::query()->firstOrCreate(['code' => 'DEMO-OPERASIONAL'], [
                    'name' => 'Pemasok Simulasi Operasional', 'is_active' => true,
                ]);
                $products = Product::query()->whereIn('sku', ['TEST-DPL', 'TEST-DWO'])->get()->keyBy('sku');
                $production = app(ProductionRequestService::class);
                $orders = app(ProductionOrderService::class);
                $transfers = app(StockTransferService::class);

                foreach (self::OUTLETS as $code => $warehouseCode) {
                    $outlet = $outlets[$code];
                    $branch = Warehouse::query()->where('code', $warehouseCode)->where('outlet_id', $outlet->id)->where('is_sales_enabled', true)->firstOrFail();
                    $stockKey = self::PREFIX.':cashier-stock:'.$code;
                    $requestKey = self::PREFIX.':cashier-stock:request:'.$code;

                    $this->as($warehouse, $outlet);
                    $request = $production->create($warehouse, $outlet->id, $central->id, [
                        ['product_id' => $products['TEST-DPL']->id, 'quantity' => '50'],
                        ['product_id' => $products['TEST-DWO']->id, 'quantity' => '50'],
                    ], '[SIMULASI] Stok komponen paket POS untuk '.$outlet->name, $requestKey);

                    $this->as($finance, $outlet);
                    $purchase = in_array($request->status, ['requested', 'purchasing'], true)
                        ? $production->approveAndPurchase($finance, $request->id, $supplier->id)
                        : null;
                    if ($purchase) {
                        $this->as($warehouse, $outlet);
                        $production->receive($warehouse, $purchase->id, $purchase->items->map(fn ($item) => [
                            'purchase_order_item_id' => $item->id,
                            'sent_quantity' => (string) $item->quantity,
                            'accepted_quantity' => (string) $item->quantity,
                            'qc_status' => 'good',
                            'notes' => '[SIMULASI] Bahan stok uji diterima.',
                        ])->all(), 'SIM-STOCK-'.$code, '[SIMULASI] Penerimaan bahan untuk stok POS', $stockKey.':material-receive');
                    }

                    $this->as($warehouse, $outlet);
                    $order = $orders->startFromRequest($warehouse, $request->id);
                    $orders->complete($warehouse, $order->id,
                        $order->items->flatMap->materials->map(fn ($row) => ['production_order_material_id' => $row->id, 'actual_quantity' => (string) $row->planned_quantity])->all(),
                        $order->items->map(fn ($row) => ['production_order_item_id' => $row->id, 'actual_output' => (string) $row->planned_quantity])->all(),
                    );

                    $transfer = $transfers->createDraft($central->id, $branch->id, [
                        ['product_id' => $products['TEST-DPL']->id, 'quantity' => '50'],
                        ['product_id' => $products['TEST-DWO']->id, 'quantity' => '50'],
                    ], '[SIMULASI] Stok komponen paket POS tambahan untuk '.$outlet->name);
                    $transfers->send($transfer->id);
                    $transfers->receive($transfer->id, $stockKey.':transfer');
                }
            }, attempts: 3);
        } finally {
            if ($previousUser) {
                Auth::login($previousUser);
            } else {
                Auth::logout();
            }
            session()->put('active_outlet_id', $previousOutlet);
            session()->put('auth.password_confirmed_at', $previousConfirmation);
        }

        $this->command?->info('Tambahan stok POS siap: 50 dimsum wortel dan 50 dimsum beef setengah jadi per outlet.');
    }

    private function seedQualityControl(Warehouse $central, Supplier $supplier, User $warehouse, User $finance): void
    {
        $product = Product::query()->firstOrCreate(['sku' => 'DEMO-QC-ITEM'], [
            'title' => 'Produk untuk contoh QC', 'category_id' => Category::query()->where('name', 'Dimsum')->value('id'),
            'unit_id' => Unit::query()->where('code', 'PCS')->value('id'), 'buy_price' => '1500', 'sell_price' => '0',
            'stock' => 0, 'is_active' => true, 'is_sellable' => false, 'track_stock' => true, 'track_batches' => false,
            'description' => '[SIMULASI] Produk khusus untuk latihan penerimaan dan QC.',
        ]);
        $outlet = Outlet::query()->where('code', 'GAL-BP')->firstOrFail();
        $this->as($finance, $outlet);
        $purchasing = app(PurchasingService::class);
        $purchase = $purchasing->createDraft($supplier->id, $central->id, [['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '1500']], '[SIMULASI] Pembelian untuk pemeriksaan QC');
        $purchasing->order($purchase->id);

        $this->as($warehouse, $outlet);
        $receiving = $purchasing->receive($purchase->id, [$purchase->items->first()->id => [
            'quantity' => '10', 'accepted' => '9', 'qc_note' => '[SIMULASI] Satu unit kemasan rusak, sembilan diterima.',
        ]], self::PREFIX.':qc', '[SIMULASI] Pemeriksaan barang masuk');

        $payable = Payable::query()->where('goods_receiving_id', $receiving->id)->firstOrFail();
        $this->as($finance, $outlet);
        app(SettlementService::class)->payPayable($payable->id, '5000', 'bank-transfer', self::PREFIX.':payable', '[SIMULASI] Bayar sebagian tagihan QC');

        $unpaidOrder = $purchasing->createDraft($supplier->id, $central->id, [
            ['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '1700'],
        ], '[SIMULASI] Pembelian produk dengan hutang belum dibayar');
        $purchasing->order($unpaidOrder->id);
        $this->as($warehouse, $outlet);
        $purchasing->receive($unpaidOrder->id, [$unpaidOrder->items->first()->id => [
            'quantity' => '10', 'accepted' => '10', 'qc_note' => '[SIMULASI] Seluruh barang diterima.',
        ]], self::PREFIX.':ap-unpaid', '[SIMULASI] Hutang pemasok masih terbuka');
    }

    private function seedOutletMenuIngredients(Warehouse $branch, User $warehouseActor): void
    {
        foreach (['SAUS-MENTAI' => 5000, 'BOX-KECIL' => 100, 'PLASTIK-DIMSUM' => 100, 'SUMPIT' => 100, 'CHILI-OIL-CUP' => 100] as $code => $quantity) {
            $ingredient = Ingredient::query()->where('code', 'TEST-'.$code)->firstOrFail();
            $key = self::PREFIX.':outlet-opening:'.$branch->id.':'.$ingredient->id;
            if (DB::table('ingredient_inventory_ledgers')->where('idempotency_key', $key)->exists()) {
                continue;
            }
            $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $branch->id)->first();
            if ($balance && (float) $balance->stock >= $quantity) {
                continue;
            }
            if ($balance && (float) $balance->stock !== 0.0) {
                throw new RuntimeException('Stok outlet '.$ingredient->name.' sudah terisi; saldo simulasi tidak ditambahkan ulang.');
            }
            DB::table('ingredient_warehouses')->updateOrInsert(
                ['ingredient_id' => $ingredient->id, 'warehouse_id' => $branch->id],
                ['stock' => $quantity, 'reserved_stock' => 0, 'created_at' => $balance?->created_at ?? now(), 'updated_at' => now()],
            );
            $beforeGlobal = (float) $ingredient->stock;
            $ingredient->update(['stock' => number_format($beforeGlobal + $quantity, 3, '.', '')]);
            DB::table('ingredient_inventory_ledgers')->insert([
                'ingredient_id' => $ingredient->id, 'warehouse_id' => $branch->id, 'user_id' => $warehouseActor->id,
                'type' => 'opening_stock', 'quantity_delta' => $quantity, 'quantity_before' => 0,
                'quantity_after' => $quantity, 'reference_type' => self::class,
                'idempotency_key' => $key, 'created_at' => now(),
            ]);
        }
    }

    private function actor(string $role, array $outletIds, ?string $code = null): User
    {
        $email = 'simulasi.'.strtolower($role).($code ? '.'.strtolower($code) : '').'@example.invalid';
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => 'Simulasi '.ucfirst($role).($code ? ' '.$code : ''),
            'password' => Hash::make(Str::random(64)), 'is_active' => true, 'must_change_password' => true,
        ]);
        if ($user->wasRecentlyCreated) {
            $user->assignRole($role);
        }
        foreach ($outletIds as $index => $outletId) {
            $user->outlets()->syncWithoutDetaching([$outletId => ['is_default' => $index === 0]]);
        }
        $extra = match ($role) {
            'finance' => ['warehouses-access', 'production-requests-approve', 'purchase-orders-create', 'purchase-orders-order', 'payables-pay', 'receivables-access', 'receivables-collect', 'cash-management-approve', 'cash-management-create'],
            'warehouse' => ['warehouses-access', 'production-requests-create', 'production-orders-create', 'production-orders-complete', 'transfers-create', 'transfers-send', 'transfers-receive', 'goods-receivings-create', 'cash-handovers-confirm', 'cash-management-create'],
            default => ['cashier-shifts-open', 'pos-access', 'pos-checkout', 'cash-management-create'],
        };
        $user->givePermissionTo($extra);

        return $user;
    }

    private function as(User $user, Outlet $outlet): void
    {
        Auth::login($user);
        session()->put('active_outlet_id', $outlet->id);
        session()->put('auth.password_confirmed_at', now()->timestamp);
    }
}

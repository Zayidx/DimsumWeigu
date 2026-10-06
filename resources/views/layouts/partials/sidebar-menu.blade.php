@php
    $user = auth()->user();
    $salesActive = request()->routeIs('pos.*', 'shifts.*', 'transactions.*', 'customers.*');
    $inventoryActive = request()->routeIs('products.*', 'categories.*', 'units.*', 'inventory.*', 'ingredients.*', 'recipes.*', 'production-requests.*', 'production-orders.*', 'transfers.*', 'stock-opnames.*', 'waste.*');
    $financeActive = request()->routeIs('suppliers.*', 'purchase-orders.*', 'goods-receivings.*', 'supplier-returns.*', 'expenses.*', 'cash-management.*', 'payables.*', 'receivables.*');
    $reportsActive = request()->routeIs('reports.*');
@endphp

<ul class="menu">
    <li class="sidebar-title">Ruang kerja</li>
    @can('dashboard-access')
        <li class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><a href="{{ route('dashboard') }}" class="sidebar-link"><i class="bi bi-grid-fill" aria-hidden="true"></i><span>Dashboard</span></a></li>
    @endcan

    @if ($user->canAny(['pos-access', 'cashier-shifts-access', 'transactions-access', 'customers-access']))
        <li class="sidebar-item has-sub {{ $salesActive ? 'active' : '' }}">
            <a href="#" class="sidebar-link" aria-expanded="{{ $salesActive ? 'true' : 'false' }}"><i class="bi bi-receipt" aria-hidden="true"></i><span>Penjualan</span></a>
            <ul class="submenu {{ $salesActive ? 'submenu-open' : 'submenu-closed' }}">
                @if (Route::has('pos.index') && $user->can('pos-access'))<li class="submenu-item {{ request()->routeIs('pos.*') ? 'active' : '' }}"><a href="{{ route('pos.index') }}" class="submenu-link">Kasir</a></li>@endif
                @can('cashier-shifts-access')<li class="submenu-item {{ request()->routeIs('shifts.*') ? 'active' : '' }}"><a href="{{ route('shifts.index') }}" class="submenu-link">Shift kasir</a></li>@endcan
                @if (Route::has('transactions.index') && $user->can('transactions-access'))<li class="submenu-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}"><a href="{{ route('transactions.index') }}" class="submenu-link">Transaksi</a></li>@endif
                @if (Route::has('customers.index') && $user->can('customers-access'))<li class="submenu-item {{ request()->routeIs('customers.*') ? 'active' : '' }}"><a href="{{ route('customers.index') }}" class="submenu-link">Pelanggan</a></li>@endif
            </ul>
        </li>
    @endif

    @if ($user->canAny(['products-access', 'categories-access', 'units-access', 'inventory-access', 'ingredients-access', 'recipes-access', 'production-requests-access', 'production-orders-access', 'transfers-access', 'stock-opnames-access', 'waste-access']))
        <li class="sidebar-item has-sub {{ $inventoryActive ? 'active' : '' }}">
            <a href="#" class="sidebar-link" aria-expanded="{{ $inventoryActive ? 'true' : 'false' }}"><i class="bi bi-boxes" aria-hidden="true"></i><span>Stok &amp; produksi</span></a>
            <ul class="submenu {{ $inventoryActive ? 'submenu-open' : 'submenu-closed' }}">
                @if (Route::has('products.index') && $user->can('products-access'))<li class="submenu-item {{ request()->routeIs('products.*') ? 'active' : '' }}"><a href="{{ route('products.index') }}" class="submenu-link">Produk</a></li>@endif
                @if (Route::has('categories.index') && $user->can('categories-access'))<li class="submenu-item {{ request()->routeIs('categories.*') ? 'active' : '' }}"><a href="{{ route('categories.index') }}" class="submenu-link">Kategori</a></li>@endif
                @if (Route::has('units.index') && $user->can('units-access'))<li class="submenu-item {{ request()->routeIs('units.*') ? 'active' : '' }}"><a href="{{ route('units.index') }}" class="submenu-link">Satuan</a></li>@endif
                @if (Route::has('inventory.index') && $user->can('inventory-access'))<li class="submenu-item {{ request()->routeIs('inventory.*') ? 'active' : '' }}"><a href="{{ route('inventory.index') }}" class="submenu-link">Inventori</a></li>@endif
                @if (Route::has('ingredients.index') && $user->can('ingredients-access'))<li class="submenu-item {{ request()->routeIs('ingredients.*') ? 'active' : '' }}"><a href="{{ route('ingredients.index') }}" class="submenu-link">Master bahan</a></li>@endif
                @if (Route::has('recipes.index') && $user->can('recipes-access'))<li class="submenu-item {{ request()->routeIs('recipes.*') ? 'active' : '' }}"><a href="{{ route('recipes.index') }}" class="submenu-link">Resep &amp; HPP</a></li>@endif
                @if (Route::has('production-requests.index') && $user->can('production-requests-access'))<li class="submenu-item {{ request()->routeIs('production-requests.*') ? 'active' : '' }}"><a href="{{ route('production-requests.index') }}" class="submenu-link">Permintaan produksi</a></li>@endif
                @if (Route::has('production-orders.index') && $user->can('production-orders-access'))<li class="submenu-item {{ request()->routeIs('production-orders.*') ? 'active' : '' }}"><a href="{{ route('production-orders.index') }}" class="submenu-link">Produksi</a></li>@endif
                @if (Route::has('transfers.index') && $user->can('transfers-access'))<li class="submenu-item {{ request()->routeIs('transfers.*') ? 'active' : '' }}"><a href="{{ route('transfers.index') }}" class="submenu-link">Transfer stok</a></li>@endif
                @if (Route::has('stock-opnames.index') && $user->can('stock-opnames-access'))<li class="submenu-item {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}"><a href="{{ route('stock-opnames.index') }}" class="submenu-link">Stok opname</a></li>@endif
                @can('waste-access')<li class="submenu-item {{ request()->routeIs('waste.*') ? 'active' : '' }}"><a href="{{ route('waste.index') }}" class="submenu-link">Waste</a></li>@endcan
            </ul>
        </li>
    @endif

    @if ($user->canAny(['suppliers-access', 'purchase-orders-access', 'goods-receivings-access', 'supplier-returns-access', 'expenses-access', 'cash-management-access', 'payables-access', 'receivables-access']))
        <li class="sidebar-item has-sub {{ $financeActive ? 'active' : '' }}">
            <a href="#" class="sidebar-link" aria-expanded="{{ $financeActive ? 'true' : 'false' }}"><i class="bi bi-wallet2" aria-hidden="true"></i><span>Pembelian &amp; keuangan</span></a>
            <ul class="submenu {{ $financeActive ? 'submenu-open' : 'submenu-closed' }}">
                @if (Route::has('suppliers.index') && $user->can('suppliers-access'))<li class="submenu-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"><a href="{{ route('suppliers.index') }}" class="submenu-link">Pemasok</a></li>@endif
                @if (Route::has('purchase-orders.index') && $user->can('purchase-orders-access'))<li class="submenu-item {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}"><a href="{{ route('purchase-orders.index') }}" class="submenu-link">Pembelian</a></li>@endif
                @if (Route::has('goods-receivings.index') && $user->can('goods-receivings-access'))<li class="submenu-item {{ request()->routeIs('goods-receivings.*') ? 'active' : '' }}"><a href="{{ route('goods-receivings.index') }}" class="submenu-link">Penerimaan barang</a></li>@endif
                @can('supplier-returns-access')<li class="submenu-item {{ request()->routeIs('supplier-returns.*') ? 'active' : '' }}"><a href="{{ route('supplier-returns.index') }}" class="submenu-link">Retur pemasok</a></li>@endcan
                @can('expenses-access')<li class="submenu-item {{ request()->routeIs('expenses.*') ? 'active' : '' }}"><a href="{{ route('expenses.index') }}" class="submenu-link">Pengeluaran</a></li>@endcan
                @can('cash-management-access')<li class="submenu-item {{ request()->routeIs('cash-management.*') ? 'active' : '' }}"><a href="{{ route('cash-management.index') }}" class="submenu-link">Manajemen kas</a></li>@endcan
                @if (Route::has('payables.index') && $user->can('payables-access'))<li class="submenu-item {{ request()->routeIs('payables.*') ? 'active' : '' }}"><a href="{{ route('payables.index') }}" class="submenu-link">Hutang</a></li>@endif
                @if (Route::has('receivables.index') && $user->can('receivables-access'))<li class="submenu-item {{ request()->routeIs('receivables.*') ? 'active' : '' }}"><a href="{{ route('receivables.index') }}" class="submenu-link">Piutang</a></li>@endif
            </ul>
        </li>
    @endif

    @can('reports-access')
        <li class="sidebar-item has-sub {{ $reportsActive ? 'active' : '' }}">
            <a href="#" class="sidebar-link" aria-expanded="{{ $reportsActive ? 'true' : 'false' }}"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i><span>Laporan</span></a>
            <ul class="submenu {{ $reportsActive ? 'submenu-open' : 'submenu-closed' }}">
                @foreach ([['reports.sales', 'Penjualan', 'reports.sales'], ['reports.profit', 'Laba', 'reports.profit'], ['reports.inventory', 'Stok', 'reports.inventory'], ['reports.aging', 'Aging', 'reports.aging']] as [$routeName, $label, $routePattern])
                    <li class="submenu-item {{ request()->routeIs($routePattern) ? 'active' : '' }}"><a href="{{ route($routeName) }}" class="submenu-link">{{ $label }}</a></li>
                @endforeach
            </ul>
        </li>
    @endcan

    @can('audit-logs-access')
        <li class="sidebar-title">Pemantauan</li>
        @if (Route::has('audit-logs.index'))<li class="sidebar-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"><a href="{{ route('audit-logs.index') }}" class="sidebar-link"><i class="bi bi-journal-text" aria-hidden="true"></i><span>Audit log</span></a></li>@endif
    @endcan

    @if ($user->canAny(['users-access', 'roles-access', 'store-settings-access', 'payment-settings-access', 'bank-accounts-access']))
        <li class="sidebar-title">Administrasi</li>
        @if (Route::has('users.index') && $user->can('users-access'))<li class="sidebar-item {{ request()->routeIs('users.*') ? 'active' : '' }}"><a href="{{ route('users.index') }}" class="sidebar-link"><i class="bi bi-people-fill" aria-hidden="true"></i><span>Pengguna</span></a></li>@endif
        @if (Route::has('roles.index') && $user->hasRole('super-admin'))<li class="sidebar-item {{ request()->routeIs('roles.*') ? 'active' : '' }}"><a href="{{ route('roles.index') }}" class="sidebar-link"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i><span>Role &amp; permission</span></a></li>@endif
        @if (Route::has('settings.store') && $user->can('store-settings-access'))<li class="sidebar-item {{ request()->routeIs('settings.store') ? 'active' : '' }}"><a href="{{ route('settings.store') }}" class="sidebar-link"><i class="bi bi-sliders" aria-hidden="true"></i><span>Profil toko</span></a></li>@endif
        @if (Route::has('settings.payments') && $user->can('payment-settings-access'))<li class="sidebar-item {{ request()->routeIs('settings.payments*') ? 'active' : '' }}"><a href="{{ route('settings.payments') }}" class="sidebar-link"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i><span>Provider pembayaran</span></a></li>@endif
        @if (Route::has('settings.bank-accounts') && $user->can('bank-accounts-access'))<li class="sidebar-item {{ request()->routeIs('settings.bank-accounts') ? 'active' : '' }}"><a href="{{ route('settings.bank-accounts') }}" class="sidebar-link"><i class="bi bi-bank" aria-hidden="true"></i><span>Rekening bank</span></a></li>@endif
    @endif

</ul>

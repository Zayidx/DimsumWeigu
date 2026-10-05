<ul class="menu">
    <li class="sidebar-title">Workspace</li>
    @can('dashboard-access')
        <li class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><a href="{{ route('dashboard') }}" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a></li>
    @endcan
    @if (Route::has('pos.index') && auth()->user()->can('pos-access'))
        <li class="sidebar-item {{ request()->routeIs('pos.*') ? 'active' : '' }}"><a href="{{ route('pos.index') }}" class="sidebar-link"><i class="bi bi-cart3"></i><span>Kasir</span></a></li>
    @endif
    @can('cashier-shifts-access')
        <li class="sidebar-item {{ request()->routeIs('shifts.*') ? 'active' : '' }}"><a href="{{ route('shifts.index') }}" class="sidebar-link"><i class="bi bi-clock-history"></i><span>Shift kasir</span></a></li>
    @endcan
    @if (Route::has('products.index') && auth()->user()->can('products-access'))
        <li class="sidebar-item {{ request()->routeIs('products.*') ? 'active' : '' }}"><a href="{{ route('products.index') }}" class="sidebar-link"><i class="bi bi-box-seam-fill"></i><span>Produk</span></a></li>
    @endif
    @if (Route::has('categories.index') && auth()->user()->can('categories-access'))
        <li class="sidebar-item {{ request()->routeIs('categories.*') ? 'active' : '' }}"><a href="{{ route('categories.index') }}" class="sidebar-link"><i class="bi bi-tags-fill"></i><span>Kategori</span></a></li>
    @endif
    @if (Route::has('units.index') && auth()->user()->can('units-access'))
        <li class="sidebar-item {{ request()->routeIs('units.*') ? 'active' : '' }}"><a href="{{ route('units.index') }}" class="sidebar-link"><i class="bi bi-rulers"></i><span>Satuan</span></a></li>
    @endif
    @if (Route::has('inventory.index') && auth()->user()->can('inventory-access'))
        <li class="sidebar-item {{ request()->routeIs('inventory.*') ? 'active' : '' }}"><a href="{{ route('inventory.index') }}" class="sidebar-link"><i class="bi bi-boxes"></i><span>Inventori</span></a></li>
    @endif
    @if (Route::has('ingredients.index') && auth()->user()->can('ingredients-access'))
        <li class="sidebar-item {{ request()->routeIs('ingredients.*') ? 'active' : '' }}"><a href="{{ route('ingredients.index') }}" class="sidebar-link"><i class="bi bi-basket"></i><span>Master bahan</span></a></li>
    @endif
    @if (Route::has('recipes.index') && auth()->user()->can('recipes-access'))
        <li class="sidebar-item {{ request()->routeIs('recipes.*') ? 'active' : '' }}"><a href="{{ route('recipes.index') }}" class="sidebar-link"><i class="bi bi-journal-text"></i><span>Resep &amp; HPP</span></a></li>
    @endif
    @if (Route::has('production-requests.index') && auth()->user()->can('production-requests-access'))
        <li class="sidebar-item {{ request()->routeIs('production-requests.*') ? 'active' : '' }}"><a href="{{ route('production-requests.index') }}" class="sidebar-link"><i class="bi bi-clipboard2-pulse"></i><span>Permintaan produksi</span></a></li>
    @endif
    @if (Route::has('production-orders.index') && auth()->user()->can('production-orders-access'))
        <li class="sidebar-item {{ request()->routeIs('production-orders.*') ? 'active' : '' }}"><a href="{{ route('production-orders.index') }}" class="sidebar-link"><i class="bi bi-gear-wide-connected"></i><span>Produksi</span></a></li>
    @endif
    @if (Route::has('transfers.index') && auth()->user()->can('transfers-access'))
        <li class="sidebar-item {{ request()->routeIs('transfers.*') ? 'active' : '' }}"><a href="{{ route('transfers.index') }}" class="sidebar-link"><i class="bi bi-arrow-left-right"></i><span>Transfer stok</span></a></li>
    @endif
    @if (Route::has('stock-opnames.index') && auth()->user()->can('stock-opnames-access'))
        <li class="sidebar-item {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}"><a href="{{ route('stock-opnames.index') }}" class="sidebar-link"><i class="bi bi-clipboard-data"></i><span>Stok opname</span></a></li>
    @endif
    @if (Route::has('transactions.index') && auth()->user()->can('transactions-access'))
        <li class="sidebar-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}"><a href="{{ route('transactions.index') }}" class="sidebar-link"><i class="bi bi-receipt-cutoff"></i><span>Transaksi</span></a></li>
    @endif
    @if (Route::has('customers.index') && auth()->user()->can('customers-access'))
        <li class="sidebar-item {{ request()->routeIs('customers.*') ? 'active' : '' }}"><a href="{{ route('customers.index') }}" class="sidebar-link"><i class="bi bi-people"></i><span>Pelanggan</span></a></li>
    @endif
    @if (Route::has('suppliers.index') && auth()->user()->can('suppliers-access'))
        <li class="sidebar-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"><a href="{{ route('suppliers.index') }}" class="sidebar-link"><i class="bi bi-truck"></i><span>Pemasok</span></a></li>
    @endif
    @if (Route::has('purchase-orders.index') && auth()->user()->can('purchase-orders-access'))
        <li class="sidebar-item {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}"><a href="{{ route('purchase-orders.index') }}" class="sidebar-link"><i class="bi bi-bag-check"></i><span>Pembelian</span></a></li>
    @endif
    @if (Route::has('goods-receivings.index') && auth()->user()->can('goods-receivings-access'))
        <li class="sidebar-item {{ request()->routeIs('goods-receivings.*') ? 'active' : '' }}"><a href="{{ route('goods-receivings.index') }}" class="sidebar-link"><i class="bi bi-box-arrow-in-down"></i><span>Penerimaan</span></a></li>
    @endif
    @can('supplier-returns-access')
        <li class="sidebar-item {{ request()->routeIs('supplier-returns.*') ? 'active' : '' }}"><a href="{{ route('supplier-returns.index') }}" class="sidebar-link"><i class="bi bi-box-arrow-up"></i><span>Retur pemasok</span></a></li>
    @endcan
    @can('expenses-access')
        <li class="sidebar-item {{ request()->routeIs('expenses.*') ? 'active' : '' }}"><a href="{{ route('expenses.index') }}" class="sidebar-link"><i class="bi bi-wallet2"></i><span>Pengeluaran</span></a></li>
    @endcan
    @can('cash-management-access')
        <li class="sidebar-item {{ request()->routeIs('cash-management.*') ? 'active' : '' }}"><a href="{{ route('cash-management.index') }}" class="sidebar-link"><i class="bi bi-cash-stack"></i><span>Manajemen kas</span></a></li>
    @endcan
    @can('waste-access')
        <li class="sidebar-item {{ request()->routeIs('waste.*') ? 'active' : '' }}"><a href="{{ route('waste.index') }}" class="sidebar-link"><i class="bi bi-trash"></i><span>Waste</span></a></li>
    @endcan
    @if (Route::has('payables.index') && auth()->user()->can('payables-access'))
        <li class="sidebar-item {{ request()->routeIs('payables.*') ? 'active' : '' }}"><a href="{{ route('payables.index') }}" class="sidebar-link"><i class="bi bi-arrow-up-right-circle"></i><span>Hutang</span></a></li>
    @endif
    @if (Route::has('receivables.index') && auth()->user()->can('receivables-access'))
        <li class="sidebar-item {{ request()->routeIs('receivables.*') ? 'active' : '' }}"><a href="{{ route('receivables.index') }}" class="sidebar-link"><i class="bi bi-arrow-down-left-circle"></i><span>Piutang</span></a></li>
    @endif
    @if (Route::has('users.index') && auth()->user()->can('users-access'))
        <li class="sidebar-title">Pengaturan</li>
        <li class="sidebar-item {{ request()->routeIs('users.*') ? 'active' : '' }}"><a href="{{ route('users.index') }}" class="sidebar-link"><i class="bi bi-people-fill"></i><span>Pengguna</span></a></li>
    @endif
    @if (Route::has('roles.index') && auth()->user()->hasRole('super-admin'))
        @unless(auth()->user()->can('users-access'))<li class="sidebar-title">Pengaturan</li>@endunless
        <li class="sidebar-item {{ request()->routeIs('roles.*') ? 'active' : '' }}"><a href="{{ route('roles.index') }}" class="sidebar-link"><i class="bi bi-shield-lock-fill"></i><span>Role &amp; permission</span></a></li>
    @endif
    @if (Route::has('reports.sales') && auth()->user()->can('reports-access'))
        <li class="sidebar-title">Laporan</li>
        <li class="sidebar-item {{ request()->routeIs('reports.sales') ? 'active' : '' }}"><a href="{{ route('reports.sales') }}" class="sidebar-link"><i class="bi bi-graph-up-arrow"></i><span>Penjualan</span></a></li>
        <li class="sidebar-item {{ request()->routeIs('reports.profit') ? 'active' : '' }}"><a href="{{ route('reports.profit') }}" class="sidebar-link"><i class="bi bi-currency-exchange"></i><span>Laba</span></a></li>
        <li class="sidebar-item {{ request()->routeIs('reports.inventory') ? 'active' : '' }}"><a href="{{ route('reports.inventory') }}" class="sidebar-link"><i class="bi bi-boxes"></i><span>Stok</span></a></li>
        <li class="sidebar-item {{ request()->routeIs('reports.aging') ? 'active' : '' }}"><a href="{{ route('reports.aging') }}" class="sidebar-link"><i class="bi bi-hourglass-split"></i><span>Aging</span></a></li>
    @endif
    @if (Route::has('audit-logs.index') && auth()->user()->can('audit-logs-access'))
        <li class="sidebar-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"><a href="{{ route('audit-logs.index') }}" class="sidebar-link"><i class="bi bi-journal-text"></i><span>Audit log</span></a></li>
    @endif
    @if (Route::has('notifications.index') && auth()->user()->can('notifications-access'))
        <li class="sidebar-item {{ request()->routeIs('notifications.*') ? 'active' : '' }}"><a href="{{ route('notifications.index') }}" class="sidebar-link"><i class="bi bi-bell"></i><span>Notifikasi</span></a></li>
    @endif
    @if (Route::has('settings.store') && auth()->user()->can('store-settings-access'))
        <li class="sidebar-title">Pengaturan</li>
        <li class="sidebar-item {{ request()->routeIs('settings.store') ? 'active' : '' }}"><a href="{{ route('settings.store') }}" class="sidebar-link"><i class="bi bi-sliders"></i><span>Profil toko</span></a></li>
    @endif
    @if (Route::has('settings.payments') && auth()->user()->can('payment-settings-access'))
        @unless(auth()->user()->can('store-settings-access'))<li class="sidebar-title">Pengaturan</li>@endunless
        <li class="sidebar-item {{ request()->routeIs('settings.payments*') ? 'active' : '' }}"><a href="{{ route('settings.payments') }}" class="sidebar-link"><i class="bi bi-credit-card-2-front"></i><span>Provider pembayaran</span></a></li>
    @endif
    @if (Route::has('settings.bank-accounts') && auth()->user()->can('bank-accounts-access'))
        <li class="sidebar-item {{ request()->routeIs('settings.bank-accounts') ? 'active' : '' }}"><a href="{{ route('settings.bank-accounts') }}" class="sidebar-link"><i class="bi bi-bank"></i><span>Rekening bank</span></a></li>
    @endif
    <li class="sidebar-item"><a href="{{ route('profile.edit') }}" class="sidebar-link"><i class="bi bi-person-circle"></i><span>Profil saya</span></a></li>
</ul>

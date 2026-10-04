<nav class="workspace-mobile-navigation" aria-label="Navigasi cepat">
    @can('dashboard-access')
        <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><i class="bi bi-grid" aria-hidden="true"></i><span>Ringkasan</span></a>
    @endcan
    @can('pos-access')
        <a href="{{ route('pos.index') }}" @if(request()->routeIs('pos.*')) aria-current="page" @endif><i class="bi bi-cart3" aria-hidden="true"></i><span>Kasir</span></a>
    @elsecan('inventory-access')
        <a href="{{ route('inventory.index') }}" @if(request()->routeIs('inventory.*')) aria-current="page" @endif><i class="bi bi-box-seam" aria-hidden="true"></i><span>Stok</span></a>
    @elsecan('reports-access')
        <a href="{{ route('reports.sales') }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Laporan</span></a>
    @endcan
    @can('transactions-access')
        <a href="{{ route('transactions.index') }}" @if(request()->routeIs('transactions.*')) aria-current="page" @endif><i class="bi bi-receipt" aria-hidden="true"></i><span>Transaksi</span></a>
    @elsecan('purchase-orders-access')
        <a href="{{ route('purchase-orders.index') }}" @if(request()->routeIs('purchase-orders.*')) aria-current="page" @endif><i class="bi bi-bag-check" aria-hidden="true"></i><span>Pembelian</span></a>
    @endcan
    <button type="button" class="burger-btn" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list" aria-hidden="true"></i><span>Menu</span></button>
</nav>

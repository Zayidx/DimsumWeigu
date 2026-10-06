<aside id="sidebar" aria-label="Menu ruang kerja">
    <div class="sidebar-wrapper">
        <div class="sidebar-menu">
            <div class="workspace-sidebar-brand">
                <a href="{{ route('home') }}" class="workspace-brand"><img src="{{ asset('assets/logo/logo-no-background.png') }}" alt="Weigu"><span>{{ config('app.name') }}<small>Ruang kerja</small></span></a>
                <button type="button" class="sidebar-hide btn btn-light d-xl-none" aria-label="Tutup menu"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            @if($workspaceOutlets->isNotEmpty())
                <form method="POST" action="{{ route('outlet.switch') }}" class="workspace-sidebar-outlet">
                    @csrf
                    <label for="active-outlet"><i class="bi bi-shop" aria-hidden="true"></i>Outlet aktif</label>
                    <div class="workspace-sidebar-outlet-controls">
                        <select id="active-outlet" name="outlet_id" class="form-select" @if($workspaceOutlets->count() === 1) aria-readonly="true" @endif>
                            @foreach($workspaceOutlets as $option)<option value="{{ $option->id }}" @selected((int) session('active_outlet_id') === $option->id)>{{ $option->name }}</option>@endforeach
                        </select>
                        @if($workspaceOutlets->count() > 1)<button class="btn btn-outline-primary" type="submit">Ganti outlet</button>@endif
                    </div>
                </form>
            @endif
            @hasSection('sidebar') @yield('sidebar') @else @include('layouts.partials.sidebar-menu') @endif
            <nav class="workspace-sidebar-account" aria-label="Menu akun">
                <div class="workspace-sidebar-user"><i class="bi bi-person-circle" aria-hidden="true"></i><div><span>Akun masuk</span><strong>{{ auth()->user()->name }}</strong></div></div>
                @can('notifications-access')
                    @php($unreadNotifications = \App\Models\OperationalNotification::query()->whereNull('read_at')->where(function ($scope) { $scope->where(fn ($personal) => $personal->where('user_id', auth()->id())->where(fn ($outlet) => $outlet->whereNull('outlet_id')->orWhere('outlet_id', session('active_outlet_id'))))->orWhere(fn ($shared) => $shared->whereNull('user_id')->where('outlet_id', session('active_outlet_id'))); })->count())
                    <a class="workspace-sidebar-account-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}" aria-label="Notifikasi, {{ $unreadNotifications }} belum dibaca"><i class="bi bi-bell" aria-hidden="true"></i><span>Notifikasi</span>@if($unreadNotifications)<span class="workspace-notification-count">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif</a>
                @endcan
                <a class="workspace-sidebar-account-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}"><i class="bi bi-person" aria-hidden="true"></i><span>Profil saya</span></a>
                <button type="button" class="workspace-sidebar-account-link" data-theme-toggle><i class="bi bi-moon-stars" aria-hidden="true"></i><span>Ganti tema</span></button>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="workspace-sidebar-account-link text-danger"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Keluar</span></button></form>
            </nav>
        </div>
    </div>
</aside>

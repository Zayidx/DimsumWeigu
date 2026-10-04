<?php

namespace App\Providers;

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureOutletContext;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Pagination\Paginator;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        config(['livewire.pagination_theme' => 'bootstrap']);
        Livewire::addPersistentMiddleware([
            EnsureActiveAccount::class,
            RequirePasswordChange::class,
            EnsureOutletContext::class,
            AuthenticateSession::class,
            RequirePassword::class,
            PermissionMiddleware::class,
            RoleMiddleware::class,
        ]);
    }
}

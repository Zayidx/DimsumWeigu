<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Middleware\EnsureActiveApiAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:12,1')->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,60')->name('register');
    Route::middleware(['auth:sanctum', EnsureActiveApiAccount::class])->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->middleware('ability:user:read')->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/products', [CatalogController::class, 'products'])->middleware(['abilities:products-access', 'permission:products-access'])->name('products.index');
        Route::get('/categories', [CatalogController::class, 'categories'])->middleware(['abilities:categories-access', 'permission:categories-access'])->name('categories.index');
        Route::get('/warehouses', [CatalogController::class, 'warehouses'])->middleware(['abilities:warehouses-access', 'permission:warehouses-access'])->name('warehouses.index');
        Route::get('/suppliers', [CatalogController::class, 'suppliers'])->middleware(['abilities:suppliers-access', 'permission:suppliers-access'])->name('suppliers.index');
        Route::get('/customers', [CatalogController::class, 'customers'])->middleware(['abilities:customers-access', 'permission:customers-access'])->name('customers.index');
        Route::post('/customers', [CatalogController::class, 'createCustomer'])->middleware(['abilities:customers-create', 'permission:customers-create'])->name('customers.store');
        Route::prefix('pos')->name('pos.')->middleware(['abilities:pos-access', 'permission:pos-access', 'throttle:120,1'])->group(function () {
            Route::get('/shift', [PosController::class, 'shift'])->name('shift');
            Route::post('/shift/open', [PosController::class, 'openShift'])->name('shift.open');
            Route::post('/shift/close', [PosController::class, 'closeShift'])->middleware('throttle:8,1,pos-close-')->name('shift.close');
            Route::get('/catalog', [PosController::class, 'catalog'])->name('catalog');
            Route::get('/cart', [PosController::class, 'cart'])->name('cart');
            Route::patch('/carts/{cart}', [PosController::class, 'updateCart'])->whereNumber('cart')->name('cart.update');
            Route::post('/carts/{cart}/items', [PosController::class, 'addItem'])->whereNumber('cart')->name('items.store');
            Route::patch('/carts/{cart}/items/{item}', [PosController::class, 'updateItem'])->whereNumber(['cart', 'item'])->name('items.update');
            Route::delete('/carts/{cart}/items/{item}', [PosController::class, 'removeItem'])->whereNumber(['cart', 'item'])->name('items.destroy');
            Route::get('/held', [PosController::class, 'held'])->name('held');
            Route::post('/carts/{cart}/hold', [PosController::class, 'hold'])->whereNumber('cart')->name('hold');
            Route::post('/carts/{cart}/resume', [PosController::class, 'resume'])->whereNumber('cart')->name('resume');
            Route::delete('/carts/{cart}/hold', [PosController::class, 'discard'])->whereNumber('cart')->name('discard');
            Route::post('/carts/{cart}/checkout', [PosController::class, 'checkout'])->whereNumber('cart')->name('checkout');
            Route::post('/sync', [PosController::class, 'sync'])->name('sync');
            Route::get('/transactions', [PosController::class, 'history'])->name('transactions.index');
            Route::get('/transactions/{transaction}', [PosController::class, 'transaction'])->whereNumber('transaction')->name('transactions.show');
        });
    });
});

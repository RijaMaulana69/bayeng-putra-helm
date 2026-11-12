<?php

use App\Http\Controllers\DashboardDispatcherController;
use App\Livewire\Catalog\CatalogIndex;
use App\Livewire\Catalog\ProductDetail;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

/*
|--------------------------------------------------------------------------
| Rute Publik Katalog & Detail Produk (Submilestone 3.1)
|--------------------------------------------------------------------------
*/
Route::get('/katalog', CatalogIndex::class)->name('catalog.index');
Route::get('/katalog/{slug}', ProductDetail::class)->name('catalog.show');

/*
|--------------------------------------------------------------------------
| Rute Pengguna Terautentikasi & Dashboard
|--------------------------------------------------------------------------
*/
Route::get('dashboard', DashboardDispatcherController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::view('dashboard', 'owner.dashboard')->name('dashboard');
    });

Route::middleware(['auth', 'verified', 'role:karyawan'])
    ->prefix('karyawan')
    ->name('karyawan.')
    ->group(function () {
        Route::view('dashboard', 'karyawan.dashboard')->name('dashboard');
    });

Route::middleware(['auth', 'verified', 'role:pembeli'])
    ->prefix('pembeli')
    ->name('pembeli.')
    ->group(function () {
        Route::view('dashboard', 'pembeli.dashboard')->name('dashboard');
    });

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

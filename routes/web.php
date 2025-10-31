<?php

use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductSettingsController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\UserDashboardController;
use App\Http\Controllers\WarehouseDeliveryController;
use App\Http\Controllers\WarehouseDocumentController;
use App\Http\Controllers\WarehouseSettingsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:user,admin'])->group(function (): void {
    Route::get('/dashboard', UserDashboardController::class)->name('dashboard.user');

    Route::resource('/integrations', IntegrationController::class)
        ->except(['show'])
        ->names('integrations');

    Route::post('/integrations/{integration}/test', [IntegrationController::class, 'test'])
        ->name('integrations.test');

    Route::resource('/products', ProductController::class)
        ->names('products')
        ->except(['show']);
    Route::resource('/product-categories', ProductCategoryController::class)
        ->names('product-categories')
        ->except(['show']);
    Route::get('/products/settings', ProductSettingsController::class)->name('products.settings');

    Route::resource('/product-catalogs', ProductCatalogController::class)
        ->names('product-catalogs')
        ->except(['show']);
    Route::resource('/manufacturers', ManufacturerController::class)
        ->names('manufacturers')
        ->except(['show']);

    Route::resource('/warehouse/documents', WarehouseDocumentController::class)
        ->parameters(['documents' => 'warehouse_document'])
        ->names('warehouse.documents')
        ->except(['show']);
    Route::get('/warehouse/deliveries', [WarehouseDeliveryController::class, 'index'])->name('warehouse.deliveries.index');
    Route::get('/warehouse/settings', WarehouseSettingsController::class)->name('warehouse.settings');
    Route::resource('/warehouse/contractors', ContractorController::class)
        ->names('warehouse.contractors')
        ->except(['show']);
});

Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/admin/dashboard', AdminDashboardController::class)->name('dashboard.admin');
    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/{user}/edit', [UserManagementController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserManagementController::class, 'destroy'])->name('admin.users.destroy');
});

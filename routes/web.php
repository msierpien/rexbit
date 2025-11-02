<?php

use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\IntegrationImportProfileController;
use App\Http\Controllers\IntegrationTaskController;
use App\Http\Controllers\IntegrationTaskRunController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\NotificationController;
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

    // New simplified task routes
    Route::post('/integrations/{integration}/tasks', [IntegrationTaskController::class, 'store'])
        ->name('integrations.tasks.store');
    Route::put('/integrations/{integration}/tasks/{task}', [IntegrationTaskController::class, 'update'])
        ->name('integrations.tasks.update');
    Route::delete('/integrations/{integration}/tasks/{task}', [IntegrationTaskController::class, 'destroy'])
        ->name('integrations.tasks.destroy');
    Route::post('/integrations/{integration}/tasks/{task}/refresh', [IntegrationTaskController::class, 'refreshHeaders'])
        ->name('integrations.tasks.refresh');
    Route::post('/integrations/{integration}/tasks/{task}/run', [IntegrationTaskController::class, 'run'])
        ->name('integrations.tasks.run');
    Route::post('/integrations/{integration}/tasks/{task}/mappings', [IntegrationTaskController::class, 'saveMappings'])
        ->name('integrations.tasks.mappings');

    // Task runs
    Route::resource('/task-runs', IntegrationTaskRunController::class)
        ->names('task-runs')
        ->only(['index', 'show']);

    // Backward compatibility (old routes redirect to new ones)
    Route::post('/integrations/{integration}/import-profiles', [IntegrationTaskController::class, 'store'])
        ->name('integrations.import-profiles.store');
    Route::put('/integrations/{integration}/import-profiles/{task}', [IntegrationTaskController::class, 'update'])
        ->name('integrations.import-profiles.update');
    Route::delete('/integrations/{integration}/import-profiles/{task}', [IntegrationTaskController::class, 'destroy'])
        ->name('integrations.import-profiles.destroy');
    Route::post('/integrations/{integration}/import-profiles/{task}/refresh-headers', [IntegrationTaskController::class, 'refreshHeaders'])
        ->name('integrations.import-profiles.refresh');
    Route::post('/integrations/{integration}/import-profiles/{task}/run', [IntegrationTaskController::class, 'run'])
        ->name('integrations.import-profiles.run');
    Route::post('/integrations/{integration}/import-profiles/{task}/mappings', [IntegrationTaskController::class, 'saveMappings'])
        ->name('integrations.import-profiles.mappings');

    Route::resource('/products', ProductController::class)
        ->names('products')
        ->except(['show']);
    Route::get('/products/{product}/stock-history', [ProductController::class, 'stockHistory'])
        ->name('products.stock-history');
    Route::get('/notifications', NotificationController::class)->name('notifications.index');
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
        ->names('warehouse.documents');
    Route::post('/warehouse/documents/bulk-status', [WarehouseDocumentController::class, 'bulkStatus'])
        ->name('warehouse.documents.bulk-status');
    
    // Status management routes for warehouse documents
    Route::post('/warehouse/documents/{warehouse_document}/post', [WarehouseDocumentController::class, 'post'])
        ->name('warehouse.documents.post');
    Route::post('/warehouse/documents/{warehouse_document}/cancel', [WarehouseDocumentController::class, 'cancel'])
        ->name('warehouse.documents.cancel');
    Route::post('/warehouse/documents/{warehouse_document}/archive', [WarehouseDocumentController::class, 'archive'])
        ->name('warehouse.documents.archive');
    Route::get('/warehouse/deliveries', [WarehouseDeliveryController::class, 'index'])->name('warehouse.deliveries.index');
    Route::get('/warehouse/settings', [WarehouseSettingsController::class, 'index'])->name('warehouse.settings');
    Route::post('/warehouse/settings', [WarehouseSettingsController::class, 'update'])->name('warehouse.settings.update');
    Route::post('/warehouse/settings/locations', [WarehouseSettingsController::class, 'storeLocation'])->name('warehouse.settings.locations.store');
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

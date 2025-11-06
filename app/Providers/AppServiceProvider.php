<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Register model observers
        \App\Models\WarehouseDocument::observe(\App\Observers\WarehouseDocumentObserver::class);
        \App\Models\WarehouseDocumentItem::observe(\App\Observers\WarehouseDocumentItemObserver::class);

        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\Integration::class,
            \App\Policies\IntegrationPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policies([
            \App\Models\Product::class => \App\Policies\ProductPolicy::class,
            \App\Models\ProductCategory::class => \App\Policies\ProductCategoryPolicy::class,
            \App\Models\ProductCatalog::class => \App\Policies\ProductCatalogPolicy::class,
            \App\Models\Manufacturer::class => \App\Policies\ManufacturerPolicy::class,
            \App\Models\WarehouseDocument::class => \App\Policies\WarehouseDocumentPolicy::class,
            \App\Models\WarehouseLocation::class => \App\Policies\WarehouseLocationPolicy::class,
            \App\Models\Contractor::class => \App\Policies\ContractorPolicy::class,
            \App\Models\InventoryCount::class => \App\Policies\InventoryCountPolicy::class,
        ]);
    }
}

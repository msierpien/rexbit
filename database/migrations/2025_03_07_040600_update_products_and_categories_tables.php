<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                if (! Schema::hasColumn('product_categories', 'catalog_id')) {
                    $table->foreignId('catalog_id')->nullable()->after('user_id');
                }
            });

            if (Schema::hasColumn('product_categories', 'slug')) {
                DB::statement('ALTER TABLE product_categories DROP CONSTRAINT IF EXISTS product_categories_user_id_slug_unique');
            }

            Schema::table('product_categories', function (Blueprint $table): void {
                $table->index('catalog_id');
                $table->unique(['user_id', 'catalog_id', 'slug'], 'product_categories_user_catalog_slug_unique');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_user_id_slug_unique');
                if (! Schema::hasColumn('products', 'catalog_id')) {
                    $table->foreignId('catalog_id')->nullable()->after('user_id');
                }
                if (! Schema::hasColumn('products', 'manufacturer_id')) {
                    $table->foreignId('manufacturer_id')->nullable()->after('category_id');
                }
                if (! Schema::hasColumn('products', 'purchase_price_net')) {
                    $table->decimal('purchase_price_net', 12, 2)->nullable()->after('description');
                }
                if (! Schema::hasColumn('products', 'purchase_vat_rate')) {
                    $table->unsignedInteger('purchase_vat_rate')->nullable()->after('purchase_price_net');
                }
                if (! Schema::hasColumn('products', 'sale_price_net')) {
                    $table->decimal('sale_price_net', 12, 2)->nullable()->after('purchase_vat_rate');
                }
                if (! Schema::hasColumn('products', 'sale_vat_rate')) {
                    $table->unsignedInteger('sale_vat_rate')->nullable()->after('sale_price_net');
                }
            });
        }

        User::with(['productCatalogs', 'productCategories', 'products'])->each(function (User $user): void {
            $defaultCatalog = $user->productCatalogs()->first();

            if (! $defaultCatalog) {
                $defaultCatalog = $user->productCatalogs()->create([
                    'name' => 'Domyślny katalog',
                    'slug' => 'default-catalog-'.$user->id,
                    'description' => null,
                ]);
            }

            $user->productCategories()
                ->whereNull('catalog_id')
                ->update(['catalog_id' => $defaultCatalog->id]);

            $user->products()->whereNull('catalog_id')->update(['catalog_id' => $defaultCatalog->id]);
        });

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (Schema::hasColumn('products', 'price_net')) {
                    DB::statement('UPDATE products SET sale_price_net = price_net WHERE sale_price_net IS NULL');
                    $table->dropColumn('price_net');
                }
                if (Schema::hasColumn('products', 'price_gross')) {
                    $table->dropColumn('price_gross');
                }
                if (Schema::hasColumn('products', 'vat_rate')) {
                    DB::statement('UPDATE products SET sale_vat_rate = vat_rate WHERE sale_vat_rate IS NULL');
                    $table->dropColumn('vat_rate');
                }
            });

            Schema::table('products', function (Blueprint $table): void {
                if (Schema::hasColumn('products', 'catalog_id')) {
                    $table->foreign('catalog_id')->references('id')->on('product_catalogs')->cascadeOnDelete();
                }
                if (Schema::hasColumn('products', 'manufacturer_id')) {
                    $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->nullOnDelete();
                }
                if (Schema::hasColumn('products', 'slug')) {
                    $table->unique(['user_id', 'catalog_id', 'slug'], 'products_user_catalog_slug_unique');
                }
                if (Schema::hasColumn('products', 'sku')) {
                    $table->index(['user_id', 'catalog_id', 'sku'], 'products_user_catalog_sku_index');
                }
            });
        }

        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                if (Schema::hasColumn('product_categories', 'catalog_id')) {
                    $table->foreign('catalog_id')->references('id')->on('product_catalogs')->cascadeOnDelete();
                }
                if (Schema::hasColumn('product_categories', 'slug')) {
                    $table->unique(['user_id', 'catalog_id', 'slug'], 'product_categories_user_catalog_slug_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->decimal('price_net', 12, 2)->nullable();
                $table->decimal('price_gross', 12, 2)->nullable();
                $table->unsignedInteger('vat_rate')->nullable();

                if (Schema::hasColumn('products', 'sale_price_net')) {
                    DB::statement('UPDATE products SET price_net = sale_price_net WHERE sale_price_net IS NOT NULL');
                }
                if (Schema::hasColumn('products', 'sale_vat_rate')) {
                    DB::statement('UPDATE products SET vat_rate = sale_vat_rate WHERE sale_vat_rate IS NOT NULL');
                }

                if (Schema::hasColumn('products', 'catalog_id')) {
                    $table->dropForeign(['catalog_id']);
                    $table->dropColumn('catalog_id');
                }
                if (Schema::hasColumn('products', 'manufacturer_id')) {
                    $table->dropForeign(['manufacturer_id']);
                    $table->dropColumn('manufacturer_id');
                }
                DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_user_catalog_slug_unique');
                DB::statement('DROP INDEX IF EXISTS products_user_catalog_sku_index');
                foreach (['purchase_price_net', 'purchase_vat_rate', 'sale_price_net', 'sale_vat_rate'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                if (Schema::hasColumn('product_categories', 'catalog_id')) {
                    $table->dropForeign(['catalog_id']);
                    $table->dropColumn('catalog_id');
                }
                DB::statement('ALTER TABLE product_categories DROP CONSTRAINT IF EXISTS product_categories_user_catalog_slug_unique');
                $table->unique(['user_id', 'slug']);
            });
        }
    }
};

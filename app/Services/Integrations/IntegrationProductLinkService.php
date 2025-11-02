<?php

namespace App\Services\Integrations;

use App\Integrations\Exceptions\IntegrationConnectionException;
use App\Models\Integration;
use App\Models\IntegrationProductLink;
use App\Models\Product;
use Illuminate\Support\Arr;

class IntegrationProductLinkService
{
    public function __construct(
        protected PrestashopProductService $prestashopProducts,
    ) {
    }

    /**
     * Attempt to automatically link the given products with external products using SKU/EAN.
     *
     * @param  array<int>  $productIds
     * @return array{
     *     created: array<int, IntegrationProductLink>,
     *     updated: array<int, IntegrationProductLink>,
     *     unmatched: array<int, IntegrationProductLink>,
     *     errors: array<int, string>
     * }
     */
    public function autoLink(Integration $integration, array $productIds): array
    {
        $user = $integration->user;

        $products = $user->products()
            ->whereIn('id', $productIds)
            ->get(['id', 'catalog_id', 'name', 'sku', 'ean']);

        $created = [];
        $updated = [];
        $unmatched = [];
        $errors = [];

        foreach ($products as $product) {
            try {
                $link = $this->syncSingleProduct($integration, $product);

                if ($link->external_product_id) {
                    if ($link->wasRecentlyCreated) {
                        $created[] = $link;
                    } else {
                        $updated[] = $link;
                    }
                } else {
                    $unmatched[] = $link;
                }
            } catch (IntegrationConnectionException $exception) {
                $errors[$product->id] = $exception->getMessage();
            }
        }

        return compact('created', 'updated', 'unmatched', 'errors');
    }

    public function updateLink(IntegrationProductLink $link, array $payload): IntegrationProductLink
    {
        $link->fill([
            'external_product_id' => Arr::get($payload, 'external_product_id'),
            'sku' => Arr::get($payload, 'sku', $link->sku),
            'ean' => Arr::get($payload, 'ean', $link->ean),
            'matched_by' => Arr::get($payload, 'matched_by', 'manual'),
            'is_manual' => true,
            'metadata' => Arr::get($payload, 'metadata', $link->metadata),
        ])->save();

        return $link->refresh();
    }

    protected function syncSingleProduct(Integration $integration, Product $product): IntegrationProductLink
    {
        $existing = IntegrationProductLink::firstOrNew([
            'integration_id' => $integration->id,
            'product_id' => $product->id,
        ]);

        $existing->fill([
            'catalog_id' => $product->catalog_id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'is_manual' => false,
        ]);

        $match = null;
        $matchedBy = null;

        if ($product->sku) {
            $match = $this->prestashopProducts->findProductBySkuOrEan($integration, $product->sku, null);
            $matchedBy = $match ? 'sku' : null;
        }

        if (!$match && $product->ean) {
            $match = $this->prestashopProducts->findProductBySkuOrEan($integration, null, $product->ean);
            $matchedBy = $match ? 'ean' : null;
        }

        if ($match) {
            $existing->fill([
                'external_product_id' => Arr::get($match, 'external_id'),
                'matched_by' => $matchedBy,
                'metadata' => [
                    'name' => Arr::get($match, 'name'),
                    'sku' => Arr::get($match, 'sku'),
                    'ean' => Arr::get($match, 'ean'),
                ],
            ]);
        } else {
            $existing->fill([
                'external_product_id' => null,
                'matched_by' => null,
            ]);
        }

        $existing->save();

        return $existing->refresh();
    }
}

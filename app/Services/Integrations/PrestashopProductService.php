<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationConnectionException;
use App\Integrations\IntegrationService;
use App\Models\Integration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class PrestashopProductService
{
    public function __construct(
        protected IntegrationService $integrationService,
    ) {
    }

    /**
     * Fetch products from Prestashop API for the given integration.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function fetchProducts(Integration $integration, array $filters = []): array
    {
        if ($integration->type !== IntegrationType::PRESTASHOP) {
            throw new \InvalidArgumentException('Nieobsługiwany typ integracji dla listy produktów.');
        }

        $config = $this->integrationService->runtimeConfig($integration);

        $perPage = $this->resolvePerPage($filters['per_page'] ?? 15);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $query = [
            'output_format' => 'JSON',
            'display' => 'full',
            'limit' => sprintf('%d,%d', $offset, $perPage),
        ];

        if (!empty($filters['search'])) {
            $query['filter[name]'] = sprintf('[%%%s%%]', $filters['search']);
        }

        $response = $this->performProductRequest($config, $query);

        $items = Arr::get($response->json(), 'products', []);

        $data = collect($items)
            ->map(fn (array $payload) => $this->mapProduct($payload, $config))
            ->values()
            ->all();

        $total = $this->resolveTotal($response->header('X-Pagination-Total') ?? null, $data, $page, $perPage);

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    protected function endpoint(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/api';
    }

    protected function resolvePerPage(mixed $value): int
    {
        $perPage = (int) $value;

        if ($perPage <= 0) {
            $perPage = 15;
        }

        return min(100, $perPage);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function mapProduct(array $payload, array $config): array
    {
        $id = Arr::get($payload, 'id');
        $name = $this->resolveLocalized($payload['name'] ?? null);
        $active = (bool) Arr::get($payload, 'active', true);
        $sku = Arr::get($payload, 'reference');
        $ean = Arr::get($payload, 'ean13') ?? Arr::get($payload, 'ean');
        $description = $this->resolveLocalized($payload['description'] ?? null);
        $quantity = (float) Arr::get($payload, 'quantity', 0);
        $price = Arr::get($payload, 'price');

        return [
            'id' => $id,
            'external_id' => $id,
            'name' => $name ?: sprintf('Produkt #%s', $id),
            'slug' => null,
            'sku' => $sku,
            'ean' => $ean,
            'description' => $description,
            'catalog_id' => null,
            'category_id' => null,
            'manufacturer_id' => null,
            'status' => $active ? 'active' : 'inactive',
            'status_label' => $active ? 'Active' : 'Inactive',
            'catalog' => null,
            'category' => null,
            'manufacturer' => null,
            'purchase_price_net' => null,
            'purchase_vat_rate' => null,
            'sale_price_net' => $price !== null ? (float) $price : null,
            'sale_vat_rate' => null,
            'images' => $this->mapImages($payload, $config),
            'updated_at' => null,
            'stock_summary' => [
                'total_on_hand' => $quantity,
                'total_reserved' => 0,
                'total_incoming' => 0,
                'total_available' => $quantity,
            ],
            'stocks' => [],
        ];
    }

    protected function mapImages(array $payload, array $config): array
    {
        $associations = Arr::get($payload, 'associations.images', []);

        if (!is_array($associations) || count($associations) === 0) {
            return [];
        }

        $base = rtrim($config['base_url'] ?? '', '/');

        return collect($associations)
            ->map(fn ($image) => $this->buildImageUrl($base, $image))
            ->filter()
            ->values()
            ->all();
    }

    protected function buildImageUrl(string $baseUrl, mixed $image): ?string
    {
        $id = null;

        if (is_array($image)) {
            $id = $image['id'] ?? $image['id_image'] ?? null;
        } elseif (is_scalar($image)) {
            $id = $image;
        }

        if (!$id) {
            return null;
        }

        return sprintf('%s/api/images/products/%s', $baseUrl, $id);
    }

    protected function resolveLocalized(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $collection = collect($value);

            if ($collection->isAssoc()) {
                $first = Arr::first($value);

                return is_array($first) ? ($first['value'] ?? null) : $first;
            }

            return $collection
                ->map(function ($entry) {
                    if (is_array($entry)) {
                        return $entry['value'] ?? Arr::first($entry);
                    }

                    return $entry;
                })
                ->filter()
                ->first();
        }

        return null;
    }

    public function findProductBySkuOrEan(Integration $integration, ?string $sku, ?string $ean): ?array
    {
        if ($integration->type !== IntegrationType::PRESTASHOP) {
            throw new \InvalidArgumentException('Nieobsługiwany typ integracji dla wyszukiwania produktu.');
        }

        $config = $this->integrationService->runtimeConfig($integration);

        $candidates = [];

        if ($sku) {
            $candidates[] = [
                'filter[reference]' => sprintf('[%s]', $sku),
                'limit' => '0,1',
            ];
        }

        if ($ean) {
            $candidates[] = [
                'filter[ean13]' => sprintf('[%s]', $ean),
                'limit' => '0,1',
            ];
        }

        foreach ($candidates as $query) {
            $response = $this->performProductRequest($config, array_merge([
                'output_format' => 'JSON',
                'display' => 'full',
            ], $query));

            $items = Arr::get($response->json(), 'products', []);

            if (empty($items)) {
                continue;
            }

            $payload = $items[0];

            return $this->mapProduct($payload, $config);
        }

        return null;
    }

    protected function resolveTotal(?string $header, array $items, int $page, int $perPage): ?int
    {
        if ($header !== null && $header !== '') {
            return (int) $header;
        }

        if ($page === 1 && count($items) < $perPage) {
            return count($items);
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function performProductRequest(array $config, array $query)
    {
        try {
            $response = Http::withBasicAuth($config['api_key'] ?? '', '')
                ->acceptJson()
                ->timeout(10)
                ->get($this->endpoint($config['base_url'] ?? '') . '/products', $query);

            if ($response->failed()) {
                $response->throw();
            }

            return $response;
        } catch (RequestException $exception) {
            throw new IntegrationConnectionException(
                'Nie udało się pobrać danych z Prestashop: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        } catch (\Throwable $exception) {
            throw new IntegrationConnectionException(
                'Wystąpił błąd podczas komunikacji z Prestashop: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }

    public function fetchProductStock(Integration $integration, string $externalProductId): ?float
    {
        if ($integration->type !== IntegrationType::PRESTASHOP) {
            throw new \InvalidArgumentException('Nieobsługiwany typ integracji dla pobierania stanów.');
        }

        $config = $this->integrationService->runtimeConfig($integration);

        try {
            // Pobierz stock_available_id z produktu
            $response = $this->performProductRequest($config, [
                'output_format' => 'JSON',
                'display' => 'full',
                'filter[id]' => sprintf('[%s]', $externalProductId),
                'limit' => '0,1',
            ]);

            $items = Arr::get($response->json(), 'products', []);

            if (empty($items)) {
                return null;
            }

            $stockAvailableId = Arr::get($items[0], 'associations.stock_availables.0.id');

            if (!$stockAvailableId) {
                // Fallback do quantity z products jeśli brak stock_available
                return (float) Arr::get($items[0], 'quantity', 0);
            }

            // Pobierz stock_available
            $stockResponse = Http::withBasicAuth($config['api_key'], '')
                ->acceptJson()
                ->timeout(30)
                ->get($this->endpoint($config['base_url']) . "/stock_availables/{$stockAvailableId}", [
                    'output_format' => 'JSON',
                ]);

            if ($stockResponse->failed()) {
                return (float) Arr::get($items[0], 'quantity', 0);
            }

            $stockData = Arr::get($stockResponse->json(), 'stock_available', []);

            return (float) Arr::get($stockData, 'quantity', 0);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch Prestashop stock', [
                'product_id' => $externalProductId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update product stock in Prestashop
     *
     * @param Integration $integration
     * @param string $externalProductId
     * @param float $quantity
     * @return array{success: bool, error?: string, stock_available_id?: int}
     */
    public function updateProductStock(Integration $integration, string $externalProductId, float $quantity): array
    {
        return $this->mutateStockAvailable($integration, $externalProductId, function (\DOMDocument $dom) use ($quantity): void {
            $this->replaceNodeValue($dom, 'quantity', (string) (int) $quantity);
            $this->replaceNodeValue($dom, 'out_of_stock', (string) ((int) $quantity > 0 ? 0 : 1));
        });
    }

    /**
     * Update multiple product stocks in Prestashop using batch operations
     *
     * @param Integration $integration
     * @param array<string, float> $updates Map of external_product_id => quantity
     * @return array{success: int, failed: int, errors: array}
     */
    public function updateProductStockBatch(Integration $integration, array $updates): array
    {
        if ($integration->type !== IntegrationType::PRESTASHOP) {
            throw new \InvalidArgumentException('Nieobsługiwany typ integracji dla aktualizacji stanów.');
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Prestashop API nie wspiera prawdziwych batch updates,
        // więc używamy concurrent requests z rate limiting
        $config = $this->integrationService->runtimeConfig($integration);
        
        // Limit 10 concurrent requests
        $chunks = array_chunk($updates, 10, true);
        
        foreach ($chunks as $chunk) {
            $promises = [];
            
            foreach ($chunk as $externalProductId => $quantity) {
                $promises[$externalProductId] = function () use ($integration, $externalProductId, $quantity) {
                    return $this->updateProductStock($integration, (string) $externalProductId, $quantity);
                };
            }
            
            // Execute promises concurrently
            foreach ($promises as $externalProductId => $promise) {
                try {
                    $result = $promise();
                    
                    if ($result['success']) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][$externalProductId] = $result['error'] ?? 'Unknown error';
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][$externalProductId] = $e->getMessage();
                }
            }
            
            // Small delay between chunks to avoid rate limiting
            if (count($chunks) > 1) {
                usleep(200000); // 200ms
            }
        }

        return $results;
    }

    public function updateProductAvailability(
        Integration $integration,
        string $externalProductId,
        int $outOfStock,
        string $availableLater
    ): array {
        return $this->mutateStockAvailable($integration, $externalProductId, function (\DOMDocument $dom) use ($outOfStock, $availableLater): void {
            $value = max(0, min(2, $outOfStock));
            $this->replaceNodeValue($dom, 'out_of_stock', (string) $value);
            $this->replaceNodeValue($dom, 'available_later', $availableLater);
        });
    }

    protected function mutateStockAvailable(
        Integration $integration,
        string $externalProductId,
        callable $mutator
    ): array {
        if ($integration->type !== IntegrationType::PRESTASHOP) {
            throw new \InvalidArgumentException('Nieobsługiwany typ integracji dla aktualizacji stanów.');
        }

        $config = $this->integrationService->runtimeConfig($integration);

        try {
            $response = $this->performProductRequest($config, [
                'output_format' => 'JSON',
                'display' => 'full',
                'filter[id]' => sprintf('[%s]', $externalProductId),
                'limit' => '0,1',
            ]);

            $items = Arr::get($response->json(), 'products', []);

            if (empty($items)) {
                return [
                    'success' => false,
                    'error' => "Product {$externalProductId} not found in Prestashop",
                ];
            }

            $stockAvailableId = Arr::get($items[0], 'associations.stock_availables.0.id');

            if (! $stockAvailableId) {
                return [
                    'success' => false,
                    'error' => "No stock_available found for product {$externalProductId}",
                ];
            }

            $stockXmlResponse = Http::withBasicAuth($config['api_key'], '')
                ->timeout(30)
                ->get($this->endpoint($config['base_url']) . "/stock_availables/{$stockAvailableId}");

            if ($stockXmlResponse->failed()) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch stock_available XML: ' . $stockXmlResponse->body(),
                ];
            }

            libxml_use_internal_errors(true);

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;
            $dom->loadXML($stockXmlResponse->body());

            if (! $dom || ! $dom->documentElement) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                return [
                    'success' => false,
                    'error' => 'Invalid XML response: ' . json_encode($errors),
                ];
            }

            $mutator($dom);

            libxml_clear_errors();

            $xmlString = $dom->saveXML();

            $updateResponse = Http::withBasicAuth($config['api_key'], '')
                ->withBody($xmlString, 'application/xml; charset=UTF-8')
                ->timeout(30)
                ->put($this->endpoint($config['base_url']) . "/stock_availables/{$stockAvailableId}");

            if ($updateResponse->failed()) {
                return [
                    'success' => false,
                    'error' => 'Failed to update stock_available: ' . $updateResponse->body(),
                ];
            }

            return [
                'success' => true,
                'stock_available_id' => (int) $stockAvailableId,
            ];
        } catch (RequestException $e) {
            \Log::error('Prestashop stock update failed - Request exception', [
                'product_id' => $externalProductId,
                'error' => $e->getMessage(),
                'response' => $e->response?->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Request failed: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            \Log::error('Prestashop stock update failed', [
                'product_id' => $externalProductId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function replaceNodeValue(\DOMDocument $dom, string $tagName, string $value): void
    {
        $node = $dom->getElementsByTagName($tagName)->item(0);

        if (! $node) {
            return;
        }

        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }

        $node->appendChild($dom->createTextNode($value));
    }
}

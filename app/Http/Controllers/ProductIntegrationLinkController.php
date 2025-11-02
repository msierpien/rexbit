<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Jobs\LinkIntegrationProducts;
use App\Models\IntegrationProductLink;
use App\Services\Integrations\IntegrationProductLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductIntegrationLinkController extends Controller
{
    public function __construct(
        protected IntegrationProductLinkService $linkService,
    ) {
        $this->middleware('auth');
    }

    public function store(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('update', $integration);

        $validated = $request->validate([
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct'],
            'select_all' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
        ]);

        $selectAll = (bool) ($validated['select_all'] ?? false);

        if ($selectAll) {
            $productIds = $this->resolveProductIdsForFilters($integration, $validated['filters'] ?? []);
        } else {
            $productIds = array_map('intval', $validated['product_ids'] ?? []);
        }

        $productIds = array_values(array_unique(array_filter($productIds)));

        if (empty($productIds)) {
            throw ValidationException::withMessages([
                'product_ids' => 'Nie wybrano żadnych produktów do powiązania.',
            ]);
        }

        $chunkSize = 100;
        $chunks = array_chunk($productIds, $chunkSize);

        foreach ($chunks as $chunk) {
            LinkIntegrationProducts::dispatch($integration, $chunk);
        }

        return response()->json([
            'status' => 'queued',
            'queued_product_ids' => $productIds,
            'queued_count' => count($productIds),
        ], 202);
    }

    public function update(Request $request, Integration $integration, IntegrationProductLink $link): JsonResponse
    {
        $this->authorize('update', $integration);

        if ($link->integration_id !== $integration->id) {
            abort(404);
        }

        $validated = $request->validate([
            'external_product_id' => ['nullable', 'string', 'max:191'],
            'sku' => ['nullable', 'string', 'max:191'],
            'ean' => ['nullable', 'string', 'max:191'],
            'matched_by' => ['nullable', 'string', Rule::in(['manual', 'sku', 'ean', 'external_id'])],
            'metadata' => ['nullable', 'array'],
        ]);

        $link = $this->linkService->updateLink($link, $validated);

        return response()->json($this->presentLink($link));
    }

    protected function presentLink(IntegrationProductLink $link): array
    {
        return [
            'id' => $link->id,
            'integration_id' => $link->integration_id,
            'product_id' => $link->product_id,
            'catalog_id' => $link->catalog_id,
            'external_product_id' => $link->external_product_id,
            'sku' => $link->sku,
            'ean' => $link->ean,
            'matched_by' => $link->matched_by,
            'is_manual' => $link->is_manual,
            'metadata' => $link->metadata,
            'created_at' => $link->created_at,
            'updated_at' => $link->updated_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int>
     */
    protected function resolveProductIdsForFilters(Integration $integration, array $filters): array
    {
        $user = $integration->user;

        if (! $user) {
            return [];
        }

        $query = $user->products()->select('products.id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('ean', 'like', '%' . $search . '%');
            });
        }

        if (! empty($filters['catalog'])) {
            $query->where('catalog_id', (int) $filters['catalog']);
        }

        if (! empty($filters['category'])) {
            $query->where('category_id', (int) $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['price_min'])) {
            $query->whereNotNull('sale_price_net')->where('sale_price_net', '>=', (float) $filters['price_min']);
        }

        if (! empty($filters['price_max'])) {
            $query->whereNotNull('sale_price_net')->where('sale_price_net', '<=', (float) $filters['price_max']);
        }

        if (! empty($filters['stock'])) {
            $stockTotals = DB::table('warehouse_stock_totals')
                ->select('product_id')
                ->selectRaw('COALESCE(SUM(on_hand), 0) as total_on_hand')
                ->selectRaw('COALESCE(SUM(reserved), 0) as total_reserved')
                ->selectRaw('COALESCE(SUM(incoming), 0) as total_incoming')
                ->selectRaw('COALESCE(SUM(on_hand - reserved), 0) as total_available')
                ->groupBy('product_id');

            $query->leftJoinSub($stockTotals, 'stock_totals', function ($join): void {
                $join->on('stock_totals.product_id', '=', 'products.id');
            });

            switch ($filters['stock']) {
                case 'available':
                    $query->whereRaw('COALESCE(stock_totals.total_available, 0) > 0');
                    break;
                case 'out':
                    $query->whereRaw('COALESCE(stock_totals.total_available, 0) <= 0');
                    break;
                case 'negative':
                    $query->whereRaw('COALESCE(stock_totals.total_available, 0) < 0');
                    break;
            }
        }

        return $query->pluck('products.id')->all();
    }
}

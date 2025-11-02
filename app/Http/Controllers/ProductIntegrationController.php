<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Services\Integrations\PrestashopProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProductIntegrationController extends Controller
{
    public function __construct(
        protected PrestashopProductService $prestashopProducts,
    ) {
        $this->middleware('auth');
    }

    public function show(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('view', $integration);

        if ($integration->type !== IntegrationType::PRESTASHOP) {
            abort(404);
        }

        if (!Arr::get($integration->config, 'product_listing_enabled')) {
            abort(403, 'Lista produktów dla tej integracji jest wyłączona.');
        }

        $filters = $request->only([
            'search',
            'page',
            'per_page',
            'status',
            'stock',
            'price_min',
            'price_max',
            'sort',
            'direction',
        ]);

        $result = $this->prestashopProducts->fetchProducts($integration, $filters);

        return response()->json([
            'integration' => [
                'id' => $integration->id,
                'name' => $integration->name,
                'type' => $integration->type->value,
            ],
            'products' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}

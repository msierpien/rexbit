<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Test endpoint dla synchronizacji Prestashop
Route::post('/test/prestashop/update-stock/{externalProductId}', function (string $externalProductId, Request $request) {
    $integration = \App\Models\Integration::where('type', 'prestashop')->first();
    
    if (!$integration) {
        return response()->json(['error' => 'No Prestashop integration found'], 404);
    }
    
    $quantity = $request->input('quantity', 10);
    
    $service = app(\App\Services\Integrations\PrestashopProductService::class);
    $result = $service->updateProductStock($integration, $externalProductId, $quantity);
    
    return response()->json([
        'success' => $result['success'],
        'external_product_id' => $externalProductId,
        'quantity' => $quantity,
        'stock_available_id' => $result['stock_available_id'] ?? null,
        'error' => $result['error'] ?? null,
    ]);
});

// Sprawdzenie aktualnego stanu
Route::get('/test/prestashop/get-stock/{externalProductId}', function (string $externalProductId) {
    $integration = \App\Models\Integration::where('type', 'prestashop')->first();
    
    if (!$integration) {
        return response()->json(['error' => 'No Prestashop integration found'], 404);
    }
    
    $service = app(\App\Services\Integrations\PrestashopProductService::class);
    $stock = $service->fetchProductStock($integration, $externalProductId);
    
    return response()->json([
        'external_product_id' => $externalProductId,
        'current_stock' => $stock,
    ]);
});

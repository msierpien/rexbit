<?php

namespace App\Integrations\Contracts;

use App\Models\Integration;

/**
 * Interface dla importu zamówień z różnych platform
 * Pozwala na ujednolicenie importu z PrestaShop, WooCommerce, Magento, itp.
 */
interface OrderImportDriver
{
    /**
     * Czy integracja obsługuje import zamówień
     */
    public function supportsOrderImport(): bool;

    /**
     * Pobierz zamówienia z zewnętrznej platformy
     * 
     * @param Integration $integration
     * @param array $options [
     *   'limit' => int,
     *   'offset' => int, 
     *   'date_from' => string,
     *   'date_to' => string,
     *   'status_filter' => array,
     *   'last_sync_date' => string
     * ]
     * @return array{
     *   'orders': array,
     *   'total_count': int,
     *   'has_more': bool,
     *   'next_offset': ?int
     * }
     */
    public function fetchOrders(Integration $integration, array $options = []): array;

    /**
     * Pobierz szczegóły pojedynczego zamówienia
     * 
     * @param Integration $integration
     * @param string $externalOrderId
     * @return array|null Znormalizowane dane zamówienia lub null jeśli nie znaleziono
     */
    public function fetchOrderDetails(Integration $integration, string $externalOrderId): ?array;

    /**
     * Mapowanie statusów z platformy zewnętrznej na lokalne statusy
     * 
     * @param string $externalStatus
     * @return string Status zgodny z enum w Order model
     */
    public function mapOrderStatus(string $externalStatus): string;

    /**
     * Mapowanie statusów płatności
     */
    public function mapPaymentStatus(array $orderData): string;

    /**
     * Znormalizuj dane zamówienia do standardowego formatu
     * 
     * @param array $rawOrderData Surowe dane z platformy
     * @return array Znormalizowane dane zgodne z Order model
     */
    public function normalizeOrderData(array $rawOrderData): array;

    /**
     * Pobierz ostatnią datę synchronizacji dla tej integracji
     */
    public function getLastSyncDate(Integration $integration): ?string;

    /**
     * Zapisz datę ostatniej synchronizacji
     */
    public function updateLastSyncDate(Integration $integration, string $date): void;

    /**
     * Walidacja czy można importować zamówienia (uprawnienia, konfiguracja)
     */
    public function validateOrderImportAccess(Integration $integration): bool;
}
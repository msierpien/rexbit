<?php

namespace App\Integrations\Drivers;

use App\Integrations\Contracts\OrderImportDriver;
use App\Integrations\IntegrationService;
use App\Models\Integration;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;

/**
 * Import zamówień z PrestaShop przez Web Service API
 * 
 * Obsługuje:
 * - PrestaShop Web Service API
 * - XML parsowanie i normalizacja danych
 * - Mapowanie statusów i walidacja danych
 * - Automatyczne pobieranie dodatkowych zasobów (klienci, adresy, produkty)
 */
class PrestashopApiOrderImportDriver implements OrderImportDriver
{
    protected Client $httpClient;

    public function __construct(
        protected IntegrationService $integrationService
    ) {
        $this->httpClient = new Client(['timeout' => 30]);
    }

    public function supportsOrderImport(): bool
    {
        return true;
    }

    public function fetchOrders(Integration $integration, array $options = []): array
    {
        if (!$this->validateOrderImportAccess($integration)) {
            throw new Exception('Import zamówień nie jest włączony dla tej integracji');
        }

        $config = $this->integrationService->runtimeConfig($integration);
        
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        
        // Buduj filtry dla API
        $filters = [];
        
        if (!empty($options['date_from'])) {
            $filters['date_add'] = '[' . $options['date_from'] . ',]';
        }
        
        if (!empty($options['date_to'])) {
            if (isset($filters['date_add'])) {
                // Aktualizuj istniejący filtr
                $filters['date_add'] = '[' . $options['date_from'] . ',' . $options['date_to'] . ']';
            } else {
                $filters['date_add'] = '[,' . $options['date_to'] . ']';
            }
        }

        if (!empty($options['status_filter'])) {
            $filters['current_state'] = '[' . implode('|', $options['status_filter']) . ']';
        }

        if (!empty($options['last_sync_date'])) {
            $filters['date_upd'] = '[' . $options['last_sync_date'] . ',]';
        }

        // Pobierz listę ID zamówień z filtrami
        $orderIds = $this->fetchOrderIds($config, $filters, $limit, $offset);
        
        if (empty($orderIds['orders'])) {
            return [
                'orders' => [],
                'total_count' => 0,
                'has_more' => false,
                'next_offset' => null
            ];
        }

        // Pobierz szczegółowe dane dla każdego zamówienia
        $orders = [];
        foreach ($orderIds['orders'] as $orderId) {
            try {
                $orderDetail = $this->fetchOrderById($config, $orderId);
                if ($orderDetail) {
                    $orders[] = $this->normalizeOrderData($orderDetail);
                }
            } catch (Exception $e) {
                // Log błąd ale kontynuuj dla pozostałych zamówień
                logger()->error("Błąd pobierania zamówienia {$orderId}: " . $e->getMessage());
            }
        }

        return [
            'orders' => $orders,
            'total_count' => $orderIds['total_count'],
            'has_more' => $orderIds['has_more'],
            'next_offset' => $orderIds['next_offset']
        ];
    }

    public function fetchOrderDetails(Integration $integration, string $externalOrderId): ?array
    {
        $config = $this->integrationService->runtimeConfig($integration);
        
        $orderData = $this->fetchOrderById($config, $externalOrderId);
        if (!$orderData) {
            return null;
        }

        // Pobierz dodatkowe dane
        $customer = $this->fetchCustomerById($config, $orderData['id_customer']);
        $addresses = $this->fetchOrderAddresses($config, $orderData);
        $orderDetails = $this->fetchOrderItems($config, $externalOrderId);

        $normalizedOrder = $this->normalizeOrderData($orderData, $customer);
        $normalizedOrder['items'] = array_map([$this, 'normalizeOrderItem'], $orderDetails);
        $normalizedOrder['addresses'] = $addresses;

        return $normalizedOrder;
    }

    protected function fetchOrderIds(array $config, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $queryParams = [
            'display' => '[id]',
            'limit' => "$offset,$limit",
            'sort' => '[date_add_DESC]'
        ];

        // Dodaj filtry
        foreach ($filters as $field => $value) {
            $queryParams["filter[$field]"] = $value;
        }

        $url = $this->buildApiUrl($config, 'orders', $queryParams);
        
        try {
            $response = $this->httpClient->get($url, [
                'auth' => [$config['api_key'], ''],
                'headers' => ['Accept' => 'application/xml']
            ]);

            $xml = simplexml_load_string($response->getBody()->getContents());
            
            $orderIds = [];
            if (isset($xml->orders->order)) {
                foreach ($xml->orders->order as $order) {
                    $orderIds[] = (string)$order['id'];
                }
            }

            // Policz total (niestety PrestaShop API nie zwraca tego bezpośrednio)
            $totalCount = $this->countTotalOrders($config, $filters);

            return [
                'orders' => $orderIds,
                'total_count' => $totalCount,
                'has_more' => ($offset + $limit) < $totalCount,
                'next_offset' => ($offset + $limit) < $totalCount ? $offset + $limit : null
            ];

        } catch (RequestException $e) {
            throw new Exception('Błąd API PrestaShop: ' . $e->getMessage());
        }
    }

    protected function fetchOrderById(array $config, string $orderId): ?array
    {
        $url = $this->buildApiUrl($config, "orders/$orderId");

        try {
            $response = $this->httpClient->get($url, [
                'auth' => [$config['api_key'], ''],
                'headers' => ['Accept' => 'application/xml']
            ]);

            $xml = simplexml_load_string($response->getBody()->getContents());
            
            if (!isset($xml->order)) {
                return null;
            }

            return $this->xmlToArray($xml->order);

        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw new Exception("Błąd pobierania zamówienia $orderId: " . $e->getMessage());
        }
    }

    protected function fetchCustomerById(array $config, string $customerId): ?array
    {
        if (!$customerId) {
            return null;
        }

        $url = $this->buildApiUrl($config, "customers/$customerId");

        try {
            $response = $this->httpClient->get($url, [
                'auth' => [$config['api_key'], ''],
                'headers' => ['Accept' => 'application/xml']
            ]);

            $xml = simplexml_load_string($response->getBody()->getContents());
            return $this->xmlToArray($xml->customer);

        } catch (RequestException $e) {
            return null;
        }
    }

    protected function fetchOrderAddresses(array $config, array $orderData): array
    {
        $addresses = [];

        // Adres dostawy
        if (!empty($orderData['id_address_delivery'])) {
            $addresses['shipping'] = $this->fetchAddressById($config, $orderData['id_address_delivery']);
        }

        // Adres płatności
        if (!empty($orderData['id_address_invoice'])) {
            $addresses['billing'] = $this->fetchAddressById($config, $orderData['id_address_invoice']);
        }

        return $addresses;
    }

    protected function fetchAddressById(array $config, string $addressId): ?array
    {
        $url = $this->buildApiUrl($config, "addresses/$addressId");

        try {
            $response = $this->httpClient->get($url, [
                'auth' => [$config['api_key'], ''],
                'headers' => ['Accept' => 'application/xml']
            ]);

            $xml = simplexml_load_string($response->getBody()->getContents());
            $address = $this->xmlToArray($xml->address);

            return [
                'external_address_id' => $address['id'],
                'name' => trim(($address['firstname'] ?? '') . ' ' . ($address['lastname'] ?? '')),
                'company' => $address['company'] ?: null,
                'street' => trim(($address['address1'] ?? '') . ' ' . ($address['address2'] ?? '')),
                'city' => $address['city'] ?? null,
                'postal_code' => $address['postcode'] ?? null,
                'country' => $this->getCountryIsoCode($config, $address['id_country'] ?? null),
                'phone' => $address['phone'] ?: $address['phone_mobile'] ?: null,
                'vat_id' => $address['vat_number'] ?: null,
                'prestashop_data' => $address
            ];

        } catch (RequestException $e) {
            return null;
        }
    }

    protected function fetchOrderItems(array $config, string $orderId): array
    {
        $url = $this->buildApiUrl($config, 'order_details', [
            'filter[id_order]' => $orderId
        ]);

        try {
            $response = $this->httpClient->get($url, [
                'auth' => [$config['api_key'], ''],
                'headers' => ['Accept' => 'application/xml']
            ]);

            $xml = simplexml_load_string($response->getBody()->getContents());
            
            $items = [];
            if (isset($xml->order_details->order_detail)) {
                foreach ($xml->order_details->order_detail as $item) {
                    $items[] = $this->xmlToArray($item);
                }
            }

            return $items;

        } catch (RequestException $e) {
            return [];
        }
    }

    protected function normalizeOrderItem(array $item): array
    {
        return [
            'external_product_id' => $item['product_id'] ?? null,
            'product_reference' => $item['product_reference'] ?? null,
            'name' => $item['product_name'] ?? null,
            'sku' => $item['product_reference'] ?? null,
            'ean' => $item['product_ean13'] ?: null,
            'quantity' => (int)($item['product_quantity'] ?? 0),
            'unit_price_net' => (float)($item['unit_price_tax_excl'] ?? 0),
            'unit_price_gross' => (float)($item['unit_price_tax_incl'] ?? 0),
            'price_net' => (float)($item['total_price_tax_excl'] ?? 0),
            'price_gross' => (float)($item['total_price_tax_incl'] ?? 0),
            'vat_rate' => (float)($item['tax_rate'] ?? 0),
            'discount_total' => (float)($item['reduction_amount'] ?? 0),
            'weight' => (float)($item['product_weight'] ?? 0),
            'prestashop_data' => $item
        ];
    }

    public function normalizeOrderData(array $rawOrderData, ?array $customer = null): array
    {
        return [
            'external_order_id' => $rawOrderData['id'] ?? $rawOrderData['id_order'],
            'external_reference' => $rawOrderData['reference'] ?? null,
            'status' => $this->mapOrderStatus($rawOrderData['current_state'] ?? ''),
            'payment_status' => $this->mapPaymentStatus($rawOrderData),
            'customer_name' => $customer ? 
                trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? '')) : null,
            'customer_email' => $customer['email'] ?? null,
            'customer_phone' => null, // Trzeba pobrać z adresu
            'currency' => $this->getCurrencyIsoCode($rawOrderData['id_currency'] ?? null),
            'total_net' => (float)($rawOrderData['total_paid_tax_excl'] ?? $rawOrderData['total_paid'] ?? 0),
            'total_gross' => (float)($rawOrderData['total_paid_tax_incl'] ?? $rawOrderData['total_paid'] ?? 0),
            'shipping_cost' => (float)($rawOrderData['total_shipping'] ?? 0),
            'discount_total' => (float)($rawOrderData['total_discounts'] ?? 0),
            'order_date' => $rawOrderData['date_add'] ?? null,
            'notes' => $rawOrderData['note'] ?: null,
            'prestashop_data' => $rawOrderData
        ];
    }

    public function mapOrderStatus(string $externalStatus): string
    {
        return match($externalStatus) {
            '1' => 'awaiting_payment',
            '2' => 'paid',
            '3' => 'awaiting_fulfillment',
            '4' => 'shipped',
            '5' => 'completed',
            '6' => 'cancelled',
            '7' => 'refunded',
            '8' => 'payment_error',
            '9' => 'on_backorder',
            '10', '11', '12' => 'awaiting_payment',
            '13' => 'awaiting_payment',
            default => 'draft'
        };
    }

    public function mapPaymentStatus(array $orderData): string
    {
        $status = $orderData['current_state'] ?? '';
        $totalPaid = (float)($orderData['total_paid'] ?? 0);
        $totalOrder = (float)($orderData['total_paid_tax_incl'] ?? $orderData['total_paid'] ?? 0);

        if (in_array($status, ['2', '3', '4', '5', '13'])) {
            if ($totalPaid >= $totalOrder) {
                return 'paid';
            } elseif ($totalPaid > 0) {
                return 'partially_paid';
            }
        }

        if ($status === '7') {
            return 'refunded';
        }

        return 'pending';
    }

    public function getLastSyncDate(Integration $integration): ?string
    {
        return Arr::get($integration->meta, 'order_import.last_sync_date');
    }

    public function updateLastSyncDate(Integration $integration, string $date): void
    {
        $meta = $integration->meta ?? [];
        $meta['order_import']['last_sync_date'] = $date;
        $integration->update(['meta' => $meta]);
    }

    public function validateOrderImportAccess(Integration $integration): bool
    {
        return (bool)Arr::get($integration->config, 'order_import_enabled', false);
    }

    protected function countTotalOrders(array $config, array $filters): int
    {
        $queryParams = ['display' => '[id]'];
        
        foreach ($filters as $field => $value) {
            $queryParams["filter[$field]"] = $value;
        }

        $url = $this->buildApiUrl($config, 'orders', $queryParams);

        try {
            $response = $this->httpClient->get($url, [
                'auth' => [$config['api_key'], ''],
                'headers' => ['Accept' => 'application/xml']
            ]);

            $xml = simplexml_load_string($response->getBody()->getContents());
            
            if (!isset($xml->orders->order)) {
                return 0;
            }

            // Jeśli to pojedynczy element, count = 1
            if (!is_array($xml->orders->order)) {
                return 1;
            }

            return count($xml->orders->order);

        } catch (RequestException $e) {
            return 0;
        }
    }

    protected function getCurrencyIsoCode(?string $currencyId): string
    {
        // Można rozbudować o cache lub mapowanie
        return 'EUR'; // Default dla PrestaShop
    }

    protected function getCountryIsoCode(array $config, ?string $countryId): string
    {
        if (!$countryId) {
            return 'PL';
        }

        // Można rozbudować o pobieranie z API lub cache
        return 'PL'; // Default
    }

    protected function buildApiUrl(array $config, string $resource, array $params = []): string
    {
        $baseUrl = rtrim($config['shop_url'], '/') . '/api/' . $resource;
        
        if (!empty($params)) {
            $baseUrl .= '?' . http_build_query($params);
        }

        return $baseUrl;
    }

    protected function xmlToArray($xmlObject): array
    {
        $array = json_decode(json_encode($xmlObject), true);
        
        // Usuń atrybuty XML które nie są potrzebne
        if (isset($array['@attributes'])) {
            unset($array['@attributes']);
        }

        return $array;
    }
}
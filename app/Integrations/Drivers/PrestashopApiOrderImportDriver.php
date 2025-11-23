<?php

namespace App\Integrations\Drivers;

use App\Integrations\Contracts\OrderImportDriver;
use App\Integrations\Concerns\HasStatusMapping;
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
    use HasStatusMapping;
    
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
            // date_upd jest bezpieczniejsza niż date_add dla wielu instalacji PS WebService
            $filters['date_upd'] = '[' . $options['date_from'] . ',]';
        }
        
        if (!empty($options['date_to'])) {
            if (isset($filters['date_upd'])) {
                // Aktualizuj istniejący filtr
                $filters['date_upd'] = '[' . ($options['date_from'] ?? '') . ',' . $options['date_to'] . ']';
            } else {
                $filters['date_upd'] = '[,' . $options['date_to'] . ']';
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
                    // Wyciągnij ID z potencjalnych tablic
                    $idCustomer = $this->extractScalarValue($orderDetail['id_customer'] ?? '');
                    $idAddressDelivery = $this->extractScalarValue($orderDetail['id_address_delivery'] ?? '');
                    $idAddressInvoice = $this->extractScalarValue($orderDetail['id_address_invoice'] ?? '');
                    
                    // Pobierz pełne dane zamówienia (klient, adresy, produkty)
                    $customer = $idCustomer ? $this->fetchCustomerById($config, $idCustomer) : null;
                    $addresses = [];
                    if ($idAddressDelivery) {
                        $addresses['shipping'] = $this->fetchAddressById($config, $idAddressDelivery);
                    }
                    if ($idAddressInvoice) {
                        $addresses['billing'] = $this->fetchAddressById($config, $idAddressInvoice);
                    }
                    $orderItems = $this->fetchOrderItems($config, $orderId);
                    
                    // Normalizuj dane zamówienia
                    $normalizedOrder = $this->normalizeOrderData($orderDetail, $customer, $addresses, $config);
                    $normalizedOrder['items'] = array_map([$this, 'normalizeOrderItem'], $orderItems);
                    $normalizedOrder['addresses'] = $addresses;
                    
                    $orders[] = $normalizedOrder;
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

        $normalizedOrder = $this->normalizeOrderData($orderData, $customer, $addresses, $config);
        $normalizedOrder['items'] = array_map([$this, 'normalizeOrderItem'], $orderDetails);
        $normalizedOrder['addresses'] = $addresses;

        return $normalizedOrder;
    }

    protected function fetchOrderIds(array $config, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $queryParams = [
            'display' => '[id]',
            'limit' => "$offset,$limit",
            // Niektóre instancje PS WebService nie pozwalają sortować po date_add – domyślnie sortujemy po id
            'sort' => '[id_DESC]'
        ];

        // Dodaj filtry
        foreach ($filters as $field => $value) {
            $queryParams["filter[$field]"] = $value;
        }

        $url = $this->buildApiUrl($config, 'orders', $queryParams);
        
        try {
            return $this->performOrderIdRequest($config, $url, $filters, $limit, $offset);
        } catch (RequestException $e) {
            $this->logApiError($e, $url);

            // Fallback 1: usuń sort
            unset($queryParams['sort']);
            $fallbackUrl = $this->buildApiUrl($config, 'orders', $queryParams);

            try {
                return $this->performOrderIdRequest($config, $fallbackUrl, $filters, $limit, $offset);
            } catch (RequestException $e2) {
                $this->logApiError($e2, $fallbackUrl);

                // Fallback 2: jeśli brak filtra daty, ogranicz do ostatnich 7 dni po date_upd
                if (empty($filters['date_upd']) && empty($filters['date_add'])) {
                    $sevenDaysAgo = now()->subDays(7)->format('Y-m-d');
                    $filters['date_upd'] = '[' . $sevenDaysAgo . ',]';
                    $queryParams['filter[date_upd]'] = $filters['date_upd'];
                    try {
                        $dateFilteredUrl = $this->buildApiUrl($config, 'orders', $queryParams);
                        return $this->performOrderIdRequest($config, $dateFilteredUrl, $filters, $limit, $offset);
                    } catch (\Throwable $e3) {
                        $this->logApiError($e3, $this->buildApiUrl($config, 'orders', $queryParams));
                    }
                }

                throw new Exception('Błąd API PrestaShop: ' . ($e2->getMessage()));
            } catch (\Throwable $inner) {
                throw new Exception('Błąd API PrestaShop: ' . $inner->getMessage());
            }
        }
    }

    protected function performOrderIdRequest(array $config, string $url, array $filters, int $limit, int $offset): array
    {
        $response = $this->httpClient->get($url, [
            'auth' => [$config['api_key'], ''],
            'headers' => ['Accept' => 'application/xml']
        ]);

        $xml = simplexml_load_string($response->getBody()->getContents());
        
        $orderIds = [];
        if (isset($xml->orders->order)) {
            foreach ($xml->orders->order as $order) {
                $orderIds[] = (string)$order->id;
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
    }

    protected function logApiError(\Throwable $exception, string $url): void
    {
        $status = method_exists($exception, 'getResponse') ? $exception->getResponse()?->getStatusCode() : null;
        $body = method_exists($exception, 'getResponse') ? (string) $exception->getResponse()?->getBody() : null;

        logger()->error('PrestaShop API error during order fetch', [
            'url' => $url,
            'status' => $status,
            'message' => $exception->getMessage(),
            'body' => $body,
        ]);
    }

    protected function fetchOrderById(array $config, string $orderId): ?array
    {
        $url = $this->buildApiUrl($config, "orders/$orderId", ['display' => 'full']);

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
            'ean' => $item['product_ean13'] ?? null,
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

    public function normalizeOrderData(array $rawOrderData, ?array $customer = null, ?array $addresses = null, ?array $config = null): array
    {
        // Wyciągnij wartości skalarne z potencjalnych tablic
        $paymentMethod = $this->extractScalarValue($rawOrderData['payment'] ?? ($rawOrderData['payment_method'] ?? ''));
        $totalPaid = (float)$this->extractScalarValue($rawOrderData['total_paid'] ?? 0);
        $totalOrder = (float)$this->extractScalarValue($rawOrderData['total_paid_tax_incl'] ?? $rawOrderData['total_paid'] ?? 0);
        $isPaid = $totalPaid > 0 && $totalPaid >= $totalOrder;

        $shippingMethod = null;
        $shippingDetails = null;
        $idCarrier = $this->extractScalarValue($rawOrderData['id_carrier'] ?? '');
        $idCart = $this->extractScalarValue($rawOrderData['id_cart'] ?? '');
        
        if (!empty($idCarrier)) {
            // Spróbuj pobrać nazwę przewoźnika
            $shippingMethod = $config ? $this->getCarrierName($idCarrier, $config) : $idCarrier;
            $shippingDetails = ['carrier_id' => $idCarrier];
            
            // Jeśli mamy id_cart, spróbuj pobrać dane o paczkomacie InPost
            if (!empty($idCart) && $config) {
                $parcelLockerData = $this->fetchInPostParcelLocker($idCart, $config);
                if ($parcelLockerData) {
                    $shippingDetails['parcel_locker'] = $parcelLockerData['machine'];
                    $shippingDetails['tracking_number'] = $parcelLockerData['tracking_number'] ?? null;
                    // Zaktualizuj metodę wysyłki jeśli to InPost
                    if (!empty($parcelLockerData['machine'])) {
                        $shippingMethod = 'InPost Paczkomat ' . $parcelLockerData['machine'];
                    }
                }
            }
        }

        // Obsłuż dane do faktury z adresów
        $invoiceData = null;
        $isCompany = false;
        if ($addresses && isset($addresses['invoice'])) {
            $invoiceData = $addresses['invoice'];
            $isCompany = !empty($invoiceData['company']);
        } elseif (!empty($rawOrderData['invoice_address']) || !empty($rawOrderData['invoice'])) {
            $invoiceData = $rawOrderData['invoice_address'] ?? $rawOrderData['invoice'];
            if (is_array($invoiceData) && !empty($invoiceData['company'])) {
                $isCompany = true;
            }
        }

        // Wyciągnij current_state - może być string lub tablica
        $currentState = $this->extractScalarValue($rawOrderData['current_state'] ?? '');
        $idCurrency = $this->extractScalarValue($rawOrderData['id_currency'] ?? '');
        $externalOrderId = $this->extractScalarValue($rawOrderData['id'] ?? $rawOrderData['id_order'] ?? '');
        $reference = $this->extractScalarValue($rawOrderData['reference'] ?? '');
        $totalNet = (float)$this->extractScalarValue($rawOrderData['total_paid_tax_excl'] ?? $rawOrderData['total_paid'] ?? 0);
        $totalGross = (float)$this->extractScalarValue($rawOrderData['total_paid_tax_incl'] ?? $rawOrderData['total_paid'] ?? 0);
        $shippingCost = (float)$this->extractScalarValue($rawOrderData['total_shipping'] ?? 0);
        $discountTotal = (float)$this->extractScalarValue($rawOrderData['total_discounts'] ?? 0);
        $dateAdd = $this->extractScalarValue($rawOrderData['date_add'] ?? '');
        $dateUpd = $this->extractScalarValue($rawOrderData['date_upd'] ?? '');
        $invoiceDate = $this->extractScalarValue($rawOrderData['invoice_date'] ?? '');
        $note = $this->extractScalarValue($rawOrderData['note'] ?? '');

        // Waliduj daty - PrestaShop często używa '0000-00-00 00:00:00' dla pustych dat
        $validDateAdd = $this->validateDate($dateAdd);
        $validDateUpd = $this->validateDate($dateUpd);
        $validInvoiceDate = $this->validateDate($invoiceDate);

        return [
            'external_order_id' => $externalOrderId,
            'external_reference' => $reference ?: null,
            'status' => $this->mapOrderStatus($currentState, 'prestashop'),
            'payment_status' => $this->mapPaymentStatus($rawOrderData, 'prestashop'),
            'payment_method' => $paymentMethod,
            'is_paid' => $isPaid,
            'customer_name' => $customer ? 
                trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? '')) : null,
            'customer_email' => $customer['email'] ?? null,
            'customer_phone' => null, // Trzeba pobrać z adresu
            'currency' => $this->getCurrencyIsoCode($idCurrency),
            'total_net' => $totalNet,
            'total_gross' => $totalGross,
            'total_paid' => $totalPaid,
            'shipping_cost' => $shippingCost,
            'shipping_method' => $shippingMethod,
            'shipping_details' => $shippingDetails,
            'discount_total' => $discountTotal,
            'order_date' => $validDateAdd,
            'paid_at' => $isPaid ? ($validInvoiceDate ?: $validDateUpd) : null,
            'notes' => $note ?: null,
            'invoice_data' => $invoiceData,
            'is_company' => $isCompany,
            'prestashop_data' => $rawOrderData
        ];
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

    /**
     * Pobiera nazwę przewoźnika z cache lub API
     */
    protected function getCarrierName(string $carrierId): ?string
    {
        static $carrierCache = [];
        
        if (isset($carrierCache[$carrierId])) {
            return $carrierCache[$carrierId];
        }
        
        try {
            $config = $this->integrationService->runtimeConfig(Integration::query()->first());
            $url = $this->buildApiUrl($config, "carriers/$carrierId");
            
            $response = $this->httpClient->get($url, [
                'auth' => [$config['api_key'], ''],
                'headers' => ['Accept' => 'application/xml']
            ]);
            
            $xml = simplexml_load_string($response->getBody()->getContents());
            if (isset($xml->carrier)) {
                $carrier = $this->xmlToArray($xml->carrier);
                $name = $carrier['name'] ?? null;
                $carrierCache[$carrierId] = $name;
                return $name;
            }
        } catch (\Exception $e) {
            // W przypadku błędu zwróć null
        }
        
        return null;
    }

    /**
     * Pobiera dane o paczkomacie InPost dla danego koszyka
     * Wymaga bezpośredniego dostępu do bazy PrestaShop
     */
    protected function fetchInPostParcelLocker(string $cartId): ?array
    {
        try {
            $config = $this->integrationService->runtimeConfig(Integration::query()->first());
            
            // Sprawdź czy mamy dane do połączenia z bazą
            if (empty($config['db_host']) || empty($config['db_name'])) {
                return null;
            }
            
            $prefix = $config['db_prefix'] ?? 'ps_';
            
            // Połącz się z bazą PrestaShop
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['db_host'],
                $config['db_port'] ?? 3306,
                $config['db_name']
            );
            
            $pdo = new \PDO($dsn, $config['db_username'], $config['db_password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            
            // Zapytanie o dane InPost - tabela może mieć różne prefiksy
            $stmt = $pdo->prepare("SELECT machine, tracking_number FROM {$prefix}pminpostpaczkomatylist WHERE id_cart = ? LIMIT 1");
            $stmt->execute([$cartId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['machine'])) {
                return $result;
            }
        } catch (\Exception $e) {
            // Loguj błąd ale nie przerywaj procesu
            logger()->debug("Nie udało się pobrać danych InPost dla koszyka $cartId: " . $e->getMessage());
        }
        
        return null;
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
        // `base_url` jest standardem w konfiguracji integracji; pozostawiamy `shop_url` jako fallback
        $shopBase = $config['base_url'] ?? $config['shop_url'] ?? '';
        $baseUrl = rtrim($shopBase, '/') . '/api/' . $resource;
        
        if (!empty($params)) {
            $baseUrl .= '?' . http_build_query($params);
        }

        return $baseUrl;
    }

    protected function xmlToArray($xmlObject): array
    {
        // Konwertuj SimpleXMLElement do tablicy z zachowaniem wartości CDATA
        $array = [];
        
        // Jeśli to nie jest obiekt SimpleXML, zwróć pusty array
        if (!($xmlObject instanceof \SimpleXMLElement)) {
            return [];
        }
        
        // Iteruj przez dzieci elementu
        foreach ($xmlObject->children() as $key => $value) {
            // Jeśli element ma dzieci, rekursywnie przetwórz
            if ($value->count() > 0) {
                // Sprawdź czy to jest tablica elementów (wielokrotne wystąpienia tego samego klucza)
                if (isset($array[$key])) {
                    if (!is_array($array[$key]) || !isset($array[$key][0])) {
                        $array[$key] = [$array[$key]];
                    }
                    $array[$key][] = $this->xmlToArray($value);
                } else {
                    $array[$key] = $this->xmlToArray($value);
                }
            } else {
                // Element bez dzieci - pobierz wartość tekstową (z CDATA)
                $textValue = (string)$value;
                
                if (isset($array[$key])) {
                    if (!is_array($array[$key]) || !isset($array[$key][0])) {
                        $array[$key] = [$array[$key]];
                    }
                    $array[$key][] = $textValue;
                } else {
                    $array[$key] = $textValue;
                }
            }
        }
        
        return $array;
    }

    /**
     * Wyciąga wartość skalarną z potencjalnie zagnieżdżonej struktury XML
     * PrestaShop API czasem zwraca wartości jako tablice zamiast stringów
     */
    protected function extractScalarValue($value): string
    {
        if (is_array($value)) {
            // Pusta tablica = pusta wartość
            if (empty($value)) {
                return '';
            }
            // Jeśli to tablica z kluczem 0, zwróć ten element (rekurencyjnie)
            if (isset($value[0])) {
                return $this->extractScalarValue($value[0]);
            }
            // Jeśli to tablica z kluczem 'value', zwróć go (rekurencyjnie)
            if (isset($value['value'])) {
                return $this->extractScalarValue($value['value']);
            }
            // Jeśli to tablica asocjacyjna z jednym elementem, spróbuj go wydobyć
            if (count($value) === 1) {
                $first = reset($value);
                return $this->extractScalarValue($first);
            }
            // W przeciwnym razie zwróć pierwszy element
            $first = reset($value);
            // Jeśli pierwszy element to też tablica lub obiekt, spróbuj rekurencyjnie
            if (is_array($first)) {
                return $this->extractScalarValue($first);
            }
            if (is_object($first)) {
                return '';
            }
            return (string)$first;
        }
        return (string)$value;
    }

    /**
     * Waliduje datę z PrestaShop - odrzuca nieprawidłowe daty jak '0000-00-00 00:00:00'
     */
    protected function validateDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        // PrestaShop używa '0000-00-00 00:00:00' dla pustych dat
        if (str_starts_with($date, '0000-00-00') || str_starts_with($date, '-0001')) {
            return null;
        }

        // Sprawdź czy data jest prawidłowa
        try {
            $parsed = new \DateTime($date);
            // Sprawdź czy rok jest rozsądny (nie ujemny, nie za duży)
            if ($parsed->format('Y') < 1970 || $parsed->format('Y') > 2100) {
                return null;
            }
            return $date;
        } catch (\Exception $e) {
            return null;
        }
    }
}

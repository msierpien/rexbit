<?php

namespace App\Integrations\Drivers;

use App\Integrations\Contracts\OrderImportDriver;
use App\Integrations\Concerns\HasStatusMapping;
use App\Integrations\IntegrationService;
use App\Models\Integration;
use PDO;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * Import zamówień z PrestaShop przez bezpośrednie połączenie z bazą MySQL
 * 
 * Obsługuje:
 * - PrestaShop 1.7.x, 8.x  
 * - Mapowanie statusów zamówień i płatności
 * - Import adresów dostawy i płatności
 * - Pozycje zamówienia z produktami
 */
class PrestashopDatabaseOrderImportDriver implements OrderImportDriver
{
    use HasStatusMapping;

    public function __construct(
        protected IntegrationService $integrationService
    ) {}

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
        $connection = $this->createConnection($config);
        
        $prefix = $config['db_prefix'] ?? 'ps_';
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        
        // Buduj query z filtrami
        $whereConditions = ['o.valid = 1']; // Tylko ważne zamówienia
        $params = [];

        if (!empty($options['date_from'])) {
            $whereConditions[] = 'o.date_add >= ?';
            $params[] = $options['date_from'];
        }

        if (!empty($options['date_to'])) {
            $whereConditions[] = 'o.date_add <= ?';
            $params[] = $options['date_to'];
        }

        if (!empty($options['status_filter'])) {
            $placeholders = str_repeat('?,', count($options['status_filter']) - 1) . '?';
            $whereConditions[] = "o.current_state IN ($placeholders)";
            $params = array_merge($params, $options['status_filter']);
        }

        if (!empty($options['last_sync_date'])) {
            $whereConditions[] = 'o.date_upd > ?';
            $params[] = $options['last_sync_date'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // Główne query - pobierz podstawowe dane zamówień
        $sql = "
            SELECT 
                o.id_order,
                o.reference,
                o.id_customer,
                o.id_cart,
                o.current_state,
                o.payment,
                o.total_paid,
                o.total_paid_tax_incl,
                o.total_paid_tax_excl,
                o.total_products,
                o.total_products_wt,
                o.total_shipping,
                o.total_discounts,
                o.id_currency,
                o.conversion_rate,
                o.id_address_delivery,
                o.id_address_invoice,
                o.id_carrier,
                o.note,
                o.date_add,
                o.date_upd,
                
                -- Dane klienta
                c.firstname as customer_firstname,
                c.lastname as customer_lastname,
                c.email as customer_email,
                
                -- Waluta
                curr.iso_code as currency_code,
                
                -- Status
                os.name as status_name
                
            FROM {$prefix}orders o
            LEFT JOIN {$prefix}customer c ON o.id_customer = c.id_customer
            LEFT JOIN {$prefix}currency curr ON o.id_currency = curr.id_currency  
            LEFT JOIN {$prefix}order_state_lang os ON o.current_state = os.id_order_state 
                AND os.id_lang = ? 
            $whereClause
            ORDER BY o.date_add DESC
            LIMIT {$offset}, {$limit}
        ";

        $idLang = $config['id_lang'] ?? 1;
        $params[] = $idLang;

        $stmt = $connection->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Policz total count dla paginacji
        $countSql = "
            SELECT COUNT(*) as total
            FROM {$prefix}orders o
            $whereClause
        ";
        
        // Usuń parametr id_lang dla count query
        $countParams = array_slice($params, 0, -1);
        $countStmt = $connection->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetchColumn();

        // Normalizuj dane zamówień (przekaż połączenie by móc pobierać dodatkowe dane)
        $normalizedOrders = array_map(function ($order) use ($connection, $prefix) {
            return $this->normalizeOrderData($order, $connection, $prefix);
        }, $orders);

        return [
            'orders' => $normalizedOrders,
            'total_count' => (int)$totalCount,
            'has_more' => ($offset + $limit) < $totalCount,
            'next_offset' => ($offset + $limit) < $totalCount ? $offset + $limit : null
        ];
    }

    public function fetchOrderDetails(Integration $integration, string $externalOrderId): ?array
    {
        $config = $this->integrationService->runtimeConfig($integration);
        $connection = $this->createConnection($config);
        $prefix = $config['db_prefix'] ?? 'ps_';
        $idLang = $config['id_lang'] ?? 1;

        // Pobierz pełne dane zamówienia
        $orderSql = "
            SELECT 
                o.*,
                c.firstname as customer_firstname,
                c.lastname as customer_lastname,
                c.email as customer_email,
                curr.iso_code as currency_code,
                os.name as status_name
            FROM {$prefix}orders o
            LEFT JOIN {$prefix}customer c ON o.id_customer = c.id_customer
            LEFT JOIN {$prefix}currency curr ON o.id_currency = curr.id_currency
            LEFT JOIN {$prefix}order_state_lang os ON o.current_state = os.id_order_state 
                AND os.id_lang = ?
            WHERE o.id_order = ?
        ";

        $stmt = $connection->prepare($orderSql);
        $stmt->execute([$idLang, $externalOrderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        // Pobierz pozycje zamówienia
        $itemsSql = "
            SELECT 
                od.*,
                p.reference as product_reference,
                pl.name as product_name_lang
            FROM {$prefix}order_detail od
            LEFT JOIN {$prefix}product p ON od.product_id = p.id_product
            LEFT JOIN {$prefix}product_lang pl ON od.product_id = pl.id_product 
                AND pl.id_lang = ?
            WHERE od.id_order = ?
            ORDER BY od.id_order_detail
        ";

        $stmt = $connection->prepare($itemsSql);
        $stmt->execute([$idLang, $externalOrderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pobierz adresy
        $addresses = $this->fetchOrderAddresses($connection, $prefix, $order);

    $normalizedOrder = $this->normalizeOrderData($order, $connection, $prefix);
        $normalizedOrder['items'] = array_map([$this, 'normalizeOrderItem'], $items);
        $normalizedOrder['addresses'] = $addresses;
        
        // Dodaj adresy do znormalizowanych danych
        if (!empty($addresses['shipping'])) {
            $normalizedOrder['shipping_address'] = $addresses['shipping'];
        }
        
        if (!empty($addresses['billing'])) {
            $normalizedOrder['billing_address'] = $addresses['billing'];
            // Zaktualizuj invoice_data z pełnymi danymi adresu
            $billingAddr = $addresses['billing'];
            $normalizedOrder['invoice_data'] = [
                'company' => $billingAddr['company'] ?? '',
                'vat_number' => $billingAddr['vat_id'] ?? '',
                'name' => $billingAddr['name'] ?? '',
                'address1' => $billingAddr['street'] ?? '',
                'address2' => '',
                'postcode' => $billingAddr['postal_code'] ?? '',
                'city' => $billingAddr['city'] ?? '',
                'phone' => $billingAddr['phone'] ?? '',
                'phone_mobile' => '',
                'alias' => $billingAddr['prestashop_data']['alias'] ?? ''
            ];
            $normalizedOrder['is_company'] = !empty($billingAddr['company']);
        }

        return $normalizedOrder;
    }

    protected function fetchOrderAddresses(PDO $connection, string $prefix, array $order): array
    {
        $addresses = [];

        // Adres dostawy
        if ($order['id_address_delivery']) {
            $addresses['shipping'] = $this->fetchAddress($connection, $prefix, $order['id_address_delivery']);
        }

        // Adres płatności  
        if ($order['id_address_invoice']) {
            $addresses['billing'] = $this->fetchAddress($connection, $prefix, $order['id_address_invoice']);
        }

        return $addresses;
    }

    protected function fetchAddress(PDO $connection, string $prefix, int $addressId): ?array
    {
        $sql = "
            SELECT 
                a.*,
                ct.iso_code as country_iso
            FROM {$prefix}address a
            LEFT JOIN {$prefix}country ct ON a.id_country = ct.id_country
            WHERE a.id_address = ?
        ";

        $stmt = $connection->prepare($sql);
        $stmt->execute([$addressId]);
        $address = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$address) {
            return null;
        }

        return [
            'external_address_id' => $address['id_address'],
            'name' => trim($address['firstname'] . ' ' . $address['lastname']),
            'company' => $address['company'] ?: null,
            'street' => trim($address['address1'] . ' ' . ($address['address2'] ?: '')),
            'city' => $address['city'],
            'postal_code' => $address['postcode'],
            'country' => $address['country_iso'] ?? 'PL',
            'phone' => $address['phone'] ?: $address['phone_mobile'] ?: null,
            'vat_id' => $address['vat_number'] ?: null,
            'prestashop_data' => $address
        ];
    }

    protected function normalizeOrderItem(array $item): array
    {
        return [
            'external_product_id' => $item['product_id'],
            'product_reference' => $item['product_reference'] ?? $item['product_supplier_reference'] ?? null,
            'name' => $item['product_name_lang'] ?? $item['product_name'],
            'sku' => $item['product_reference'],
            'ean' => $item['product_ean13'] ?: null,
            'quantity' => (int)$item['product_quantity'],
            'unit_price_net' => (float)$item['unit_price_tax_excl'],
            'unit_price_gross' => (float)$item['unit_price_tax_incl'],
            'price_net' => (float)$item['total_price_tax_excl'],
            'price_gross' => (float)$item['total_price_tax_incl'],
            'vat_rate' => (float)$item['tax_rate'],
            'discount_total' => (float)($item['reduction_amount'] ?? 0),
            'weight' => (float)($item['product_weight'] ?? 0),
            'prestashop_data' => $item
        ];
    }

    public function normalizeOrderData(array $rawOrderData, ?PDO $connection = null, string $prefix = 'ps_'): array
    {
        // podstawowe mapowanie
        $paymentMethod = $rawOrderData['payment'] ?? null;
        $totalPaid = (float)($rawOrderData['total_paid'] ?? 0);
        $totalOrder = (float)($rawOrderData['total_paid_tax_incl'] ?? $rawOrderData['total_paid'] ?? 0);

        $isPaid = $totalPaid > 0 && $totalPaid >= $totalOrder;

        $shippingMethod = null;
        $shippingDetails = null;

        // jeśli mamy połączenie do bazy PrestaShop, pobierz nazwę przewoźnika
        if ($connection && !empty($rawOrderData['id_carrier'])) {
            try {
                $stmt = $connection->prepare("SELECT name FROM {$prefix}carrier WHERE id_carrier = ? LIMIT 1");
                $stmt->execute([$rawOrderData['id_carrier']]);
                $carrier = $stmt->fetch(PDO::FETCH_ASSOC);
                $shippingMethod = $carrier['name'] ?? null;
                $shippingDetails = ['carrier_id' => $rawOrderData['id_carrier']];
                
                // Pobierz dane o paczkomacie InPost jeśli dostępne
                if (!empty($rawOrderData['id_cart'])) {
                    try {
                        $inpostStmt = $connection->prepare("SELECT machine, tracking_number FROM juuw_pminpostpaczkomatylist WHERE id_cart = ? LIMIT 1");
                        $inpostStmt->execute([$rawOrderData['id_cart']]);
                        $inpostData = $inpostStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($inpostData && !empty($inpostData['machine'])) {
                            $shippingDetails['parcel_locker'] = $inpostData['machine'];
                            $shippingDetails['tracking_number'] = $inpostData['tracking_number'] ?? null;
                            // Jeśli to InPost, zaktualizuj metodę wysyłki
                            if (stripos($shippingMethod, 'inpost') !== false || !empty($inpostData['machine'])) {
                                $shippingMethod = 'InPost Paczkomat ' . $inpostData['machine'];
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore - błąd pobierania danych InPost nie przerywa procesu
                    }
                }
            } catch (\Exception $e) {
                // ignore - pozostaw null
            }
        } else {
            // fallback do pola id_carrier value
            $shippingMethod = $rawOrderData['id_carrier'] ?? null;
            $shippingDetails = ['carrier_id' => $rawOrderData['id_carrier'] ?? null];
        }

        // invoice / billing data - jeżeli dostępne w surowych danych, w przeciwnym razie zostanie pobrane przy fetchOrderDetails
        $invoiceData = null;
        $isCompany = false;
        if (!empty($rawOrderData['id_address_invoice']) && $connection) {
            try {
                $stmt = $connection->prepare("SELECT company, vat_number, address1, address2, postcode, city, phone, phone_mobile, alias FROM {$prefix}address WHERE id_address = ? LIMIT 1");
                $stmt->execute([$rawOrderData['id_address_invoice']]);
                $addr = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($addr) {
                    $invoiceData = $addr;
                    $isCompany = !empty($addr['company']);
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        return [
            'external_order_id' => $rawOrderData['id_order'] ?? ($rawOrderData['id'] ?? null),
            'external_reference' => $rawOrderData['reference'] ?? null,
            'status' => $this->mapOrderStatus((string)($rawOrderData['current_state'] ?? ''), 'prestashop'),
            'payment_status' => $this->mapPaymentStatus($rawOrderData, 'prestashop'),
            'payment_method' => $paymentMethod,
            'is_paid' => $isPaid,
            'customer_name' => trim(($rawOrderData['customer_firstname'] ?? '') . ' ' . ($rawOrderData['customer_lastname'] ?? '')) ?: null,
            'customer_email' => $rawOrderData['customer_email'] ?? null,
            'customer_phone' => $rawOrderData['customer_phone'] ?? null,
            'currency' => $rawOrderData['currency_code'] ?? ($rawOrderData['iso_code'] ?? 'EUR'),
            'total_net' => (float)($rawOrderData['total_paid_tax_excl'] ?? $rawOrderData['total_paid'] ?? 0),
            'total_gross' => (float)($rawOrderData['total_paid_tax_incl'] ?? $rawOrderData['total_paid'] ?? 0),
            'total_paid' => $totalPaid,
            'shipping_cost' => (float)($rawOrderData['total_shipping'] ?? 0),
            'shipping_method' => $shippingMethod,
            'shipping_details' => $shippingDetails,
            'discount_total' => (float)($rawOrderData['total_discounts'] ?? 0),
            'order_date' => $rawOrderData['date_add'] ?? null,
            'notes' => $rawOrderData['note'] ?? null,
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

    public function validateOrderImportAccess(Integration $integration): bool
    {
        // Sprawdź czy import jest włączony w konfiguracji
        return (bool)Arr::get($integration->config, 'order_import_enabled', false);
    }

    protected function createConnection(array $config): PDO
    {
        // PrestaShop używa MySQL/MariaDB, nie PostgreSQL
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_port'],
            $config['db_name']
        );

        return new PDO($dsn, $config['db_username'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
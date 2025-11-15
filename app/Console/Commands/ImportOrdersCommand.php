<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderAddress;
use App\Integrations\IntegrationFactory;
use App\Integrations\Contracts\OrderImportDriver;
use Illuminate\Console\Command;
use Exception;
use Carbon\Carbon;

/**
 * Uniwersalna komenda do importu zamówień z różnych platform e-commerce
 * 
 * Obsługuje:
 * - PrestaShop (API i Database)
 * - WooCommerce (w przyszłości)  
 * - Magento (w przyszłości)
 * - Inne platformy przez OrderImportDriver
 */
class ImportOrdersCommand extends Command
{
    protected $signature = 'orders:import 
        {integration? : ID lub nazwa integracji (opcjonalnie, domyślnie wszystkie)}
        {--limit=50 : Maksymalna liczba zamówień do importu na integrację}
        {--date-from= : Data od której importować (YYYY-MM-DD)}
        {--date-to= : Data do której importować (YYYY-MM-DD)}
        {--status= : Filtruj po statusach zamówień (rozdzielone przecinkami)}
        {--force : Wymuszaj import nawet jeśli brak zmian}
        {--dry-run : Tylko symulacja, bez zapisywania danych}';

    protected $description = 'Import zamówień z platform e-commerce (PrestaShop, WooCommerce, etc.)';

    protected IntegrationFactory $integrationFactory;
    protected int $totalImported = 0;
    protected int $totalErrors = 0;

    public function __construct(IntegrationFactory $integrationFactory)
    {
        parent::__construct();
        $this->integrationFactory = $integrationFactory;
    }

    public function handle(): int
    {
        $this->info('🛒 Uruchamianie importu zamówień...');

        try {
            $integrations = $this->getIntegrationsToProcess();
            
            if ($integrations->isEmpty()) {
                $this->warn('Brak integracji do przetworzenia.');
                return Command::SUCCESS;
            }

            $this->info("Znaleziono {$integrations->count()} integracji do przetworzenia.");

            foreach ($integrations as $integration) {
                $this->processIntegration($integration);
            }

            $this->displaySummary();

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Krytyczny błąd podczas importu: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function getIntegrationsToProcess()
    {
        $integrationId = $this->argument('integration');
        
        $query = Integration::where('status', 'active')
            ->where('config->order_import_enabled', true);

        if ($integrationId) {
            if (is_numeric($integrationId)) {
                $query->where('id', $integrationId);
            } else {
                $query->where('name', 'like', "%{$integrationId}%");
            }
        }

        return $query->get();
    }

    protected function processIntegration(Integration $integration): void
    {
        $this->line('');
        $this->info("📋 Przetwarzanie: {$integration->name} (ID: {$integration->id})");

        try {
            $driver = $this->integrationFactory->makeOrderImportDriver($integration->type);
            
            if (!$driver->supportsOrderImport()) {
                $this->warn('  ⚠️ Integracja nie obsługuje importu zamówień');
                return;
            }

            if (!$driver->validateOrderImportAccess($integration)) {
                $this->warn('  ⚠️ Import zamówień nie jest włączony w konfiguracji');
                return;
            }

            $this->importOrdersFromIntegration($integration, $driver);

        } catch (Exception $e) {
            $this->error("  ❌ Błąd przetwarzania integracji: {$e->getMessage()}");
            $this->totalErrors++;
        }
    }

    protected function importOrdersFromIntegration(Integration $integration, OrderImportDriver $driver): void
    {
        $options = $this->buildImportOptions($integration, $driver);
        
        $this->line("  🔍 Opcje importu: " . json_encode($options, JSON_UNESCAPED_UNICODE));

        $offset = 0;
        $importedCount = 0;
        $hasMore = true;

        while ($hasMore) {
            try {
                $currentOptions = array_merge($options, ['offset' => $offset]);
                $result = $driver->fetchOrders($integration, $currentOptions);

                if (empty($result['orders'])) {
                    $this->line('  📭 Brak zamówień do importu');
                    break;
                }

                $batchCount = count($result['orders']);
                $this->line("  📦 Pobrano {$batchCount} zamówień (offset: {$offset})");

                foreach ($result['orders'] as $orderData) {
                    if ($this->importSingleOrder($integration, $driver, $orderData)) {
                        $importedCount++;
                        $this->totalImported++;
                    }
                }

                $hasMore = $result['has_more'] ?? false;
                $offset = $result['next_offset'] ?? ($offset + $batchCount);

                // Respektuj limit
                if ($importedCount >= $options['limit']) {
                    $this->line("  🎯 Osiągnięto limit {$options['limit']} zamówień");
                    break;
                }

            } catch (Exception $e) {
                $this->error("  ❌ Błąd podczas pobierania zamówień: {$e->getMessage()}");
                $this->totalErrors++;
                break;
            }
        }

        // Aktualizuj datę ostatniej synchronizacji
        if ($importedCount > 0 && !$this->option('dry-run')) {
            $driver->updateLastSyncDate($integration, Carbon::now()->toDateTimeString());
        }

        $this->info("  ✅ Zaimportowano {$importedCount} zamówień z integracji {$integration->name}");
    }

    protected function importSingleOrder(Integration $integration, OrderImportDriver $driver, array $orderData): bool
    {
        if ($this->option('dry-run')) {
            $this->line("    [DRY-RUN] Zamówienie: {$orderData['external_order_id']} - {$orderData['customer_name']}");
            return true;
        }

        try {
            // Sprawdź czy zamówienie już istnieje
            $existingOrder = Order::where('integration_id', $integration->id)
                ->where('external_order_id', $orderData['external_order_id'])
                ->first();

            if ($existingOrder && !$this->option('force')) {
                // Sprawdź czy są zmiany
                if (!$this->hasOrderChanges($existingOrder, $orderData)) {
                    return false; // Brak zmian
                }
                
                // Aktualizuj istniejące
                $this->updateExistingOrder($existingOrder, $orderData);
                $this->line("    📝 Zaktualizowano: {$orderData['external_order_id']}");
            } else {
                // Pobierz szczegółowe dane zamówienia
                $fullOrderData = $driver->fetchOrderDetails($integration, $orderData['external_order_id']);
                if (!$fullOrderData) {
                    throw new Exception("Nie udało się pobrać szczegółów zamówienia {$orderData['external_order_id']}");
                }

                // Utwórz nowe zamówienie
                $order = $this->createNewOrder($integration, $fullOrderData);
                $this->line("    ➕ Utworzono: {$orderData['external_order_id']} (ID: {$order->id})");
            }

            return true;

        } catch (Exception $e) {
            $this->error("    ❌ Błąd importu zamówienia {$orderData['external_order_id']}: {$e->getMessage()}");
            $this->totalErrors++;
            return false;
        }
    }

    protected function createNewOrder(Integration $integration, array $orderData): Order
    {
        // Utwórz główne zamówienie
        $order = Order::create([
            'integration_id' => $integration->id,
            'user_id' => $integration->user_id,
            'external_order_id' => $orderData['external_order_id'],
            'external_reference' => $orderData['external_reference'],
            'number' => $this->generateOrderNumber(),
            'status' => $orderData['status'],
            'payment_status' => $orderData['payment_status'],
            'payment_method' => $orderData['payment_method'] ?? null,
            'is_paid' => $orderData['is_paid'] ?? false,
            'customer_name' => $orderData['customer_name'],
            'customer_email' => $orderData['customer_email'],
            'customer_phone' => $orderData['customer_phone'],
            'currency' => $orderData['currency'],
            'total_net' => $orderData['total_net'],
            'total_gross' => $orderData['total_gross'],
            'total_paid' => $orderData['total_paid'] ?? 0,
            'shipping_cost' => $orderData['shipping_cost'],
            'shipping_method' => $orderData['shipping_method'] ?? null,
            'shipping_details' => $orderData['shipping_details'] ?? null,
            'invoice_data' => $orderData['invoice_data'] ?? null,
            'is_company' => $orderData['is_company'] ?? false,
            'discount_total' => $orderData['discount_total'],
            'order_date' => $orderData['order_date'],
            'notes' => $orderData['notes'],
            'prestashop_data' => $orderData['prestashop_data'] ?? null,
        ]);

        // Dodaj pozycje zamówienia
        if (!empty($orderData['items'])) {
            foreach ($orderData['items'] as $itemData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'external_product_id' => $itemData['external_product_id'],
                    'product_reference' => $itemData['product_reference'],
                    'name' => $itemData['name'],
                    'sku' => $itemData['sku'],
                    'ean' => $itemData['ean'],
                    'quantity' => $itemData['quantity'],
                    'unit_price_net' => $itemData['unit_price_net'],
                    'unit_price_gross' => $itemData['unit_price_gross'],
                    'price_net' => $itemData['price_net'],
                    'price_gross' => $itemData['price_gross'],
                    'vat_rate' => $itemData['vat_rate'],
                    'discount_total' => $itemData['discount_total'],
                    'weight' => $itemData['weight'],
                    'prestashop_data' => $itemData['prestashop_data'] ?? null,
                ]);
            }
        }

        // Dodaj adresy
        if (!empty($orderData['addresses'])) {
            foreach ($orderData['addresses'] as $type => $addressData) {
                if ($addressData) {
                    OrderAddress::create([
                        'order_id' => $order->id,
                        'type' => $type, // 'billing', 'shipping'
                        'external_address_id' => $addressData['external_address_id'],
                        'name' => $addressData['name'],
                        'company' => $addressData['company'],
                        'street' => $addressData['street'],
                        'city' => $addressData['city'],
                        'postal_code' => $addressData['postal_code'],
                        'country' => $addressData['country'],
                        'phone' => $addressData['phone'],
                        'vat_id' => $addressData['vat_id'],
                        'prestashop_data' => $addressData['prestashop_data'] ?? null,
                    ]);
                }
            }
        }

        return $order;
    }

    protected function updateExistingOrder(Order $order, array $orderData): void
    {
        $order->update([
            'status' => $orderData['status'],
            'payment_status' => $orderData['payment_status'],
            'payment_method' => $orderData['payment_method'] ?? $order->payment_method,
            'is_paid' => $orderData['is_paid'] ?? $order->is_paid,
            'total_net' => $orderData['total_net'],
            'total_gross' => $orderData['total_gross'],
            'total_paid' => $orderData['total_paid'] ?? $order->total_paid,
            'shipping_cost' => $orderData['shipping_cost'],
            'shipping_method' => $orderData['shipping_method'] ?? $order->shipping_method,
            'shipping_details' => $orderData['shipping_details'] ?? $order->shipping_details,
            'invoice_data' => $orderData['invoice_data'] ?? $order->invoice_data,
            'is_company' => $orderData['is_company'] ?? $order->is_company,
            'discount_total' => $orderData['discount_total'],
            'notes' => $orderData['notes'],
            'prestashop_data' => $orderData['prestashop_data'] ?? $order->prestashop_data,
        ]);
    }

    protected function hasOrderChanges(Order $order, array $orderData): bool
    {
        return $order->status !== $orderData['status'] ||
               $order->payment_status !== $orderData['payment_status'] ||
               abs($order->total_gross - $orderData['total_gross']) > 0.01;
    }

    protected function buildImportOptions(Integration $integration, OrderImportDriver $driver): array
    {
        $options = [
            'limit' => (int)$this->option('limit')
        ];

        if ($dateFrom = $this->option('date-from')) {
            $options['date_from'] = $dateFrom;
        } elseif (!$this->option('force')) {
            // Użyj daty ostatniej synchronizacji
            if ($lastSync = $driver->getLastSyncDate($integration)) {
                $options['date_from'] = $lastSync;
            }
        }

        if ($dateTo = $this->option('date-to')) {
            $options['date_to'] = $dateTo;
        }

        if ($statuses = $this->option('status')) {
            $options['status_filter'] = explode(',', $statuses);
        }

        return $options;
    }

    protected function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid());
    }

    protected function displaySummary(): void
    {
        $this->line('');
        $this->info('📊 PODSUMOWANIE IMPORTU');
        $this->line("✅ Zaimportowano łącznie: {$this->totalImported} zamówień");
        
        if ($this->totalErrors > 0) {
            $this->line("❌ Błędy: {$this->totalErrors}");
        }

        if ($this->option('dry-run')) {
            $this->warn('⚠️ To była symulacja - żadne dane nie zostały zapisane');
        }
    }
}
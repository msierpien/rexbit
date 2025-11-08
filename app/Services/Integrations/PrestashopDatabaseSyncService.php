<?php

namespace App\Services\Integrations;

use App\Integrations\IntegrationService;
use App\Models\IntegrationTask;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrestashopDatabaseSyncService
{
    protected IntegrationTask $task;
    protected array $config;
    protected ?\PDO $connection = null;

    public function __construct(IntegrationTask $task, ?IntegrationService $integrationService = null)
    {
        $this->task = $task;
        
        // Use IntegrationService to get decrypted config
        if ($integrationService) {
            $this->config = $integrationService->runtimeConfig($task->integration);
        } else {
            // Fallback for testing - use config directly (will be encrypted)
            $this->config = $task->integration->config ?? [];
        }
    }

    /**
     * Get MySQL PDO connection to PrestaShop database
     */
    protected function getConnection(): \PDO
    {
        if ($this->connection) {
            return $this->connection;
        }

        $host = $this->config['db_host'] ?? 'localhost';
        $port = $this->config['db_port'] ?? 3306;
        $database = $this->config['db_name'] ?? '';
        $username = $this->config['db_username'] ?? '';
        $password = $this->config['db_password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            $this->connection = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            return $this->connection;
        } catch (\PDOException $e) {
            Log::error('PrestaShop DB connection failed', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Nie można połączyć się z bazą danych PrestaShop: ' . $e->getMessage());
        }
    }

    /**
     * Sync supplier availability to PrestaShop using direct database queries
     * 
     * @param int|null $limit Maximum number of products to sync
     * @return array Statistics: ['success' => int, 'skipped' => int, 'errors' => int]
     */
    public function syncToPrestashop(?int $limit = null): array
    {
        $stats = [
            'success' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        // Get sync settings from integration meta
        $meta = $this->task->integration->meta ?? [];
        $minThreshold = $meta['supplier_min_stock_threshold'] ?? 0;
        $syncOnlyChanged = $meta['supplier_sync_only_changed'] ?? false;
        $availableText = $meta['supplier_available_text'] ?? 'Dostępne';
        $unavailableText = $meta['supplier_unavailable_text'] ?? 'Niedostępne';
        $deliveryTemplate = $meta['supplier_delivery_text_template'] ?? 'Dostawa w {days} dni';

        $prefix = $this->config['db_prefix'] ?? 'ps_';
        $idShop = $this->config['id_shop'] ?? 1;
        $idLang = $this->config['id_lang'] ?? 1;

        // Get products with PrestaShop mapping
        $query = Product::query()
            ->whereHas('integrationLinks', function ($q) {
                $q->where('integration_id', $this->task->integration_id)
                    ->whereNotNull('external_id');
            })
            ->with(['integrationLinks' => function ($q) {
                $q->where('integration_id', $this->task->integration_id);
            }]);

        if ($limit) {
            $query->limit($limit);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            return $stats;
        }

        // Prepare batch updates
        $availableProducts = [];
        $unavailableProducts = [];
        $availabilityTexts = [];

        foreach ($products as $product) {
            $link = $product->integrationLinks->first();
            if (!$link) {
                continue;
            }

            $externalId = $link->external_id;
            $quantity = $product->supplier_availability ?? 0;
            $deliveryDays = $product->supplier_delivery_days ?? 0;

            // Check if product should be considered available
            $isAvailable = $quantity >= $minThreshold;

            // If sync_only_changed is enabled, check if availability changed
            if ($syncOnlyChanged) {
                $lastAvailability = $link->metadata['last_availability'] ?? null;
                if ($lastAvailability !== null && $lastAvailability === $isAvailable) {
                    $stats['skipped']++;
                    continue;
                }
            }

            // Prepare availability text
            $availabilityText = $isAvailable ? $availableText : $unavailableText;
            if ($isAvailable && $deliveryDays > 0) {
                $availabilityText = str_replace('{days}', $deliveryDays, $deliveryTemplate);
            }

            if ($isAvailable) {
                $availableProducts[] = (int)$externalId;
            } else {
                $unavailableProducts[] = (int)$externalId;
            }

            $availabilityTexts[(int)$externalId] = $availabilityText;

            // Update metadata
            $link->metadata = array_merge($link->metadata ?? [], [
                'last_availability' => $isAvailable,
                'last_stock_quantity' => $quantity,
                'last_synced_at' => now()->toIso8601String(),
            ]);
            $link->save();
        }

        try {
            $pdo = $this->getConnection();

            // Update stock_available table (out_of_stock field)
            // out_of_stock: 0 = Deny orders, 1 = Allow orders, 2 = Use default
            if (!empty($availableProducts)) {
                $ids = implode(',', $availableProducts);
                $sql = "UPDATE {$prefix}stock_available 
                        SET out_of_stock = 1 
                        WHERE id_product IN ({$ids}) AND id_shop = {$idShop}";
                $pdo->exec($sql);
                $stats['success'] += count($availableProducts);
            }

            if (!empty($unavailableProducts)) {
                $ids = implode(',', $unavailableProducts);
                $sql = "UPDATE {$prefix}stock_available 
                        SET out_of_stock = 0 
                        WHERE id_product IN ({$ids}) AND id_shop = {$idShop}";
                $pdo->exec($sql);
                $stats['success'] += count($unavailableProducts);
            }

            // Update product_lang table (available_now, available_later fields)
            if (!empty($availabilityTexts)) {
                foreach ($availabilityTexts as $productId => $text) {
                    $isAvailable = in_array($productId, $availableProducts);
                    
                    if ($isAvailable) {
                        // Product is available - set available_now
                        $stmt = $pdo->prepare(
                            "UPDATE {$prefix}product_lang 
                             SET available_now = :text, available_later = '' 
                             WHERE id_product = :id AND id_shop = :shop AND id_lang = :lang"
                        );
                    } else {
                        // Product is unavailable - set available_later
                        $stmt = $pdo->prepare(
                            "UPDATE {$prefix}product_lang 
                             SET available_now = '', available_later = :text 
                             WHERE id_product = :id AND id_shop = :shop AND id_lang = :lang"
                        );
                    }

                    $stmt->execute([
                        'text' => $text,
                        'id' => $productId,
                        'shop' => $idShop,
                        'lang' => $idLang,
                    ]);
                }
            }

            Log::info('PrestaShop DB sync completed', [
                'task_id' => $this->task->id,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('PrestaShop DB sync failed', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage(),
            ]);
            
            $stats['errors'] = count($availableProducts) + count($unavailableProducts);
            $stats['success'] = 0;
        }

        return $stats;
    }

    /**
     * Close database connection
     */
    public function __destruct()
    {
        $this->connection = null;
    }
}

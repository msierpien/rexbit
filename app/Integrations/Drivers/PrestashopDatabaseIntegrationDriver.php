<?php

namespace App\Integrations\Drivers;

use App\Enums\IntegrationType;
use App\Integrations\Contracts\IntegrationDriver;
use App\Integrations\Exceptions\IntegrationConnectionException;
use App\Models\Integration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PrestashopDatabaseIntegrationDriver implements IntegrationDriver
{
    /**
     * {@inheritdoc}
     */
    public function type(): IntegrationType
    {
        return IntegrationType::PRESTASHOP_DB;
    }

    /**
     * {@inheritdoc}
     */
    public function validationRules(?Integration $integration = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_name' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => $integration
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'db_prefix' => ['required', 'string', 'max:10'],
            'id_shop' => ['nullable', 'integer', 'min:1'],
            'id_lang' => ['nullable', 'integer', 'min:1'],
            'product_listing_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfig(): array
    {
        return [
            'description' => null,
            'db_host' => 'localhost',
            'db_port' => 3306,
            'db_name' => '',
            'db_username' => '',
            'db_password' => '',
            'db_prefix' => 'ps_',
            'id_shop' => 1,
            'id_lang' => 1,
            'product_listing_enabled' => false,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function sanitizeConfig(array $config): array
    {
        $dbHost = trim((string) Arr::get($config, 'db_host', 'localhost'));
        $dbPort = (int) Arr::get($config, 'db_port', 3306);
        $dbName = trim((string) Arr::get($config, 'db_name', ''));
        $dbUsername = trim((string) Arr::get($config, 'db_username', ''));
        $dbPrefix = trim((string) Arr::get($config, 'db_prefix', 'ps_'));
        $idShop = (int) Arr::get($config, 'id_shop', 1);
        $idLang = (int) Arr::get($config, 'id_lang', 1);

        $normalized = [
            'description' => Arr::get($config, 'description'),
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_username' => $dbUsername,
            'db_prefix' => $dbPrefix,
            'id_shop' => $idShop > 0 ? $idShop : 1,
            'id_lang' => $idLang > 0 ? $idLang : 1,
            'product_listing_enabled' => filter_var(
                Arr::get($config, 'product_listing_enabled', false),
                FILTER_VALIDATE_BOOL
            ),
        ];

        // Only include password if provided
        if (Arr::has($config, 'db_password')) {
            $normalized['db_password'] = trim((string) Arr::get($config, 'db_password', ''));
        }

        return $normalized;
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(array $config): bool
    {
        $config = $this->sanitizeConfig($config);

        try {
            // Create temporary connection
            config(['database.connections.prestashop_test' => [
                'driver' => 'mysql',
                'host' => $config['db_host'],
                'port' => $config['db_port'],
                'database' => $config['db_name'],
                'username' => $config['db_username'],
                'password' => $config['db_password'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ]]);

            // Test connection by querying configuration table
            $prefix = $config['db_prefix'];
            $result = DB::connection('prestashop_test')
                ->table($prefix . 'configuration')
                ->where('name', 'PS_SHOP_NAME')
                ->first();

            // Clean up temporary connection
            DB::purge('prestashop_test');

            if ($result === null) {
                throw new IntegrationConnectionException(
                    'Połączenie udane, ale nie znaleziono tabeli konfiguracji PrestaShop. Sprawdź prefiks tabel.'
                );
            }

            return true;
        } catch (\PDOException $e) {
            DB::purge('prestashop_test');
            throw new IntegrationConnectionException(
                'Nie można połączyć z bazą PrestaShop: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        } catch (IntegrationConnectionException $e) {
            DB::purge('prestashop_test');
            throw $e;
        } catch (\Exception $e) {
            DB::purge('prestashop_test');
            throw new IntegrationConnectionException(
                'Błąd podczas testowania połączenia: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
}

<?php

namespace App\Integrations\Drivers;

use App\Enums\IntegrationType;
use App\Integrations\Contracts\IntegrationDriver;
use App\Integrations\Exceptions\IntegrationConnectionException;
use App\Models\Integration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class PrestashopIntegrationDriver implements IntegrationDriver
{
    /**
     * {@inheritdoc}
     */
    public function type(): IntegrationType
    {
        return IntegrationType::PRESTASHOP;
    }

    /**
     * {@inheritdoc}
     */
    public function validationRules(?Integration $integration = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'base_url' => ['required', 'url'],
            'api_key' => $integration
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8'],
            'product_listing_enabled' => ['sometimes', 'boolean'],
            'inventory_sync_mode' => ['required', 'in:disabled,local_to_presta,prestashop_to_local'],
            'inventory_sync_interval_minutes' => ['nullable', 'integer', 'min:5'],
            'primary_warehouse_id' => ['nullable', 'integer', 'exists:warehouse_locations,id', 'required_if:inventory_sync_mode,local_to_presta'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfig(): array
    {
        return [
            'description' => null,
            'base_url' => '',
            'api_key' => '',
            'product_listing_enabled' => false,
            'inventory_sync_mode' => 'disabled',
            'inventory_sync_interval_minutes' => 180,
            'primary_warehouse_id' => null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function sanitizeConfig(array $config): array
    {
        $baseUrl = rtrim((string) Arr::get($config, 'base_url', ''), '/');

        $mode = Arr::get($config, 'inventory_sync_mode', 'disabled');
        if (! in_array($mode, ['disabled', 'local_to_presta', 'prestashop_to_local'], true)) {
            $mode = 'disabled';
        }

        $interval = (int) Arr::get($config, 'inventory_sync_interval_minutes', 180);
        if ($interval < 5) {
            $interval = 5;
        }

        $primaryWarehouse = Arr::get($config, 'primary_warehouse_id');
        $primaryWarehouse = $mode === 'local_to_presta' ? ($primaryWarehouse ? (int) $primaryWarehouse : null) : null;

        $normalized = [
            'description' => Arr::get($config, 'description'),
            'base_url' => $baseUrl,
            'product_listing_enabled' => filter_var(
                Arr::get($config, 'product_listing_enabled', false),
                FILTER_VALIDATE_BOOL
            ),
            'inventory_sync_mode' => $mode,
            'inventory_sync_interval_minutes' => $interval,
            'primary_warehouse_id' => $primaryWarehouse,
        ];

        if (Arr::has($config, 'api_key')) {
            $normalized['api_key'] = trim((string) Arr::get($config, 'api_key', ''));
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
            $response = Http::withBasicAuth($config['api_key'], '')
                ->acceptJson()
                ->timeout(10)
                ->get($this->buildEndpoint($config['base_url']));

            if ($response->failed()) {
                $response->throw();
            }
        } catch (RequestException $exception) {
            throw new IntegrationConnectionException(
                'Połączenie z Prestashop nie powiodło się: '.$exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        } catch (\Throwable $exception) {
            throw new IntegrationConnectionException(
                'Nieoczekiwany błąd podczas łączenia z Prestashop: '.$exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }

        return true;
    }

    protected function buildEndpoint(string $baseUrl): string
    {
        return $baseUrl.'/api';
    }
}

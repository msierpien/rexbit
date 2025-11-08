<?php

namespace App\Integrations;

use App\Enums\IntegrationStatus;
use App\Enums\IntegrationType;
use App\Integrations\Contracts\IntegrationDriver;
use App\Integrations\Exceptions\IntegrationConnectionException;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

class IntegrationService
{
    public function __construct(
        protected IntegrationFactory $factory,
        protected ValidationFactory $validator,
    ) {
    }

    /**
     * Return driver for a given type.
     */
    public function driver(IntegrationType|string $type): IntegrationDriver
    {
        return $this->factory->make($type);
    }

    /**
     * Create a new integration for the given user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, IntegrationType|string $type, array $attributes): Integration
    {
        $driver = $this->driver($type);

        $payload = $this->validatePayload($driver, $attributes);
        $config = $this->prepareConfigForStorage($driver, $payload);

        $integration = new Integration([
            'name' => $payload['name'],
            'type' => $driver->type(),
            'status' => IntegrationStatus::INACTIVE,
            'config' => $config,
        ]);

        $user->integrations()->save($integration);

        return $integration;
    }

    /**
     * Update integration configuration.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Integration $integration, array $attributes): Integration
    {
        $driver = $this->driver($integration->type);

        $payload = $this->validatePayload($driver, $attributes, $integration);

        // Extract supplier sync settings if present
        $meta = $integration->meta ?? [];
        if (isset($attributes['supplier_min_stock_threshold']) || 
            isset($attributes['supplier_sync_availability_text']) ||
            isset($attributes['supplier_sync_only_changed']) ||
            isset($attributes['supplier_available_text']) ||
            isset($attributes['supplier_unavailable_text']) ||
            isset($attributes['supplier_delivery_text_template'])) {
            
            $meta['supplier_sync'] = [
                'min_stock_threshold' => (int) ($attributes['supplier_min_stock_threshold'] ?? 20),
                'sync_availability_text' => (bool) ($attributes['supplier_sync_availability_text'] ?? true),
                'sync_only_changed' => (bool) ($attributes['supplier_sync_only_changed'] ?? true),
                'available_text' => $attributes['supplier_available_text'] ?? 'Dostępny u dostawcy',
                'unavailable_text' => $attributes['supplier_unavailable_text'] ?? 'Produkt niedostępny',
                'delivery_text_template' => $attributes['supplier_delivery_text_template'] ?? 'Wysyłka za :days dni',
            ];
        }

        $integration->fill([
            'name' => $payload['name'],
            'config' => $this->prepareConfigForStorage($driver, $payload, $integration),
            'meta' => $meta,
        ])->save();

        return $integration->refresh();
    }

    /**
     * Perform a health check for integration credentials.
     *
     * @throws IntegrationConnectionException
     */
    public function testConnection(Integration $integration): bool
    {
        $driver = $this->driver($integration->type);

        $config = $this->decryptConfigForRuntime($integration, $driver);

        $success = $driver->testConnection($config);

        if ($success) {
            $integration->forceFill([
                'status' => IntegrationStatus::ACTIVE,
                'last_synced_at' => now(),
            ])->save();
        }

        return $success;
    }

    /**
     * Fetch integrations for a user filtered by type.
     *
     * @param  IntegrationType|string|null  $type
     * @return Collection<int, Integration>
     */
    public function list(User $user, IntegrationType|string|null $type = null): Collection
    {
        return $user->integrations()
            ->when($type, fn ($query) => $query->where('type', $type instanceof IntegrationType ? $type->value : $type))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Validate payload according to driver rules.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function validatePayload(IntegrationDriver $driver, array $attributes, ?Integration $integration = null): array
    {
        return $this->validator->make(
            $attributes,
            $driver->validationRules($integration)
        )->validate();
    }

    /**
     * Prepare configuration payload for storage by encrypting sensitive values.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function prepareConfigForStorage(IntegrationDriver $driver, array $payload, ?Integration $integration = null): array
    {
        $sanitized = $driver->sanitizeConfig($payload);
        $existing = $integration?->config ?? [];

        // Merge config but exclude encrypted fields
        $config = array_merge($existing, Arr::except($sanitized, ['api_key', 'db_password']));

        // Handle api_key encryption (for PrestaShop API integration)
        $apiKey = Arr::get($sanitized, 'api_key');
        if ($apiKey) {
            $config['api_key'] = Crypt::encryptString($apiKey);
        } elseif ($integration && isset($existing['api_key'])) {
            $config['api_key'] = $existing['api_key'];
        }

        // Handle db_password encryption (for PrestaShop Database integration)
        $dbPassword = Arr::get($sanitized, 'db_password');
        if ($dbPassword) {
            $config['db_password'] = Crypt::encryptString($dbPassword);
        } elseif ($integration && isset($existing['db_password'])) {
            $config['db_password'] = $existing['db_password'];
        }

        return $config;
    }

    /**
     * Retrieve configuration for runtime use (with decrypted secrets).
     *
     * @return array<string, mixed>
     */
    protected function decryptConfigForRuntime(Integration $integration, IntegrationDriver $driver): array
    {
        $config = $integration->config ?? [];

        // Decrypt api_key (for PrestaShop API integration)
        if (isset($config['api_key'])) {
            try {
                $config['api_key'] = Crypt::decryptString($config['api_key']);
            } catch (\Throwable $exception) {
                throw new IntegrationConnectionException(
                    'Nie udało się odszyfrować klucza API integracji.',
                    (int) $exception->getCode(),
                    $exception
                );
            }
        }

        // Decrypt db_password (for PrestaShop Database integration)
        if (isset($config['db_password'])) {
            try {
                $config['db_password'] = Crypt::decryptString($config['db_password']);
            } catch (\Throwable $exception) {
                throw new IntegrationConnectionException(
                    'Nie udało się odszyfrować hasła do bazy danych.',
                    (int) $exception->getCode(),
                    $exception
                );
            }
        }

        return $driver->sanitizeConfig($config);
    }

    /**
     * Retrieve sanitized configuration with decrypted secrets for runtime usage.
     *
     * @return array<string, mixed>
     */
    public function runtimeConfig(Integration $integration): array
    {
        $driver = $this->driver($integration->type);

        return $this->decryptConfigForRuntime($integration, $driver);
    }
}

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
            'base_url' => ['required', 'url'],
            'api_key' => $integration
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfig(): array
    {
        return [
            'base_url' => '',
            'api_key' => '',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function sanitizeConfig(array $config): array
    {
        $baseUrl = rtrim((string) Arr::get($config, 'base_url', ''), '/');

        $normalized = [
            'base_url' => $baseUrl,
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

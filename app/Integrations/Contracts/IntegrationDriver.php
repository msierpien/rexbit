<?php

namespace App\Integrations\Contracts;

use App\Enums\IntegrationType;
use App\Models\Integration;

interface IntegrationDriver
{
    /**
     * Return the supported integration type.
     */
    public function type(): IntegrationType;

    /**
     * Return validation rules for configuration payload.
     *
     * @return array<string, mixed>
     */
    public function validationRules(?Integration $integration = null): array;

    /**
     * Provide default configuration structure.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array;

    /**
     * Normalize configuration before persisting.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function sanitizeConfig(array $config): array;

    /**
     * Attempt to perform lightweight connection check.
     *
     * @param  array<string, mixed>  $config
     */
    public function testConnection(array $config): bool;
}

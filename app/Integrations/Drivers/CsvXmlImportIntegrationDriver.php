<?php

namespace App\Integrations\Drivers;

use App\Enums\IntegrationType;
use App\Integrations\Contracts\IntegrationDriver;
use App\Models\Integration;

class CsvXmlImportIntegrationDriver implements IntegrationDriver
{
    public function type(): IntegrationType
    {
        return IntegrationType::CSV_XML_IMPORT;
    }

    public function validationRules(?Integration $integration = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'description' => null,
        ];
    }

    public function sanitizeConfig(array $config): array
    {
        return [
            'description' => $config['description'] ?? null,
        ];
    }

    public function testConnection(array $config): bool
    {
        // Import from CSV/XML is local/URL based – no remote connectivity check needed.
        return true;
    }
}

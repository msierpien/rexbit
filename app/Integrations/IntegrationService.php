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
        $config = $driver->sanitizeConfig($payload);

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

        $integration->fill([
            'name' => $payload['name'],
            'config' => $driver->sanitizeConfig($payload),
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

        $success = $driver->testConnection($integration->config ?? []);

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
}

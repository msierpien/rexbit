<?php

namespace App\Integrations;

use App\Enums\IntegrationType;
use App\Integrations\Contracts\IntegrationDriver;
use InvalidArgumentException;

class IntegrationFactory
{
    /**
     * Resolve the driver instance for the given integration type.
     */
    public function make(IntegrationType|string $type): IntegrationDriver
    {
        $key = $type instanceof IntegrationType ? $type->value : $type;

        $driverClass = config("integrations.drivers.{$key}");

        if (! $driverClass) {
            throw new InvalidArgumentException("Brak zarejestrowanego drivera dla integracji typu [{$key}].");
        }

        $driver = app($driverClass);

        if (! $driver instanceof IntegrationDriver) {
            throw new InvalidArgumentException("Klasa [{$driverClass}] nie implementuje interfejsu IntegrationDriver.");
        }

        return $driver;
    }
}

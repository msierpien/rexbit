<?php

namespace App\Console;

use App\Console\Commands\RunIntegrationImportsCommand;
use App\Console\Commands\SyncSupplierAvailabilityCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * @var array<int, class-string>
     */
    protected $commands = [
        RunIntegrationImportsCommand::class,
        SyncSupplierAvailabilityCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('integrations:run-imports')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Synchronize inventory with Prestashop integrations
        $schedule->command('integrations:sync-inventory')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Synchronize supplier availability to Prestashop (every 30 minutes)
        $schedule->command('supplier:sync-availability --prestashop=1')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        if (file_exists(base_path('routes/console.php'))) {
            require base_path('routes/console.php');
        }
    }
}

<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                [
                    'label' => 'Aktywni użytkownicy',
                    'value' => 128,
                    'trend' => '+12% w ostatnim tygodniu',
                    'trendVariant' => 'success',
                ],
                [
                    'label' => 'Nowe zgłoszenia',
                    'value' => 24,
                    'trend' => '6 oczekuje na reakcję',
                    'trendVariant' => 'warning',
                ],
            ],
            'latestLogins' => [
                ['name' => 'Anna Kowalska', 'time' => '5 minut temu'],
                ['name' => 'Jan Nowak', 'time' => '12 minut temu'],
                ['name' => 'Magda Wiśniewska', 'time' => '31 minut temu'],
            ],
            'roleStats' => [
                ['label' => 'Administratorzy', 'count' => 8, 'progress' => 20],
                ['label' => 'Użytkownicy', 'count' => 120, 'progress' => 80],
            ],
        ]);
    }
}

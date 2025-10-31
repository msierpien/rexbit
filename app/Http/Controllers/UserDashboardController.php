<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class UserDashboardController extends Controller
{
    /**
     * Display the user dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('User/Dashboard', [
            'stats' => [
                ['label' => 'Ostatnia aktywność', 'value' => '2 godziny temu', 'trend' => 'Dziękujemy, że korzystasz z platformy', 'variant' => 'neutral'],
                ['label' => 'Powiadomienia', 'value' => 5, 'trend' => '2 nowe od ostatniego logowania', 'variant' => 'warning'],
            ],
            'accountStatus' => 'Aktywne',
            'tasks' => [
                ['title' => 'Uzupełnij profil', 'description' => 'Dodaj zdjęcie i podstawowe informacje', 'eta' => '15 min', 'badge' => 'primary'],
                ['title' => 'Przeczytaj raport', 'description' => 'Podsumowanie aktywności z ostatniego tygodnia', 'eta' => '45 min', 'badge' => 'warning'],
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Products/Settings', [
            'suggestions' => [
                [
                    'title' => 'Jednostki miary',
                    'description' => 'Zdefiniuj domyślne jednostki sprzedaży oraz stanów magazynowych.',
                ],
                [
                    'title' => 'Stawki VAT',
                    'description' => 'Ustal listę stawek VAT dostępnych podczas edycji produktu.',
                ],
                [
                    'title' => 'Własne atrybuty',
                    'description' => 'Twórz atrybuty (np. kolor, rozmiar), które później przypiszesz do produktów.',
                ],
            ],
        ]);
    }
}

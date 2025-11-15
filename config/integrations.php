<?php

return [
    'drivers' => [
        'prestashop' => App\Integrations\Drivers\PrestashopIntegrationDriver::class,
        'prestashop-db' => App\Integrations\Drivers\PrestashopDatabaseIntegrationDriver::class,
        'csv-xml-import' => App\Integrations\Drivers\CsvXmlImportIntegrationDriver::class,
    ],
    
    'order_import_drivers' => [
        'prestashop' => App\Integrations\Drivers\PrestashopApiOrderImportDriver::class,
        'prestashop-db' => App\Integrations\Drivers\PrestashopDatabaseOrderImportDriver::class,
    ],

    // Mapowanie statusów zamówień dla różnych platform
    'status_mapping' => [
        'prestashop' => [
            'order_statuses' => [
                '1' => 'awaiting_payment',       // Oczekiwanie na płatność czekiem
                '2' => 'paid',                   // Płatność zaakceptowana  
                '3' => 'awaiting_fulfillment',   // Przygotowanie w toku
                '4' => 'shipped',                // Dostarczane
                '5' => 'completed',              // Dostarczone
                '6' => 'cancelled',              // Anulowane
                '7' => 'refunded',               // Zwróconych pieniędzy
                '8' => 'payment_error',          // Błąd płatności
                '9' => 'awaiting_fulfillment',   // Zamówienie oczekujące (opłacone)
                '10' => 'awaiting_payment',      // Oczekiwanie na płatność przelewem
                '11' => 'paid',                  // Płatność przyjęta
                '12' => 'awaiting_payment',      // Zamówienie oczekujące (nieopłacone)
                '13' => 'awaiting_payment',      // Oczekiwanie na płatność przy odbiorze
                '14' => 'awaiting_payment',      // Oczekiwanie na płatność
                '15' => 'partially_refunded',    // Częściowy zwrot
                '16' => 'partially_paid',        // Częściowa płatność
                '17' => 'awaiting_payment',      // Pomyślna autoryzacja
            ],
            'payment_statuses' => [
                // Płatności opłacone
                'paid_statuses' => ['2', '3', '4', '5', '9', '11'],
                // Płatności częściowo opłacone
                'partial_paid_statuses' => ['16'],
                // Płatności zwrócone
                'refunded_statuses' => ['7'],
                // Płatności częściowo zwrócone  
                'partial_refunded_statuses' => ['15'],
                // Błędy płatności
                'error_statuses' => ['8'],
                // Pozostałe = oczekujące
            ],
        ],
        
        'woocommerce' => [
            'order_statuses' => [
                'pending' => 'awaiting_payment',
                'processing' => 'awaiting_fulfillment', 
                'on-hold' => 'awaiting_payment',
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                'refunded' => 'refunded',
                'failed' => 'payment_error',
            ],
            'payment_statuses' => [
                'paid_statuses' => ['processing', 'completed'],
                'refunded_statuses' => ['refunded'],
                'error_statuses' => ['failed'],
            ],
        ],
    ],
    'import' => [
        'chunk_size' => env('INTEGRATION_IMPORT_CHUNK', 200),
    ],
    'supplier_sync' => [
        // Co synchronizować do PrestaShop
        'sync_stock_quantity' => env('SUPPLIER_SYNC_STOCK', false), // Czy aktualizować stan magazynowy
        'sync_wholesale_price' => env('SUPPLIER_SYNC_PRICE', false), // Czy aktualizować cenę zakupu
        'sync_availability_text' => env('SUPPLIER_SYNC_TEXT', true), // Czy aktualizować tekst dostępności
        
        // Opcje formatowania
        'delivery_text_template' => env('SUPPLIER_DELIVERY_TEXT', 'Wysyłka za :days dni'),
        'available_text' => env('SUPPLIER_AVAILABLE_TEXT', 'Dostępny u dostawcy'),
        'unavailable_text' => env('SUPPLIER_UNAVAILABLE_TEXT', 'Produkt niedostępny'),
        
        // Czy nadpisać stan PrestaShop stanem dostawcy
        'override_prestashop_stock' => env('SUPPLIER_OVERRIDE_STOCK', false),
    ],
];

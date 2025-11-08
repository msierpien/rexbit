<?php

return [
    'drivers' => [
        'prestashop' => App\Integrations\Drivers\PrestashopIntegrationDriver::class,
        'prestashop-db' => App\Integrations\Drivers\PrestashopDatabaseIntegrationDriver::class,
        'csv-xml-import' => App\Integrations\Drivers\CsvXmlImportIntegrationDriver::class,
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

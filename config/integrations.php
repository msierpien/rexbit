<?php

return [
    'drivers' => [
        'prestashop' => App\Integrations\Drivers\PrestashopIntegrationDriver::class,
        'csv-xml-import' => App\Integrations\Drivers\CsvXmlImportIntegrationDriver::class,
    ],
    'import' => [
        'chunk_size' => env('INTEGRATION_IMPORT_CHUNK', 200),
    ],
];

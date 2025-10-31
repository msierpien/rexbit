<?php

namespace App\Services\Integrations\Import;

use InvalidArgumentException;

class ImportParserFactory
{
    public function make(string $format): ImportParser
    {
        return match (strtolower($format)) {
            'csv' => app(CsvImportParser::class),
            'xml' => app(XmlImportParser::class),
            default => throw new InvalidArgumentException("Nieobsługiwany format importu [{$format}]."),
        };
    }
}

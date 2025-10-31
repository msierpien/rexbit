<?php

namespace App\Services\Integrations\Import;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SimpleXMLElement;

class XmlImportParser implements ImportParser
{
    public function detectHeaders(string $path, array $options = []): array
    {
        $records = iterator_to_array($this->records($path, $options, 1));

        if (empty($records)) {
            return [];
        }

        $record = Arr::first($records);

        if (! is_array($record)) {
            return [];
        }

        return array_keys($record);
    }

    public function iterate(string $path, array $options = []): \Generator
    {
        yield from $this->records($path, $options);
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    protected function records(string $path, array $options = [], ?int $limit = null): \Generator
    {
        $recordPath = (string) ($options['record_path'] ?? '');
        $recordPath = trim($recordPath);

        $xml = simplexml_load_file($path, SimpleXMLElement::class, LIBXML_NOCDATA);

        if (! $xml) {
            throw new \RuntimeException('Nie udało się wczytać pliku XML.');
        }

        $nodes = $recordPath !== ''
            ? $xml->xpath($recordPath) ?: []
            : $xml->children();

        $count = 0;

        foreach ($nodes as $node) {
            $record = $this->nodeToArray($node);

            yield $record;

            $count++;

            if ($limit !== null && $count >= $limit) {
                break;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function nodeToArray(SimpleXMLElement $node): array
    {
        $json = json_decode(json_encode($node, JSON_THROW_ON_ERROR), true);

        if (! is_array($json)) {
            return [];
        }

        $flatten = Arr::dot($json);

        $normalized = [];

        foreach ($flatten as $key => $value) {
            $normalized[Str::snake($key)] = $value;
        }

        return $normalized;
    }
}

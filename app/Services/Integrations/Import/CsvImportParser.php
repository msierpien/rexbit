<?php

namespace App\Services\Integrations\Import;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CsvImportParser implements ImportParser
{
    public function detectHeaders(string $path, array $options = []): array
    {
        $delimiter = (string) Arr::get($options, 'delimiter', ';');
        $hasHeader = (bool) Arr::get($options, 'has_header', true);

        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('Nie udało się otworzyć pliku CSV.');
        }

        try {
            $row = fgetcsv($handle, 0, $delimiter);
        } finally {
            fclose($handle);
        }

        if (! $row) {
            return [];
        }

        if ($hasHeader) {
            return array_values(array_map(fn ($value) => $this->normalizeHeader($value), $row));
        }

        return collect($row)
            ->keys()
            ->map(fn (int $index) => 'column_'.($index + 1))
            ->all();
    }

    public function iterate(string $path, array $options = []): \Generator
    {
        $delimiter = (string) Arr::get($options, 'delimiter', ';');
        $hasHeader = (bool) Arr::get($options, 'has_header', true);

        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('Nie udało się otworzyć pliku CSV.');
        }

        $headers = [];

        if ($hasHeader) {
            $headers = fgetcsv($handle, 0, $delimiter) ?: [];
            $headers = array_map(fn ($header) => $this->normalizeHeader($header), $headers);
        }

        $rowIndex = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowIndex++;

            if ($hasHeader) {
                yield $this->combineRow($headers, $row);
            } else {
                yield $this->combineRowWithIndexes($row);
            }
        }

        fclose($handle);
    }

    protected function normalizeHeader(?string $value): string
    {
        $value = $value ?? '';
        $value = trim($value);
        $value = Str::snake(Str::ascii($value));

        return $value !== '' ? $value : 'column_'.uniqid();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    protected function combineRow(array $headers, array $row): array
    {
        $normalized = [];
        $count = max(count($headers), count($row));

        for ($i = 0; $i < $count; $i++) {
            $key = $headers[$i] ?? 'column_'.($i + 1);
            $normalized[$key] = $row[$i] ?? null;
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    protected function combineRowWithIndexes(array $row): array
    {
        $normalized = [];

        foreach ($row as $index => $value) {
            $normalized['column_'.($index + 1)] = $value;
        }

        return $normalized;
    }
}

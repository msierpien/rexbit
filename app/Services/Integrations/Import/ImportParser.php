<?php

namespace App\Services\Integrations\Import;

interface ImportParser
{
    /**
     * Detect headers/fields available in given source.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    public function detectHeaders(string $path, array $options = []): array;

    /**
     * Iterate rows as associative arrays (header => value).
     *
     * @param  array<string, mixed>  $options
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterate(string $path, array $options = []): \Generator;
}

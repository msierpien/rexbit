<?php

namespace App\Services\Integrations\Import;

use App\Models\IntegrationImportProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImportSourceResolver
{
    /**
     * Resolve local filesystem path for given profile source.
     *
     * @return array{path: string, temporary: bool}
     */
    public function resolve(IntegrationImportProfile $profile): array
    {
        return match ($profile->source_type) {
            'file' => $this->resolveFilePath($profile),
            'url' => $this->downloadRemoteSource($profile),
            default => throw new \InvalidArgumentException('Nieobsługiwane źródło importu: '.$profile->source_type),
        };
    }

    protected function resolveFilePath(IntegrationImportProfile $profile): array
    {
        $disk = Storage::disk('local');
        $path = $profile->source_location;

        if (! $disk->exists($path)) {
            throw new \RuntimeException('Plik źródłowy importu nie istnieje.');
        }

        return [
            'path' => $disk->path($path),
            'temporary' => false,
        ];
    }

    protected function downloadRemoteSource(IntegrationImportProfile $profile): array
    {
        $response = Http::timeout(20)
            ->withHeaders([
                'User-Agent' => 'RexBitImporter/1.0',
            ])
            ->get($profile->source_location);

        if ($response->failed()) {
            $response->throw();
        }

        $extension = $profile->format === 'xml' ? 'xml' : 'csv';
        $temporaryPath = storage_path('app/import-cache');

        if (! is_dir($temporaryPath)) {
            mkdir($temporaryPath, 0775, true);
        }

        $filename = sprintf('profile_%d_%s.%s', $profile->id, uniqid(), $extension);
        $path = $temporaryPath.'/'.$filename;

        file_put_contents($path, $response->body());

        return [
            'path' => $path,
            'temporary' => true,
        ];
    }
}

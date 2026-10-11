<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

trait TransfersAudioFiles
{
    /**
     * Download a file from storage to a local path.
     */
    protected function download(string $storagePath, string $localPath): string
    {
        $stream = Storage::disk()->readStream($storagePath);

        if ($stream === null) {
            throw new RuntimeException("The file [{$storagePath}] could not be read from storage.");
        }

        try {
            File::put($localPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $localPath;
    }

    /**
     * Upload a local file to storage.
     */
    protected function upload(string $localPath, string $storagePath): void
    {
        $stream = fopen($localPath, 'r');

        try {
            if (! Storage::disk()->writeStream($storagePath, $stream)) {
                throw new RuntimeException("The file could not be written to [{$storagePath}].");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}

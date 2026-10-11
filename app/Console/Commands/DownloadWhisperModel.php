<?php

namespace App\Console\Commands;

use App\Services\Transcription\Transcriber;
use App\Services\Transcription\WhisperCppTranscriber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('whisper:download')]
#[Description('Download the configured whisper.cpp model so transcription jobs can start immediately')]
class DownloadWhisperModel extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Transcriber $transcriber): int
    {
        if (! $transcriber instanceof WhisperCppTranscriber) {
            $this->components->error('The configured transcriber does not use a whisper.cpp model.');

            return self::FAILURE;
        }

        $this->components->info('Fetching the ['.config('readalong.transcription.model').'] model. This may take a few minutes...');

        $this->components->success('Model ready at '.$transcriber->modelPath());

        return self::SUCCESS;
    }
}

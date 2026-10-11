<?php

namespace App\Services\Transcription;

interface Transcriber
{
    /**
     * Transcribe the audio file with word-level timings.
     *
     * @param  string|null  $prompt  The text the speaker is expected to read, used to guide recognition.
     */
    public function transcribe(string $audioPath, ?string $prompt = null): Transcription;
}

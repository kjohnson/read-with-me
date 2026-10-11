<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | The maximum size (in kilobytes) of a single page recording upload.
    |
    */

    'max_upload_kilobytes' => (int) env('READALONG_MAX_UPLOAD_KB', 20480),

    /*
    |--------------------------------------------------------------------------
    | FFmpeg
    |--------------------------------------------------------------------------
    |
    | FFmpeg converts, trims, and combines the recorded audio. FFprobe is used
    | to measure durations. Both must be available to the queue workers.
    |
    */

    'ffmpeg' => [
        'binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),
        'timeout' => (int) env('FFMPEG_TIMEOUT', 300),
        'audio_bitrate' => env('READALONG_AUDIO_BITRATE', '96k'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trimming
    |--------------------------------------------------------------------------
    |
    | Recordings are trimmed to the first and last spoken words, keeping a
    | little padding (in seconds) so the audio doesn't start or end abruptly.
    |
    */

    'trim' => [
        'lead_in' => (float) env('READALONG_TRIM_LEAD_IN', 0.3),
        'tail' => (float) env('READALONG_TRIM_TAIL', 0.6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transcription
    |--------------------------------------------------------------------------
    |
    | Recordings are transcribed on the queue worker with whisper.cpp (through
    | Whisper.php, which requires PHP's FFI extension) to get word timings.
    | The model is downloaded into the model directory the first time it is
    | needed; run `php artisan whisper:download` to fetch it ahead of time.
    | Set a model path to use a model file you've downloaded yourself.
    |
    */

    'transcription' => [
        'model' => env('WHISPER_MODEL', 'base.en'),
        'model_path' => env('WHISPER_MODEL_PATH'),
        'model_directory' => env('WHISPER_MODEL_DIRECTORY', storage_path('whisper')),
        'threads' => (int) env('WHISPER_THREADS', 4),
        'language' => env('WHISPER_LANGUAGE', 'en'),

        // whisper.cpp's DTW word timestamps land ~0.15s after the word starts, so they're shifted earlier by this many seconds.
        'dtw_offset' => (float) env('WHISPER_DTW_OFFSET', 0.15),

        // Prime Whisper with the page text, which improves accuracy for young readers.
        'prompt_with_page_text' => (bool) env('WHISPER_PROMPT_WITH_PAGE_TEXT', true),
    ],

];

<?php

namespace App\Jobs;

use App\Enums\PageRecordingStatus;
use App\Enums\ReadingSessionStatus;
use App\Jobs\Concerns\TransfersAudioFiles;
use App\Models\PageRecording;
use App\Services\Audio\AudioProcessor;
use App\Services\Transcription\Transcriber;
use App\Services\Transcription\TranscriptAligner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessPageRecording implements ShouldQueue
{
    use Queueable, TransfersAudioFiles;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 900;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

    /**
     * Delete the job if the recording no longer exists.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public PageRecording $pageRecording) {}

    /**
     * Transcribe the page recording, trim it to the spoken words, and time each word of the page text.
     */
    public function handle(AudioProcessor $audio, Transcriber $transcriber, TranscriptAligner $aligner): void
    {
        $recording = $this->pageRecording;
        $originalPath = $recording->original_path;
        $workingDirectory = sys_get_temp_dir().'/readalong-'.Str::uuid();

        $recording->forceFill(['status' => PageRecordingStatus::Processing, 'error' => null])->save();

        File::ensureDirectoryExists($workingDirectory);

        try {
            $sourcePath = $this->download($originalPath, $workingDirectory.'/original.'.(pathinfo($originalPath, PATHINFO_EXTENSION) ?: 'webm'));
            $wavPath = $audio->convertToWav($sourcePath, $workingDirectory.'/page.wav');
            $recordedDuration = $audio->duration($wavPath);

            $prompt = config('readalong.transcription.prompt_with_page_text') ? $recording->text : null;
            $transcription = $transcriber->transcribe($wavPath, $prompt);

            [$trimStart, $trimEnd] = $this->trimRange($transcription->timedWords(), $recordedDuration);

            $trimmedPath = $audio->trim($sourcePath, $workingDirectory.'/page.m4a', $trimStart, $trimEnd);
            $duration = $audio->duration($trimmedPath);
            $transcription = $transcription->shiftedBy($trimStart);

            $recording->refresh();

            if ($recording->original_path !== $originalPath) {
                return;
            }

            $processedPath = $recording->readingSession->storageDirectory()."/pages/{$recording->page_number}/".Str::random(12).'.m4a';
            $this->upload($trimmedPath, $processedPath);

            if ($recording->processed_path !== null) {
                Storage::disk()->delete($recording->processed_path);
            }

            $recording->forceFill([
                'status' => PageRecordingStatus::Processed,
                'processed_path' => $processedPath,
                'duration' => $duration,
                'trim_start' => $trimStart,
                'trim_end' => $trimEnd,
                'transcript' => $transcription->text,
                'transcript_words' => $transcription->words,
                'word_timings' => $aligner->align($recording->words(), $transcription->words, $duration),
                'processed_at' => now(),
            ])->save();
        } finally {
            File::deleteDirectory($workingDirectory);
        }

        $recording->readingSession->combineAudioWhenReady();
    }

    /**
     * Handle a job failure after all attempts have been exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $this->pageRecording->forceFill([
            'status' => PageRecordingStatus::Failed,
            'error' => $exception?->getMessage(),
        ])->save();

        $session = $this->pageRecording->readingSession;

        if ($session->status === ReadingSessionStatus::Processing) {
            $session->markAsFailed("Page {$this->pageRecording->page_number} could not be processed.");
        }
    }

    /**
     * Determine the range of the recording to keep, from just before the first spoken word to just after the last.
     *
     * @param  list<array{word: string, start: float, end: float}>  $timedWords
     * @return array{0: float, 1: float}
     */
    private function trimRange(array $timedWords, float $duration): array
    {
        if ($timedWords === []) {
            return [0.0, $duration];
        }

        $start = max(0.0, $timedWords[0]['start'] - config('readalong.trim.lead_in'));
        $end = min($duration, max(array_column($timedWords, 'end')) + config('readalong.trim.tail'));

        return $end > $start ? [round($start, 3), round($end, 3)] : [0.0, $duration];
    }
}

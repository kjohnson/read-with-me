<?php

namespace App\Jobs;

use App\Enums\ReadingSessionStatus;
use App\Jobs\Concerns\TransfersAudioFiles;
use App\Models\PageRecording;
use App\Models\ReadingSession;
use App\Services\Audio\AudioProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CombineReadingAudio implements ShouldBeUnique, ShouldQueue
{
    use Queueable, TransfersAudioFiles;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600;

    /**
     * Delete the job if the reading session no longer exists.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public ReadingSession $readingSession) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->readingSession->getKey();
    }

    /**
     * Combine every processed page into a single audio file for playback.
     */
    public function handle(AudioProcessor $audio): void
    {
        $session = $this->readingSession;

        if ($session->status !== ReadingSessionStatus::Processing || ! $session->allPagesProcessed()) {
            return;
        }

        $workingDirectory = sys_get_temp_dir().'/readalong-'.Str::uuid();
        File::ensureDirectoryExists($workingDirectory);

        try {
            $pagePaths = $session->pageRecordings
                ->map(fn (PageRecording $page): string => $this->download($page->processed_path, "{$workingDirectory}/page-{$page->page_number}.m4a"))
                ->all();

            $combinedPath = $audio->concatenate($pagePaths, $workingDirectory.'/reading.m4a');
            $audioPath = $session->storageDirectory().'/reading-'.Str::random(12).'.m4a';

            $this->upload($combinedPath, $audioPath);

            if ($session->audio_path !== null) {
                Storage::disk()->delete($session->audio_path);
            }

            $session->forceFill([
                'status' => ReadingSessionStatus::Ready,
                'audio_path' => $audioPath,
                'duration' => $audio->duration($combinedPath),
                'error' => null,
                'processed_at' => now(),
            ])->save();
        } finally {
            File::deleteDirectory($workingDirectory);
        }
    }

    /**
     * Handle a job failure after all attempts have been exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $this->readingSession->markAsFailed('The pages could not be combined into a single recording.');
    }
}

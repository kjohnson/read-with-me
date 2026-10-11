<?php

namespace App\Models;

use App\Enums\PageRecordingStatus;
use App\Enums\ReadingSessionStatus;
use App\Jobs\CombineReadingAudio;
use App\Support\Story;
use Database\Factories\ReadingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['story_slug', 'story_title', 'page_count', 'status'])]
class ReadingSession extends Model
{
    /** @use HasFactory<ReadingSessionFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReadingSessionStatus::class,
            'duration' => 'float',
            'finished_recording_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who recorded the session.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the recordings for each page of the story.
     *
     * @return HasMany<PageRecording, $this>
     */
    public function pageRecordings(): HasMany
    {
        return $this->hasMany(PageRecording::class)->orderBy('page_number');
    }

    /**
     * Get the configured story being read, if it still exists.
     */
    public function story(): ?Story
    {
        return Story::find($this->story_slug);
    }

    /**
     * Get the storage directory holding the session's audio files.
     */
    public function storageDirectory(): string
    {
        return "readings/{$this->getKey()}";
    }

    /**
     * Determine if pages may still be recorded for the session.
     */
    public function isRecording(): bool
    {
        return $this->status === ReadingSessionStatus::Recording;
    }

    /**
     * Determine if the combined audio is ready to be played.
     */
    public function isReady(): bool
    {
        return $this->status === ReadingSessionStatus::Ready && $this->audio_path !== null;
    }

    /**
     * Determine if every page has a successfully processed recording.
     */
    public function allPagesProcessed(): bool
    {
        return $this->pageRecordings()
            ->where('status', PageRecordingStatus::Processed)
            ->count() === $this->page_count;
    }

    /**
     * Queue the combined audio to be built once recording is finished and every page has been processed.
     */
    public function combineAudioWhenReady(): void
    {
        if ($this->status === ReadingSessionStatus::Processing && $this->allPagesProcessed()) {
            CombineReadingAudio::dispatch($this);
        }
    }

    /**
     * Mark the session as failed with the given error message.
     */
    public function markAsFailed(string $error): void
    {
        $this->forceFill([
            'status' => ReadingSessionStatus::Failed,
            'error' => $error,
        ])->save();
    }
}

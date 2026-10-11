<?php

namespace App\Models;

use App\Enums\PageRecordingStatus;
use Database\Factories\PageRecordingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['page_number', 'text', 'status', 'original_path', 'original_mime_type'])]
class PageRecording extends Model
{
    /** @use HasFactory<PageRecordingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageRecordingStatus::class,
            'duration' => 'float',
            'trim_start' => 'float',
            'trim_end' => 'float',
            'transcript_words' => 'array',
            'word_timings' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the reading session the recording belongs to.
     *
     * @return BelongsTo<ReadingSession, $this>
     */
    public function readingSession(): BelongsTo
    {
        return $this->belongsTo(ReadingSession::class);
    }

    /**
     * Get the words of the page text exactly as they are displayed to the reader.
     *
     * @return list<string>
     */
    public function words(): array
    {
        return self::splitWords($this->text);
    }

    /**
     * Split page text into display words.
     *
     * @return list<string>
     */
    public static function splitWords(string $text): array
    {
        return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}

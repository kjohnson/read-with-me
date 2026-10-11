<?php

namespace Database\Factories;

use App\Enums\PageRecordingStatus;
use App\Models\PageRecording;
use App\Models\ReadingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageRecording>
 */
class PageRecordingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reading_session_id' => ReadingSession::factory(),
            'page_number' => 1,
            'text' => 'Pip was a small mouse with a big dream.',
            'status' => PageRecordingStatus::Uploaded,
            'original_path' => 'readings/example/pages/1/original.webm',
            'original_mime_type' => 'audio/webm',
        ];
    }

    /**
     * Indicate that the recording has been processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageRecordingStatus::Processed,
            'processed_path' => "readings/example/pages/{$attributes['page_number']}/processed.m4a",
            'duration' => 4.0,
            'trim_start' => 0.5,
            'trim_end' => 4.5,
            'transcript' => $attributes['text'],
            'word_timings' => array_map(
                fn (int $index): array => [$index * 0.4, $index * 0.4 + 0.4],
                array_keys(PageRecording::splitWords($attributes['text'])),
            ),
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate that processing the recording failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageRecordingStatus::Failed,
            'error' => 'FFmpeg failed.',
        ]);
    }
}

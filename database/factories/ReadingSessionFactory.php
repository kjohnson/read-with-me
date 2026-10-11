<?php

namespace Database\Factories;

use App\Enums\ReadingSessionStatus;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingSession>
 */
class ReadingSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'story_slug' => 'pip-and-the-big-red-kite',
            'story_title' => 'Pip and the Big Red Kite',
            'page_count' => 5,
            'status' => ReadingSessionStatus::Recording,
        ];
    }

    /**
     * Indicate that recording is finished and the audio is being processed.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReadingSessionStatus::Processing,
            'finished_recording_at' => now(),
        ]);
    }

    /**
     * Indicate that the combined audio is ready to play.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReadingSessionStatus::Ready,
            'audio_path' => 'readings/example/reading.m4a',
            'duration' => 30.0,
            'finished_recording_at' => now(),
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate that processing the audio failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReadingSessionStatus::Failed,
            'error' => 'Page 1 could not be processed.',
            'finished_recording_at' => now(),
        ]);
    }
}

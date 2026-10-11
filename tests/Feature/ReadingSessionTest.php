<?php

namespace Tests\Feature;

use App\Enums\PageRecordingStatus;
use App\Enums\ReadingSessionStatus;
use App\Jobs\CombineReadingAudio;
use App\Jobs\ProcessPageRecording;
use App\Models\PageRecording;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReadingSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_story_creates_a_reading_session_and_opens_the_recorder(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('readings.store', 'the-garden-surprise'));

        $reading = ReadingSession::sole();
        $response->assertRedirect(route('readings.record', $reading));
        $this->assertTrue($reading->user->is($user));
        $this->assertSame('The Garden Surprise', $reading->story_title);
        $this->assertSame(5, $reading->page_count);
        $this->assertSame(ReadingSessionStatus::Recording, $reading->status);
    }

    public function test_starting_an_unknown_story_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('readings.store', 'missing-story'))
            ->assertNotFound();
    }

    public function test_the_recorder_resumes_at_the_first_unrecorded_page(): void
    {
        $reading = ReadingSession::factory()->create();
        PageRecording::factory()->for($reading)->create(['page_number' => 1]);
        PageRecording::factory()->for($reading)->create(['page_number' => 2]);

        $this->actingAs($reading->user)
            ->get(route('readings.record', $reading))
            ->assertOk()
            ->assertViewHas('recorderConfig', fn (array $config): bool => $config['startPage'] === 3);
    }

    public function test_users_cannot_access_another_users_reading(): void
    {
        $reading = ReadingSession::factory()->ready()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->get(route('readings.record', $reading))->assertForbidden();
        $this->actingAs($otherUser)->get(route('readings.show', $reading))->assertForbidden();
        $this->actingAs($otherUser)->get(route('readings.audio', $reading))->assertForbidden();
        $this->actingAs($otherUser)->post(route('readings.finish', $reading))->assertForbidden();
        $this->actingAs($otherUser)->delete(route('readings.destroy', $reading))->assertForbidden();
        $this->assertModelExists($reading);
    }

    public function test_the_recordings_list_shows_each_readings_status(): void
    {
        $user = User::factory()->create();
        $processing = ReadingSession::factory()->for($user)->processing()->create(['story_title' => 'Processing Story']);
        PageRecording::factory()->for($processing)->processed()->create(['page_number' => 1]);
        ReadingSession::factory()->for($user)->ready()->create(['story_title' => 'Ready Story']);
        ReadingSession::factory()->create(['story_title' => 'Someone Else Story']);

        $this->actingAs($user)
            ->get(route('readings.index'))
            ->assertOk()
            ->assertSee('Processing Story')
            ->assertSee('1 of 5 pages done')
            ->assertSee('Ready Story')
            ->assertSee('Ready to play')
            ->assertSee('http-equiv="refresh"', escape: false)
            ->assertDontSee('Someone Else Story');
    }

    public function test_finishing_requires_every_page_to_be_recorded(): void
    {
        Queue::fake();
        $reading = ReadingSession::factory()->create();
        PageRecording::factory()->for($reading)->create(['page_number' => 1]);

        $this->actingAs($reading->user)
            ->postJson(route('readings.finish', $reading))
            ->assertUnprocessable()
            ->assertJsonPath('missing_pages', [2, 3, 4, 5]);

        $this->assertSame(ReadingSessionStatus::Recording, $reading->fresh()->status);
    }

    public function test_finishing_starts_processing_and_waits_for_pages_still_being_processed(): void
    {
        Queue::fake();
        $reading = ReadingSession::factory()->create();
        PageRecording::factory()->for($reading)->processed()->create(['page_number' => 1]);

        foreach (range(2, 5) as $page) {
            PageRecording::factory()->for($reading)->create(['page_number' => $page]);
        }

        $this->actingAs($reading->user)
            ->postJson(route('readings.finish', $reading))
            ->assertOk()
            ->assertJsonPath('redirect', route('readings.index'));

        $this->assertSame(ReadingSessionStatus::Processing, $reading->fresh()->status);
        Queue::assertNotPushed(CombineReadingAudio::class);
    }

    public function test_finishing_combines_the_audio_when_every_page_is_already_processed(): void
    {
        Queue::fake();
        $reading = ReadingSession::factory()->create();

        foreach (range(1, 5) as $page) {
            PageRecording::factory()->for($reading)->processed()->create(['page_number' => $page]);
        }

        $this->actingAs($reading->user)->postJson(route('readings.finish', $reading))->assertOk();

        Queue::assertPushed(CombineReadingAudio::class, fn (CombineReadingAudio $job): bool => $job->readingSession->is($reading));
    }

    public function test_a_ready_reading_shows_the_player_with_page_offsets(): void
    {
        $reading = ReadingSession::factory()->ready()->create();

        foreach (range(1, 5) as $page) {
            PageRecording::factory()->for($reading)->processed()->create(['page_number' => $page, 'duration' => 4.0]);
        }

        $this->actingAs($reading->user)
            ->get(route('readings.show', $reading))
            ->assertOk()
            ->assertViewHas('playerConfig', fn (array $config): bool => $config['pages']->pluck('offset')->all() === [0.0, 4.0, 8.0, 12.0, 16.0]
                && $config['audioUrl'] === route('readings.audio', [$reading, 'v' => 'reading']));
    }

    public function test_a_reading_that_is_still_processing_cannot_be_played(): void
    {
        $reading = ReadingSession::factory()->processing()->create();

        $this->actingAs($reading->user)->get(route('readings.show', $reading))->assertRedirect(route('readings.index'));
        $this->actingAs($reading->user)->get(route('readings.audio', $reading))->assertNotFound();
    }

    public function test_the_combined_audio_is_served_from_local_storage(): void
    {
        Storage::fake();
        $reading = ReadingSession::factory()->ready()->create();
        Storage::put($reading->audio_path, 'audio-bytes');

        $this->actingAs($reading->user)
            ->get(route('readings.audio', $reading))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mp4')
            ->assertHeader('Accept-Ranges', 'bytes');
    }

    public function test_retrying_a_failed_reading_reprocesses_only_the_failed_pages(): void
    {
        Queue::fake();
        $reading = ReadingSession::factory()->failed()->create();
        PageRecording::factory()->for($reading)->processed()->create(['page_number' => 1]);
        $failedPage = PageRecording::factory()->for($reading)->failed()->create(['page_number' => 2]);

        $this->actingAs($reading->user)->post(route('readings.retry', $reading))->assertRedirect();

        $this->assertSame(ReadingSessionStatus::Processing, $reading->fresh()->status);
        $this->assertSame(PageRecordingStatus::Uploaded, $failedPage->fresh()->status);
        Queue::assertPushed(ProcessPageRecording::class, 1);
        Queue::assertPushed(ProcessPageRecording::class, fn (ProcessPageRecording $job): bool => $job->pageRecording->is($failedPage));
    }

    public function test_deleting_a_reading_removes_its_audio(): void
    {
        Storage::fake();
        $reading = ReadingSession::factory()->ready()->create();
        $page = PageRecording::factory()->for($reading)->create(['original_path' => $reading->storageDirectory().'/pages/1/original.webm']);
        Storage::put($page->original_path, 'audio-bytes');

        $this->actingAs($reading->user)->delete(route('readings.destroy', $reading))->assertRedirect(route('readings.index'));

        $this->assertModelMissing($reading);
        $this->assertModelMissing($page);
        Storage::assertMissing($page->original_path);
    }
}

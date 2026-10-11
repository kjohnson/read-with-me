<?php

namespace Tests\Feature;

use App\Enums\PageRecordingStatus;
use App\Jobs\ProcessPageRecording;
use App\Models\PageRecording;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageRecordingUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Queue::fake();
    }

    public function test_uploading_a_page_stores_the_audio_and_queues_it_for_processing(): void
    {
        $reading = ReadingSession::factory()->create();

        $this->actingAs($reading->user)
            ->post(route('readings.pages.store', [$reading, 2]), ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJson(['page' => 2, 'status' => 'uploaded']);

        $page = PageRecording::sole();
        $this->assertSame(2, $page->page_number);
        $this->assertSame('One windy morning, Pip found a big red kite stuck in a bush. "This is my chance!" said Pip.', $page->text);
        $this->assertStringStartsWith($reading->storageDirectory().'/pages/2/', $page->original_path);
        Storage::assertExists($page->original_path);
        Queue::assertPushed(ProcessPageRecording::class, fn (ProcessPageRecording $job): bool => $job->pageRecording->is($page));
    }

    public function test_re_recording_a_page_replaces_the_earlier_take(): void
    {
        $reading = ReadingSession::factory()->create();
        $earlierTake = PageRecording::factory()->for($reading)->processed()->create(['page_number' => 1]);
        Storage::put($earlierTake->original_path, 'old');
        Storage::put($earlierTake->processed_path, 'old');

        $this->actingAs($reading->user)
            ->post(route('readings.pages.store', [$reading, 1]), ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertCreated();

        $page = PageRecording::sole();
        $this->assertSame(PageRecordingStatus::Uploaded, $page->status);
        $this->assertNull($page->processed_path);
        $this->assertNull($page->word_timings);
        $this->assertNotSame($earlierTake->original_path, $page->original_path);
        Storage::assertMissing($earlierTake->original_path);
        Storage::assertMissing($earlierTake->processed_path);
    }

    public function test_pages_outside_the_story_are_rejected(): void
    {
        $reading = ReadingSession::factory()->create();

        $this->actingAs($reading->user)
            ->post(route('readings.pages.store', [$reading, 6]), ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    public function test_pages_cannot_be_uploaded_after_recording_is_finished(): void
    {
        $reading = ReadingSession::factory()->processing()->create();

        $this->actingAs($reading->user)
            ->post(route('readings.pages.store', [$reading, 1]), ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_only_audio_files_are_accepted(): void
    {
        $reading = ReadingSession::factory()->create();

        $this->actingAs($reading->user)
            ->post(route('readings.pages.store', [$reading, 1]), ['audio' => UploadedFile::fake()->create('page.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');
    }

    public function test_users_cannot_upload_to_another_users_reading(): void
    {
        $reading = ReadingSession::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('readings.pages.store', [$reading, 1]), ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertSame(0, PageRecording::count());
    }

    /**
     * Create an uploaded WebM file like the ones browsers record.
     */
    private function recording(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('page.webm', "\x1A\x45\xDF\xA3".str_repeat("\0", 64));
    }
}

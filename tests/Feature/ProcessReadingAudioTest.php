<?php

namespace Tests\Feature;

use App\Enums\PageRecordingStatus;
use App\Enums\ReadingSessionStatus;
use App\Jobs\CombineReadingAudio;
use App\Jobs\ProcessPageRecording;
use App\Models\PageRecording;
use App\Models\ReadingSession;
use App\Services\Audio\AudioProcessor;
use App\Services\Transcription\Transcriber;
use App\Services\Transcription\Transcription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcessReadingAudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (Process::run(['ffmpeg', '-version'])->failed()) {
            $this->markTestSkipped('FFmpeg is required to process audio.');
        }

        Storage::fake();
    }

    public function test_a_page_is_trimmed_to_the_spoken_words_and_each_word_is_timed(): void
    {
        Queue::fake([CombineReadingAudio::class]);
        $page = $this->uploadedPage(seconds: 6, text: 'Pip wanted to fly.');
        $this->fakeTranscription(new Transcription('Pip wanted to fly.', [
            ['word' => 'Pip', 'start' => 1.5, 'end' => 1.8],
            ['word' => 'wanted', 'start' => 1.8, 'end' => 2.2],
            ['word' => 'fly.', 'start' => 2.6, 'end' => 3.0],
        ]));

        ProcessPageRecording::dispatchSync($page);

        $page->refresh();
        $this->assertSame(PageRecordingStatus::Processed, $page->status);
        $this->assertSame(1.2, $page->trim_start);
        $this->assertSame(3.6, $page->trim_end);
        $this->assertEqualsWithDelta(2.4, $page->duration, 0.1);
        Storage::assertExists($page->processed_path);
        $this->assertEqualsWithDelta([[0.3, 0.6], [0.6, 1.0], [1.0, 1.4], [1.4, 1.8]], $page->word_timings, 0.001);
        $this->assertSame('Pip wanted to fly.', $page->transcript);
    }

    public function test_trimming_keeps_all_of_the_audio_up_to_the_end_of_the_range(): void
    {
        Queue::fake([CombineReadingAudio::class]);
        $page = $this->uploadedPage(seconds: 6, text: 'Pip wanted to fly.');
        $this->fakeTranscription(new Transcription('Pip wanted to fly.', [
            ['word' => 'Pip', 'start' => 1.5, 'end' => 1.8],
            ['word' => 'fly.', 'start' => 2.6, 'end' => 3.0],
        ]));

        ProcessPageRecording::dispatchSync($page);

        $processedPath = tempnam(sys_get_temp_dir(), 'trimmed').'.m4a';
        file_put_contents($processedPath, Storage::get($page->fresh()->processed_path));
        $silenceDetection = Process::run(['ffmpeg', '-hide_banner', '-i', $processedPath, '-af', 'silencedetect=noise=-40dB:d=0.1', '-f', 'null', '-']);
        unlink($processedPath);

        $this->assertStringNotContainsString('silence_start', $silenceDetection->errorOutput());
    }

    public function test_the_combined_audio_is_built_once_the_last_page_is_processed(): void
    {
        $reading = ReadingSession::factory()->processing()->create(['page_count' => 2]);
        $firstPage = $this->uploadedPage(seconds: 3, text: 'Hello there.', reading: $reading, pageNumber: 1);
        $secondPage = $this->uploadedPage(seconds: 3, text: 'Goodbye now.', reading: $reading, pageNumber: 2);
        $this->fakeTranscription(new Transcription('', []));

        ProcessPageRecording::dispatchSync($firstPage);
        $this->assertSame(ReadingSessionStatus::Processing, $reading->fresh()->status);

        ProcessPageRecording::dispatchSync($secondPage);

        $reading->refresh();
        $this->assertSame(ReadingSessionStatus::Ready, $reading->status);
        Storage::assertExists($reading->audio_path);
        $this->assertEqualsWithDelta($firstPage->fresh()->duration + $secondPage->fresh()->duration, $reading->duration, 0.1);
    }

    public function test_the_combined_audio_is_not_built_while_the_reader_is_still_recording(): void
    {
        Queue::fake([CombineReadingAudio::class]);
        $reading = ReadingSession::factory()->create(['page_count' => 1]);
        $page = $this->uploadedPage(seconds: 2, text: 'Hello.', reading: $reading);
        $this->fakeTranscription(new Transcription('', []));

        ProcessPageRecording::dispatchSync($page);

        $this->assertSame(PageRecordingStatus::Processed, $page->fresh()->status);
        Queue::assertNotPushed(CombineReadingAudio::class);
    }

    public function test_a_page_that_cannot_be_processed_fails_the_reading(): void
    {
        $reading = ReadingSession::factory()->processing()->create();
        $page = PageRecording::factory()->for($reading)->create(['page_number' => 3]);
        Storage::put($page->original_path, 'not audio');

        (new ProcessPageRecording($page))->failed(new RuntimeException('FFmpeg failed: invalid data'));

        $this->assertSame(PageRecordingStatus::Failed, $page->fresh()->status);
        $this->assertSame('FFmpeg failed: invalid data', $page->fresh()->error);
        $this->assertSame(ReadingSessionStatus::Failed, $reading->fresh()->status);
        $this->assertSame('Page 3 could not be processed.', $reading->fresh()->error);
    }

    public function test_a_recording_replaced_during_processing_keeps_the_newer_take(): void
    {
        Queue::fake([CombineReadingAudio::class]);
        $page = $this->uploadedPage(seconds: 2, text: 'Hello.');
        $this->fakeTranscription(new Transcription('', []), function () use ($page): void {
            PageRecording::whereKey($page->getKey())->update(['original_path' => 'readings/newer-take.webm']);
        });

        ProcessPageRecording::dispatchSync($page);

        $page->refresh();
        $this->assertSame('readings/newer-take.webm', $page->original_path);
        $this->assertNull($page->processed_path);
        $this->assertNotSame(PageRecordingStatus::Processed, $page->status);
    }

    /**
     * Store a recording of a tone with the given length for a page.
     */
    private function uploadedPage(int $seconds, string $text, ?ReadingSession $reading = null, int $pageNumber = 1): PageRecording
    {
        $reading ??= ReadingSession::factory()->create();
        $localPath = tempnam(sys_get_temp_dir(), 'tone').'.webm';

        Process::run(['ffmpeg', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i', "sine=frequency=440:duration={$seconds}", '-c:a', 'libopus', $localPath])->throw();

        $page = PageRecording::factory()->for($reading)->create([
            'page_number' => $pageNumber,
            'text' => $text,
            'original_path' => $reading->storageDirectory()."/pages/{$pageNumber}/original.webm",
        ]);

        Storage::put($page->original_path, file_get_contents($localPath));
        unlink($localPath);

        return $page;
    }

    /**
     * Replace whisper.cpp with a transcriber that returns the given transcription.
     */
    private function fakeTranscription(Transcription $transcription, ?callable $whileTranscribing = null): void
    {
        $this->app->instance(Transcriber::class, new class($transcription, $whileTranscribing) implements Transcriber
        {
            public function __construct(private Transcription $transcription, private $whileTranscribing) {}

            public function transcribe(string $audioPath, ?string $prompt = null): Transcription
            {
                if ($this->whileTranscribing !== null) {
                    ($this->whileTranscribing)();
                }

                return $this->transcription;
            }
        });

        $this->app->forgetInstance(AudioProcessor::class);
    }
}

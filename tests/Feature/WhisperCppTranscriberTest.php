<?php

namespace Tests\Feature;

use App\Services\Transcription\Transcriber;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('whisper')]
class WhisperCppTranscriberTest extends TestCase
{
    public function test_speech_is_transcribed_into_timed_words(): void
    {
        $model = config('readalong.transcription.model_directory').'/ggml-'.config('readalong.transcription.model').'.bin';

        if (! extension_loaded('ffi') || ! File::exists($model)) {
            $this->markTestSkipped('Requires the FFI extension and a downloaded model (php artisan whisper:download).');
        }

        $transcription = $this->app->make(Transcriber::class)
            ->transcribe(base_path('vendor/codewithkyrian/whisper.php/examples/sounds/jfk.wav'));

        $words = collect($transcription->words);

        $this->assertStringContainsString('ask not what your country can do for you', strtolower($transcription->text));
        $this->assertSame('Americans,', $words->firstWhere('word', 'Americans,')['word'] ?? null);
        $this->assertSame($words->pluck('start')->sort()->values()->all(), $words->pluck('start')->all());
        $this->assertTrue($words->every(fn (array $word): bool => $word['end'] >= $word['start'] && $word['end'] <= 11.0));
    }
}

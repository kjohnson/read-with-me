<?php

namespace App\Providers;

use App\Services\Transcription\Transcriber;
use App\Services\Transcription\WhisperCppTranscriber;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Transcriber::class, fn (): Transcriber => new WhisperCppTranscriber(config('readalong.transcription')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

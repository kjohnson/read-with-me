<?php

namespace App\Http\Controllers;

use App\Models\ReadingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReadingAudioController extends Controller
{
    /**
     * Serve the combined audio for a reading.
     *
     * Local files are served directly (with support for range requests, so the
     * player can seek). Files in a storage bucket are served by redirecting to
     * a short-lived signed URL. The player's URL includes the audio file's name,
     * so rebuilt audio is never served from the browser's cache.
     */
    public function __invoke(ReadingSession $reading): BinaryFileResponse|RedirectResponse
    {
        abort_unless($reading->isReady(), 404);

        $disk = config('filesystems.default');

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return response()->file(Storage::disk()->path($reading->audio_path), [
                'Content-Type' => 'audio/mp4',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        return redirect()->away(Storage::disk()->temporaryUrl($reading->audio_path, now()->addHours(3)));
    }
}

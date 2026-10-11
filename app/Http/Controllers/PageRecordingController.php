<?php

namespace App\Http\Controllers;

use App\Enums\PageRecordingStatus;
use App\Jobs\ProcessPageRecording;
use App\Models\ReadingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PageRecordingController extends Controller
{
    /**
     * The audio types browsers' MediaRecorder may produce.
     *
     * @var list<string>
     */
    private const array ALLOWED_MIME_TYPES = [
        'audio/webm', 'video/webm', 'audio/ogg', 'video/ogg', 'audio/mp4', 'video/mp4',
        'audio/x-m4a', 'audio/aac', 'audio/mpeg', 'audio/wav', 'audio/x-wav',
    ];

    /**
     * Store the recording for a page of the story, replacing any earlier take.
     */
    public function store(Request $request, ReadingSession $reading, int $page): JsonResponse
    {
        abort_unless($reading->isRecording(), 409, 'This reading has already been finished.');
        abort_unless($page >= 1 && $page <= $reading->page_count, 404);

        $text = $reading->story()?->pageText($page) ?? abort(404);

        $request->validate([
            'audio' => ['required', 'file', 'max:'.config('readalong.max_upload_kilobytes'), 'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES)],
        ]);

        $file = $request->file('audio');
        $extension = $file->extension() ?: 'webm';
        $path = $file->storeAs(
            $reading->storageDirectory()."/pages/{$page}",
            'original-'.Str::random(12).'.'.$extension,
        );

        $recording = $reading->pageRecordings()->firstOrNew(['page_number' => $page]);

        if ($recording->exists) {
            Storage::disk()->delete(array_filter([$recording->original_path, $recording->processed_path]));
        }

        $recording->forceFill([
            'text' => $text,
            'status' => PageRecordingStatus::Uploaded,
            'original_path' => $path,
            'original_mime_type' => $file->getMimeType(),
            'processed_path' => null,
            'duration' => null,
            'trim_start' => null,
            'trim_end' => null,
            'transcript' => null,
            'transcript_words' => null,
            'word_timings' => null,
            'error' => null,
            'processed_at' => null,
        ])->save();

        ProcessPageRecording::dispatch($recording);

        return response()->json([
            'page' => $page,
            'status' => $recording->status,
        ], 201);
    }
}

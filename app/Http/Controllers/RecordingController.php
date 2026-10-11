<?php

namespace App\Http\Controllers;

use App\Enums\ReadingSessionStatus;
use App\Models\ReadingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RecordingController extends Controller
{
    /**
     * Show the recorder for a reading session.
     */
    public function show(ReadingSession $reading): View|RedirectResponse
    {
        if (! $reading->isRecording()) {
            return redirect()->route('readings.index');
        }

        $story = $reading->story() ?? abort(404);
        $recordedPages = $reading->pageRecordings()->pluck('page_number')->all();
        $pageNumbers = range(1, $story->pageCount());

        return view('readings.record', [
            'reading' => $reading,
            'story' => $story,
            'recorderConfig' => [
                'pages' => $story->pages,
                'startPage' => collect($pageNumbers)->diff($recordedPages)->first() ?? $story->pageCount(),
                'recordedPages' => $recordedPages,
                'uploadUrl' => route('readings.pages.store', [$reading, '__PAGE__']),
                'finishUrl' => route('readings.finish', $reading),
            ],
        ]);
    }

    /**
     * Finish recording and start building the combined audio.
     */
    public function finish(ReadingSession $reading): JsonResponse
    {
        if (! $reading->isRecording()) {
            return response()->json(['redirect' => route('readings.index')]);
        }

        $missingPages = collect(range(1, $reading->page_count))
            ->diff($reading->pageRecordings()->pluck('page_number'))
            ->values();

        if ($missingPages->isNotEmpty()) {
            return response()->json([
                'message' => 'Some pages have not been recorded yet.',
                'missing_pages' => $missingPages,
            ], 422);
        }

        $reading->forceFill([
            'status' => ReadingSessionStatus::Processing,
            'finished_recording_at' => now(),
        ])->save();

        $reading->combineAudioWhenReady();

        return response()->json(['redirect' => route('readings.index')]);
    }
}

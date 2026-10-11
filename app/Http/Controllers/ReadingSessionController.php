<?php

namespace App\Http\Controllers;

use App\Enums\PageRecordingStatus;
use App\Enums\ReadingSessionStatus;
use App\Jobs\ProcessPageRecording;
use App\Models\PageRecording;
use App\Models\ReadingSession;
use App\Support\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ReadingSessionController extends Controller
{
    /**
     * Show the user's recorded stories and their processing status.
     */
    public function index(Request $request): View
    {
        $readings = $request->user()
            ->readingSessions()
            ->withCount([
                'pageRecordings as recorded_pages_count',
                'pageRecordings as processed_pages_count' => fn ($query) => $query->where('status', PageRecordingStatus::Processed),
            ])
            ->latest()
            ->paginate(20);

        return view('readings.index', [
            'readings' => $readings,
            'isProcessing' => $readings->contains(fn (ReadingSession $reading): bool => $reading->status === ReadingSessionStatus::Processing),
        ]);
    }

    /**
     * Start a new reading session for a story.
     */
    public function store(Request $request, string $story): RedirectResponse
    {
        $story = Story::findOrFail($story);

        $reading = $request->user()->readingSessions()->create([
            'story_slug' => $story->slug,
            'story_title' => $story->title,
            'page_count' => $story->pageCount(),
            'status' => ReadingSessionStatus::Recording,
        ]);

        return redirect()->route('readings.record', $reading);
    }

    /**
     * Play the recorded story with follow-along text.
     */
    public function show(ReadingSession $reading): View|RedirectResponse
    {
        if (! $reading->isReady()) {
            return redirect()->route('readings.index')->with('status', 'That recording is not ready to play yet.');
        }

        $offset = 0.0;

        $pages = $reading->pageRecordings->map(function (PageRecording $page) use (&$offset): array {
            $data = [
                'number' => $page->page_number,
                'words' => $page->words(),
                'timings' => $page->word_timings ?? [],
                'offset' => round($offset, 3),
                'duration' => $page->duration,
            ];

            $offset += $page->duration;

            return $data;
        });

        return view('readings.show', [
            'reading' => $reading,
            'story' => $reading->story(),
            'playerConfig' => [
                'audioUrl' => route('readings.audio', [$reading, 'v' => pathinfo($reading->audio_path, PATHINFO_FILENAME)]),
                'duration' => $reading->duration,
                'pages' => $pages,
            ],
        ]);
    }

    /**
     * Retry processing a reading whose audio processing failed.
     */
    public function retry(ReadingSession $reading): RedirectResponse
    {
        if ($reading->status !== ReadingSessionStatus::Failed) {
            return back();
        }

        $reading->forceFill(['status' => ReadingSessionStatus::Processing, 'error' => null])->save();

        $failedPages = $reading->pageRecordings()->where('status', PageRecordingStatus::Failed)->get();

        foreach ($failedPages as $page) {
            $page->forceFill(['status' => PageRecordingStatus::Uploaded, 'error' => null])->save();

            ProcessPageRecording::dispatch($page);
        }

        if ($failedPages->isEmpty()) {
            $reading->combineAudioWhenReady();
        }

        return back()->with('status', "We're trying \"{$reading->story_title}\" again.");
    }

    /**
     * Delete a reading session and all of its audio.
     */
    public function destroy(ReadingSession $reading): RedirectResponse
    {
        Storage::disk()->deleteDirectory($reading->storageDirectory());

        $reading->delete();

        return redirect()->route('readings.index')->with('status', "\"{$reading->story_title}\" was deleted.");
    }
}

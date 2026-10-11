<x-layout title="My Recordings">
    @if ($isProcessing)
        <x-slot:head>
            <meta http-equiv="refresh" content="10">
        </x-slot:head>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold sm:text-4xl">My recordings</h1>
            <p class="mt-2 text-lg text-stone-600">Every story you've read, ready to listen to once it's processed.</p>
        </div>
        <a href="{{ route('stories.index') }}" class="btn btn-secondary">Read another story</a>
    </div>

    @if ($isProcessing)
        <p class="mt-6 rounded-2xl bg-sky-50 px-4 py-3 text-sky-900">
            ⏳ We're getting your recordings ready. This page will refresh by itself.
        </p>
    @endif

    <div class="mt-8 space-y-4">
        @forelse ($readings as $reading)
            <article class="card flex flex-col gap-4 sm:flex-row sm:items-center">
                <x-story-cover :story="$reading->story()" size="sm" />

                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-lg font-extrabold">{{ $reading->story_title }}</h2>
                    <p class="text-sm text-stone-500">Recorded {{ $reading->created_at->diffForHumans() }}</p>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm font-bold">
                        @switch($reading->status)
                            @case(\App\Enums\ReadingSessionStatus::Ready)
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">✅ {{ $reading->status->label() }}</span>
                                <span class="text-stone-500">{{ gmdate('i:s', (int) round($reading->duration)) }}</span>
                                @break
                            @case(\App\Enums\ReadingSessionStatus::Processing)
                                <span class="rounded-full bg-sky-100 px-3 py-1 text-sky-800">
                                    <span class="inline-block animate-spin" aria-hidden="true">⏳</span> {{ $reading->status->label() }}
                                </span>
                                <span class="text-stone-500">{{ $reading->processed_pages_count }} of {{ $reading->page_count }} pages done</span>
                                @break
                            @case(\App\Enums\ReadingSessionStatus::Failed)
                                <span class="rounded-full bg-rose-100 px-3 py-1 text-rose-800">⚠️ {{ $reading->status->label() }}</span>
                                <span class="text-stone-500">{{ $reading->error }}</span>
                                @break
                            @default
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800">✏️ {{ $reading->status->label() }}</span>
                                <span class="text-stone-500">{{ $reading->recorded_pages_count }} of {{ $reading->page_count }} pages recorded</span>
                        @endswitch
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($reading->isReady())
                        <a href="{{ route('readings.show', $reading) }}" class="btn btn-primary">▶ Play</a>
                    @elseif ($reading->isRecording() && $reading->story())
                        <a href="{{ route('readings.record', $reading) }}" class="btn btn-primary">Keep reading</a>
                    @elseif ($reading->status === \App\Enums\ReadingSessionStatus::Failed)
                        <form method="POST" action="{{ route('readings.retry', $reading) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Try again</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('readings.destroy', $reading) }}" onsubmit="return confirm('Delete this recording? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger px-3" title="Delete recording" aria-label="Delete {{ $reading->story_title }} recording">🗑️</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card text-center">
                <p class="text-5xl" aria-hidden="true">🎙️</p>
                <p class="mt-4 text-lg font-bold">You haven't recorded any stories yet.</p>
                <a href="{{ route('stories.index') }}" class="btn btn-primary mt-4">Pick a story</a>
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $readings->links() }}</div>
</x-layout>

<x-layout :title="$reading->story_title">
    <div data-player data-config="{{ json_encode($playerConfig) }}">
        <div class="flex items-center gap-4">
            <x-story-cover :story="$story" size="sm" />
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-extrabold sm:text-3xl">{{ $reading->story_title }}</h1>
                <p class="font-bold text-stone-500">
                    Page <span data-page-number>1</span> of {{ $reading->page_count }}
                    · recorded {{ $reading->created_at->toFormattedDayDateString() }}
                </p>
            </div>
        </div>

        <div class="card mt-6 min-h-56 sm:p-10">
            <p data-page-text class="text-2xl leading-relaxed font-semibold text-stone-900 sm:text-3xl sm:leading-relaxed"></p>

            <div data-the-end class="hidden py-6 text-center">
                <p class="text-6xl" aria-hidden="true">🎉</p>
                <p class="mt-3 text-3xl font-extrabold">The End</p>
                <p class="mt-1 text-stone-600">What a wonderful reader!</p>
            </div>
        </div>

        <div class="mt-6">
            <div class="relative">
                <input data-seek type="range" min="0" max="{{ $reading->duration }}" step="0.01" value="0" aria-label="Seek" class="w-full accent-violet-600">
            </div>
            <div class="mt-1 flex justify-between text-sm font-bold text-stone-500 tabular-nums">
                <span data-current-time>0:00</span>
                <span data-total-time>0:00</span>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-center gap-3">
            <button type="button" data-action="previous" class="btn btn-secondary size-14 p-0 text-xl" aria-label="Previous page">⏮</button>
            <button type="button" data-action="play" class="btn btn-primary size-20 p-0 text-3xl" aria-label="Play">▶</button>
            <button type="button" data-action="next" class="btn btn-secondary size-14 p-0 text-xl" aria-label="Next page">⏭</button>
        </div>

        <ol class="mt-6 flex flex-wrap justify-center gap-2" aria-label="Jump to page">
            @for ($page = 1; $page <= $reading->page_count; $page++)
                <li>
                    <button type="button" data-page-button="{{ $page }}" class="flex size-9 items-center justify-center rounded-full border-2 border-stone-200 bg-white text-sm font-extrabold text-stone-500 transition hover:border-violet-300" aria-label="Go to page {{ $page }}">
                        {{ $page }}
                    </button>
                </li>
            @endfor
        </ol>

        <p class="mt-8 text-center text-sm text-stone-500">Tip: tap any word to hear it from there.</p>
    </div>
</x-layout>

<x-layout :title="'Reading '.$story->title">
    <div
        data-recorder
        data-config="{{ json_encode($recorderConfig) }}"
    >
        <div class="flex items-center gap-4">
            <x-story-cover :story="$story" size="sm" />
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-extrabold sm:text-3xl">{{ $story->title }}</h1>
                <p class="font-bold text-stone-500">Page <span data-page-number>1</span> of {{ $story->pageCount() }}</p>
            </div>
        </div>

        <ol class="mt-5 flex flex-wrap gap-2" aria-label="Pages" data-page-dots>
            @foreach ($story->pages as $index => $text)
                <li data-page-dot="{{ $index + 1 }}" class="flex size-9 items-center justify-center rounded-full border-2 border-stone-200 bg-white text-sm font-extrabold text-stone-400 transition">
                    {{ $index + 1 }}
                </li>
            @endforeach
        </ol>

        {{-- Before the microphone is turned on --}}
        <section data-intro class="card mt-6 text-center">
            <p class="text-6xl" aria-hidden="true">🎙️</p>
            <h2 class="mt-4 text-2xl font-extrabold">Ready to read?</h2>
            <p class="mx-auto mt-2 max-w-md text-stone-600">
                When you press start, we'll ask to use your microphone. Read the page out loud,
                then press <strong>Next page</strong> to keep going.
            </p>
            <button type="button" data-action="start" class="btn btn-primary mt-6 px-8 py-4 text-xl">Start reading</button>
            <p data-intro-error class="mt-4 hidden font-semibold text-rose-600" role="alert"></p>
        </section>

        {{-- While recording --}}
        <section data-stage class="mt-6 hidden">
            <div class="card relative">
                <div class="mb-4 flex items-center justify-between gap-3 text-sm font-bold">
                    <span data-recording-indicator class="flex items-center gap-2 text-rose-600">
                        <span class="relative flex size-3">
                            <span data-pulse class="absolute inline-flex size-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex size-3 rounded-full bg-rose-500"></span>
                        </span>
                        <span data-recording-label>Recording</span>
                        <span data-timer class="tabular-nums text-stone-500">0:00</span>
                    </span>
                    <span class="flex h-2.5 w-28 overflow-hidden rounded-full bg-stone-100" title="Microphone level">
                        <span data-level class="h-full w-0 rounded-full bg-emerald-400 transition-[width] duration-75"></span>
                    </span>
                </div>

                <p data-page-text class="text-2xl leading-relaxed font-semibold text-stone-900 sm:text-3xl sm:leading-relaxed"></p>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-action="back" class="btn btn-secondary">← Back</button>
                    <button type="button" data-action="redo" class="btn btn-secondary" title="Start this page over">↺ Redo page</button>
                    <button type="button" data-action="pause" class="btn btn-secondary">⏸ Pause</button>
                </div>
                <button type="button" data-action="next" class="btn btn-primary px-8 py-4 text-xl">Next page →</button>
                <button type="button" data-action="finish" class="btn hidden bg-emerald-600 px-8 py-4 text-xl text-white hover:bg-emerald-700 focus-visible:ring-emerald-300">I'm done! ✓</button>
            </div>

            <p data-upload-status class="mt-4 min-h-6 text-sm font-semibold text-stone-500" aria-live="polite"></p>
            <p data-error class="mt-2 hidden rounded-2xl bg-rose-50 px-4 py-3 font-semibold text-rose-700" role="alert"></p>
        </section>
    </div>
</x-layout>

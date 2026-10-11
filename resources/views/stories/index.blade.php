<x-layout title="Stories" :wide="true">
    <h1 class="text-3xl font-extrabold sm:text-4xl">Pick a story to read</h1>
    <p class="mt-2 text-lg text-stone-600">You'll read one page at a time. We'll record each page as you go.</p>

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($stories as $story)
            <article class="card flex flex-col">
                <x-story-cover :story="$story" />
                <h2 class="mt-4 text-xl font-extrabold">{{ $story->title }}</h2>
                <p class="mt-1 flex-1 text-stone-600">{{ $story->description }}</p>
                <p class="mt-3 text-sm font-bold text-stone-500">{{ trans_choice(':count page|:count pages', $story->pageCount()) }}</p>
                <form method="POST" action="{{ route('readings.store', $story->slug) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">🎙️ Read this story</button>
                </form>
            </article>
        @endforeach
    </div>
</x-layout>

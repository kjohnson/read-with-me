<x-layout :wide="true">
    <section class="py-6 text-center sm:py-12">
        <p class="text-6xl" aria-hidden="true">📖🎙️✨</p>
        <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-stone-900 sm:text-6xl">Read a story. Hear it back.</h1>
        <p class="mx-auto mt-4 max-w-xl text-lg text-stone-600">
            Pick a story, read each page out loud, and we'll turn your reading into a follow-along audiobook
            with every word lighting up as you hear it.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('register') }}" class="btn btn-primary px-7 py-3.5 text-lg">Start reading — it's free</a>
            <a href="{{ route('login') }}" class="btn btn-secondary px-7 py-3.5 text-lg">I already have an account</a>
        </div>
    </section>

    <section class="mt-12 grid gap-6 sm:grid-cols-3">
        @foreach ($stories as $story)
            <div class="card">
                <x-story-cover :story="$story" />
                <h2 class="mt-4 text-lg font-extrabold">{{ $story->title }}</h2>
                <p class="mt-1 text-stone-600">{{ $story->description }}</p>
            </div>
        @endforeach
    </section>
</x-layout>

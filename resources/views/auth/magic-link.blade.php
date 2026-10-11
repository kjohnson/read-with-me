<x-layout title="Sign in">
    <div class="card mx-auto max-w-md text-center">
        @if ($isUsable)
            <p class="text-5xl" aria-hidden="true">✨</p>
            <h1 class="mt-4 text-3xl font-extrabold">Signing you in…</h1>

            <form method="POST" action="{{ route('magic-link.consume', $token) }}" data-auto-submit class="mt-6">
                @csrf
                <button type="submit" class="btn btn-primary w-full py-3 text-lg">Continue to {{ config('app.name') }}</button>
            </form>
        @else
            <p class="text-5xl" aria-hidden="true">⌛</p>
            <h1 class="mt-4 text-3xl font-extrabold">This link has expired</h1>
            <p class="mt-2 text-stone-600">Sign-in links only work once and expire after {{ \App\Models\MagicLink::LIFETIME_MINUTES }} minutes.</p>
            <a href="{{ route('login') }}" class="btn btn-primary mt-6 w-full py-3 text-lg">Send me a new link</a>
        @endif
    </div>
</x-layout>

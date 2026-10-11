@props(['title' => null, 'wide' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{ $head ?? '' }}

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-amber-50 font-sans text-stone-800 antialiased">
        <header class="border-b border-amber-100 bg-white/70 backdrop-blur">
            <nav class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-extrabold text-violet-700">
                    <span aria-hidden="true">📚</span> {{ config('app.name') }}
                </a>

                <div class="flex items-center gap-1 text-sm font-bold sm:gap-2">
                    @auth
                        <a href="{{ route('stories.index') }}" @class(['rounded-full px-3 py-2 hover:bg-violet-50', 'bg-violet-100 text-violet-800' => request()->routeIs('stories.*')])>Stories</a>
                        <a href="{{ route('readings.index') }}" @class(['rounded-full px-3 py-2 hover:bg-violet-50', 'bg-violet-100 text-violet-800' => request()->routeIs('readings.*')])>My Recordings</a>
                        <a href="{{ route('account.edit') }}" @class(['rounded-full px-3 py-2 hover:bg-violet-50', 'bg-violet-100 text-violet-800' => request()->routeIs('account.*')])>Account</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-full px-3 py-2 text-stone-500 hover:bg-stone-100">Sign out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full px-3 py-2 hover:bg-violet-50">Sign in</a>
                        <a href="{{ route('register') }}" class="btn btn-primary py-2">Get started</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main @class(['mx-auto px-4 py-8 sm:py-12', 'max-w-5xl' => $wide, 'max-w-3xl' => ! $wide])>
            @if (session('status'))
                <div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-semibold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </body>
</html>

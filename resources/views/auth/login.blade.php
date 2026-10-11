@php($usePassword = request('method') === 'password')

<x-layout title="Sign in">
    <div class="card mx-auto max-w-md">
        <h1 class="text-3xl font-extrabold">Welcome back!</h1>

        @if (session('magic_link_sent'))
            <div role="status" class="mt-6 rounded-2xl bg-violet-50 p-5 text-violet-900">
                <p class="text-3xl" aria-hidden="true">📬</p>
                <p class="mt-2 font-bold">Check your email!</p>
                <p class="mt-1">If <strong>{{ old('email') }}</strong> has an account, we've sent it a sign-in link. The link works for {{ \App\Models\MagicLink::LIFETIME_MINUTES }} minutes.</p>
            </div>
        @endif

        @if ($usePassword)
            <p class="mt-2 text-stone-600">Sign in with the password you set on your account.</p>

            <form method="POST" action="{{ route('password-login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="label">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input">
                    <x-field-error name="email" />
                </div>
                <div>
                    <label for="password" class="label">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
                    <x-field-error name="password" />
                </div>
                <button type="submit" class="btn btn-primary w-full py-3 text-lg">Sign in</button>
            </form>

            <p class="mt-6 text-center text-stone-600">
                <a href="{{ route('login') }}" class="font-bold text-violet-700 underline">Email me a magic link instead</a>
            </p>
        @else
            <p class="mt-2 text-stone-600">Enter your email and we'll send you a magic link to sign in. No password needed.</p>

            <form method="POST" action="{{ route('magic-link.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="label">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input">
                    <x-field-error name="email" />
                </div>
                <button type="submit" class="btn btn-primary w-full py-3 text-lg">Email me a sign-in link</button>
            </form>

            <p class="mt-6 text-center text-stone-600">
                <a href="{{ route('login', ['method' => 'password']) }}" class="font-bold text-violet-700 underline">Sign in with a password</a>
            </p>
        @endif

        <p class="mt-3 text-center text-stone-600">
            New here? <a href="{{ route('register') }}" class="font-bold text-violet-700 underline">Create an account</a>
        </p>
    </div>
</x-layout>

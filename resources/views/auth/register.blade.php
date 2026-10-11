<x-layout title="Get started">
    <div class="card mx-auto max-w-md">
        <h1 class="text-3xl font-extrabold">Let's get reading!</h1>
        <p class="mt-2 text-stone-600">All you need is an email address. Next time, we'll email you a magic sign-in link.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="label">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input">
                <x-field-error name="email" />
            </div>
            <button type="submit" class="btn btn-primary w-full py-3 text-lg">Create my account</button>
        </form>

        <p class="mt-6 text-center text-stone-600">
            Already have an account? <a href="{{ route('login') }}" class="font-bold text-violet-700 underline">Sign in</a>
        </p>
    </div>
</x-layout>

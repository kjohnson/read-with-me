<x-layout title="Account">
    <h1 class="text-3xl font-extrabold">Your account</h1>

    <section class="card mt-6">
        <h2 class="text-xl font-extrabold">Your details</h2>

        <form method="POST" action="{{ route('account.update') }}" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="label">Name <span class="font-normal text-stone-500">(optional)</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" autocomplete="name" class="input">
                <x-field-error name="name" />
            </div>
            <div>
                <label for="email" class="label">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email" class="input">
                <x-field-error name="email" />
            </div>
            <button type="submit" class="btn btn-primary">Save details</button>
        </form>
    </section>

    <section class="card mt-6">
        <h2 class="text-xl font-extrabold">{{ $user->hasPassword() ? 'Change your password' : 'Set a password' }}</h2>
        <p class="mt-1 text-stone-600">
            @if ($user->hasPassword())
                You can sign in with your password or with a magic link.
            @else
                You sign in with magic links sent to your email. Set a password if you'd also like to sign in without checking your email.
            @endif
        </p>

        <form method="POST" action="{{ route('account.password.update') }}" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            @if ($user->hasPassword())
                <div>
                    <label for="current_password" class="label">Current password</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="input">
                    <x-field-error name="current_password" bag="password" />
                </div>
            @endif
            <div>
                <label for="password" class="label">New password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
                <x-field-error name="password" bag="password" />
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
            </div>
            <button type="submit" class="btn btn-primary">{{ $user->hasPassword() ? 'Change password' : 'Set password' }}</button>
        </form>

        @if ($user->hasPassword())
            <details class="mt-6 border-t border-stone-100 pt-4" @if ($errors->removePassword->any()) open @endif>
                <summary class="cursor-pointer font-bold text-stone-600">Remove my password</summary>
                <form method="POST" action="{{ route('account.password.destroy') }}" class="mt-4 space-y-4">
                    @csrf
                    @method('DELETE')
                    <p class="text-stone-600">You'll only be able to sign in with magic links.</p>
                    <div>
                        <label for="remove_current_password" class="label">Current password</label>
                        <input id="remove_current_password" name="current_password" type="password" required autocomplete="current-password" class="input">
                        <x-field-error name="current_password" bag="removePassword" />
                    </div>
                    <button type="submit" class="btn btn-danger">Remove password</button>
                </form>
            </details>
        @endif
    </section>
</x-layout>

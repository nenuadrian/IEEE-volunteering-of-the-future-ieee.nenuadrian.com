<x-guest-layout>
    <h2 class="text-2xl font-semibold text-ink">Sign in</h2>
    <p class="mt-1 text-sm text-warm-gray">Welcome back to IEEE Volunteering.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="mb-1.5 text-xs text-brand hover:underline" href="{{ route('password.request') }}">Forgot your password?</a>
                @endif
            </div>
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex items-center gap-2">
            <input id="remember_me" type="checkbox" class="checkbox" name="remember">
            <span class="text-sm text-warmer-gray">Keep me signed in</span>
        </label>

        <x-primary-button class="w-full">Sign in</x-primary-button>
    </form>

    <p class="mt-6 border-t border-light-gray pt-5 text-center text-sm text-warm-gray">
        New to IEEE Volunteering? <a href="{{ route('register') }}" class="font-semibold text-brand hover:underline">Create an account</a>
    </p>
</x-guest-layout>

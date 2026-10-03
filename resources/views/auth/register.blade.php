<x-guest-layout>
    <h2 class="text-2xl font-semibold text-ink">Create your account</h2>
    <p class="mt-1 text-sm text-warm-gray">Volunteer, create opportunities, or both — every account can do everything.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf

        {{-- Honeypot: hidden from real users, irresistible to bots. --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Website</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
        </div>

        <div>
            <x-input-label for="name" :value="__('Full name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <p class="help">Use the email associated with your IEEE account if you have one.</p>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            </div>
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>
        </div>
        <p class="help -mt-2">At least 8 characters, with letters and numbers.</p>
        <x-input-error :messages="$errors->get('password')" class="mt-2" />

        <x-primary-button class="w-full">Create account</x-primary-button>
    </form>

    <p class="mt-6 border-t border-light-gray pt-5 text-center text-sm text-warm-gray">
        Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">Sign in</a>
    </p>
</x-guest-layout>

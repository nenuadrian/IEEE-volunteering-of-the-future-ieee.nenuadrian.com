{{--
    Soft email-verification reminder. Shown to any signed-in user whose address
    isn't confirmed yet. Access is NOT blocked (soft mode) — this just nudges.
    Dismissible for the browser session via sessionStorage so it isn't naggy
    within a visit but reappears next time.
--}}
@auth
    @if (! auth()->user()->hasVerifiedEmail())
        <div x-data="{ show: sessionStorage.getItem('hideVerifyNotice') !== '1' }" x-show="show" x-cloak
             class="border-b border-amber-300/70 bg-amber-50">
            <div class="{{ ($wide ?? false) ? 'px-6' : 'container-x' }} flex flex-wrap items-center justify-between gap-3 py-3">
                <p class="flex items-start gap-2 font-ui text-sm text-amber-900">
                    <span aria-hidden="true" class="text-base leading-5">✉</span>
                    <span>
                        @if (session('status') === 'verification-link-sent')
                            <strong class="font-semibold">A new confirmation link is on its way.</strong> Check your inbox at <strong>{{ auth()->user()->email }}</strong>.
                        @else
                            Please confirm your email address (<strong>{{ auth()->user()->email }}</strong>) to secure your account.
                        @endif
                    </span>
                </p>
                <div class="flex items-center gap-1.5">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button class="rounded-full bg-amber-500 px-3.5 py-1.5 font-ui text-xs font-semibold text-white transition hover:bg-amber-600">
                            Resend confirmation email
                        </button>
                    </form>
                    <button type="button" @click="show = false; sessionStorage.setItem('hideVerifyNotice', '1')"
                            class="rounded-full px-2 py-1 text-lg leading-none text-amber-700/70 transition hover:bg-amber-100 hover:text-amber-900"
                            aria-label="Dismiss">&times;</button>
                </div>
            </div>
        </div>
    @endif
@endauth

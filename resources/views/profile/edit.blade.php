<x-app-layout>
    <x-slot name="header">
        <h1 class="section-title text-3xl">Account settings</h1>
        <p class="mt-3 text-warm-gray">Your sign-in details. Looking for skills, bio and IEEE details? <a href="{{ route('profile.volunteer.edit') }}" class="font-semibold text-brand hover:underline">Edit your volunteer profile</a>.</p>
    </x-slot>

    <div class="container-x max-w-3xl space-y-6 py-8">
        <div class="card p-6 sm:p-8">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="card p-6 sm:p-8">
            @include('profile.partials.update-password-form')
        </div>
        <div class="card p-6 sm:p-8">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>

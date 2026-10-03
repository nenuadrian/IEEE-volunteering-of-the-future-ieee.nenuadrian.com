@props(['user', 'size' => 'h-10 w-10', 'text' => 'text-sm'])

@php($profile = $user?->profile)
@if ($profile?->avatarUrl())
    <img src="{{ $profile->avatarUrl() }}" alt="" {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover ring-1 ring-black/5"]) }} loading="lazy">
@else
    <span {{ $attributes->merge(['class' => "$size $text grid shrink-0 place-items-center rounded-full font-semibold text-white"]) }}
          style="background-color: {{ $profile?->avatarColor() ?? '#e87722' }}" aria-hidden="true">{{ $user?->initials() ?? '?' }}</span>
@endif

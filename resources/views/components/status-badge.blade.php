@props(['status', 'type' => 'opportunity'])

@php
    $styles = [
        // Opportunity statuses
        'draft' => 'bg-light-gray text-warmer-gray',
        'open' => 'bg-accent-green-light/70 text-accent-green-dark',
        'in_progress' => 'bg-brand-50 text-brand-700',
        'on_hold' => 'bg-amber-100 text-amber-800',
        'completed' => 'bg-accent-blue/10 text-accent-blue',
        'cancelled' => 'bg-red-100 text-red-700',
        // Application statuses
        'pending' => 'bg-amber-100 text-amber-800',
        'accepted' => 'bg-accent-green-light/70 text-accent-green-dark',
        'rejected' => 'bg-red-100 text-red-700',
        'withdrawn' => 'bg-light-gray text-warm-gray',
        // Hour logs / generic
        'approved' => 'bg-accent-green-light/70 text-accent-green-dark',
        'published' => 'bg-accent-green-light/70 text-accent-green-dark',
        'success' => 'bg-accent-green-light/70 text-accent-green-dark',
        'failed' => 'bg-red-100 text-red-700',
        'running' => 'bg-amber-100 text-amber-800',
    ][$status] ?? 'bg-light-gray text-warmer-gray';

    $label = match ($type) {
        'opportunity' => config('volunteering.opportunity_statuses')[$status] ?? \Illuminate\Support\Str::headline($status),
        'application' => config('volunteering.application_statuses')[$status] ?? \Illuminate\Support\Str::headline($status),
        default => \Illuminate\Support\Str::headline($status),
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 font-ui text-xs font-semibold $styles"]) }}>{{ $label }}</span>

@props(['action', 'confirm' => 'Are you sure? This cannot be undone.', 'label' => 'Delete'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm }}')" class="inline">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->merge(['class' => 'font-ui text-sm font-medium text-red-600 hover:text-red-700']) }}>{{ $label }}</button>
</form>

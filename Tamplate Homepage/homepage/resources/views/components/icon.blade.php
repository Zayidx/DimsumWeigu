@props(['name', 'size' => 24, 'strokeWidth' => 2])

<svg xmlns="http://www.w3.org/2000/svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="{{ str_ends_with($name, '-filled') ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @include('components.icons.'.$name)
</svg>

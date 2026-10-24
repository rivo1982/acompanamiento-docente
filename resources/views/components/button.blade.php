{{-- @host-owned Copied from Vinkured resources/views/components/button.blade.php — do not modify here; replace with the host class at integration. --}}
@props([
    'variante' => 'primario',
    'tamano' => 'normal',
    'href' => null,
    'type' => 'button',
])

@php
    $variantes = [
        'primario' => 'border border-acento bg-acento text-sobre-acento hover:border-acento-hover hover:bg-acento-hover',
        'secundario' => 'border border-borde-campo bg-superficie text-texto hover:bg-superficie-atenuada',
        'peligro' => 'border border-peligro bg-peligro-fondo text-peligro hover:bg-peligro hover:text-sobre-acento',
        'fantasma' => 'border border-transparent bg-transparent text-acento hover:bg-acento-sutil',
    ];
    $tamanos = [
        'normal' => 'min-h-(--alto-control)',
        'tactil' => 'min-h-(--alto-control-tactil)',
    ];
    $deshabilitado = $attributes->has('disabled') && $attributes->get('disabled') !== false;
    $clases = 'inline-flex items-center justify-center gap-2 rounded-control px-4 text-tabla font-semibold disabled:cursor-not-allowed disabled:opacity-60 '
        .($variantes[$variante] ?? $variantes['primario']).' '
        .($tamanos[$tamano] ?? $tamanos['normal']);
@endphp

@if ($href && $deshabilitado)
    {{-- Un enlace no admite "disabled": se muestra sin destino, fuera del orden de tabulación. --}}
    <a role="link" aria-disabled="true" tabindex="-1" {{ $attributes->except('disabled')->class([$clases, 'cursor-not-allowed opacity-60 pointer-events-none']) }}>{{ $slot }}</a>
@elseif ($href)
    <a href="{{ $href }}" {{ $attributes->class([$clases]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$clases]) }}>{{ $slot }}</button>
@endif

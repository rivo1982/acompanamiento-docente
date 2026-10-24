{{-- @host-owned Copied from Vinkured resources/views/components/card.blade.php — do not modify here; replace with the host class at integration. --}}
@props(['titulo' => null, 'nivel' => 2])

@php
    $nivel = filter_var($nivel, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]);
    if ($nivel === false) {
        throw new InvalidArgumentException('El nivel del titulo de la tarjeta (nivel) debe ser un entero entre 1 y 6.');
    }
@endphp

<section {{ $attributes->class(['rounded-tarjeta border border-borde bg-superficie p-4 md:p-6']) }}>
    @if ($titulo)
        <h{{ $nivel }} class="mb-4 text-subtitulo font-semibold text-balance">{{ $titulo }}</h{{ $nivel }}>
    @endif
    {{ $slot }}
</section>

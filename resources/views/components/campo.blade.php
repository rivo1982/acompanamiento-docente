{{-- @host-owned Copied from Vinkured resources/views/components/campo.blade.php — do not modify here; replace with the host class at integration. --}}
@props([
    'id',
    'etiqueta',
    'obligatorio' => false,
    'ayuda' => null,
    'error' => null,
    'type' => 'text',
    // 'input' (por defecto), 'textarea' o 'select'. El `select` recibe sus <option> en el slot por defecto.
    'elemento' => 'input',
    // `name` de textarea y select (por defecto, el id); el input conserva su comportamiento.
    'name' => null,
])

@php
    $ayudaId = $id.'-ayuda';
    $errorId = $id.'-error';
    $descritoPor = trim(($ayuda ? $ayudaId : '').' '.($error ? $errorId : ''));
    $elemento = in_array($elemento, ['textarea', 'select'], true) ? $elemento : 'input';
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="text-tabla font-semibold">{{ $etiqueta.($obligatorio ? ' (obligatorio)' : '') }}</label>
    @if ($ayuda)
        <p id="{{ $ayudaId }}" class="text-tabla text-texto-atenuado">{{ $ayuda }}</p>
    @endif
    @if ($elemento === 'textarea')
        <textarea
            id="{{ $id }}"
            name="{{ $name ?? $id }}"
            @if ($obligatorio) required @endif
            @if ($error) aria-invalid="true" @endif
            @if ($descritoPor !== '') aria-describedby="{{ $descritoPor }}" @endif
            {{ $attributes->class([
                'w-full resize-y rounded-control border bg-superficie px-3 py-2 text-base text-texto disabled:cursor-not-allowed disabled:opacity-60',
                'border-borde-campo' => ! $error,
                'border-peligro' => $error,
            ]) }}
        ></textarea>
    @elseif ($elemento === 'select')
        <select
            id="{{ $id }}"
            name="{{ $name ?? $id }}"
            @if ($obligatorio) required @endif
            @if ($error) aria-invalid="true" @endif
            @if ($descritoPor !== '') aria-describedby="{{ $descritoPor }}" @endif
            {{ $attributes->class([
                'min-h-(--alto-control) w-full rounded-control border bg-superficie px-3 text-base text-texto disabled:cursor-not-allowed disabled:opacity-60',
                'border-borde-campo' => ! $error,
                'border-peligro' => $error,
            ]) }}
        >{{ $slot }}</select>
    @else
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            @if ($obligatorio) required @endif
            @if ($error) aria-invalid="true" @endif
            @if ($descritoPor !== '') aria-describedby="{{ $descritoPor }}" @endif
            {{ $attributes->class([
                'min-h-(--alto-control) w-full rounded-control border bg-superficie px-3 text-base text-texto disabled:cursor-not-allowed disabled:opacity-60',
                'border-borde-campo' => ! $error,
                'border-peligro' => $error,
            ]) }}
        >
    @endif
    @isset($pie)
        {{ $pie }}
    @endisset
    @if ($error)
        <p id="{{ $errorId }}" class="text-tabla text-peligro">{{ $error }}</p>
    @endif
</div>

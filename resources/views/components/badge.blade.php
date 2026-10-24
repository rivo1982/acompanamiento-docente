{{-- @host-owned Copied from Vinkured resources/views/components/badge.blade.php — do not modify here; replace with the host class at integration. --}}
@props(['variante' => 'neutro'])

@php
    $variantes = [
        'exito' => ['clases' => 'bg-exito-fondo text-exito', 'texto' => 'Éxito'],
        'aviso' => ['clases' => 'bg-aviso-fondo text-aviso', 'texto' => 'Aviso'],
        'peligro' => ['clases' => 'bg-peligro-fondo text-peligro', 'texto' => 'Peligro'],
        'informacion' => ['clases' => 'bg-informacion-fondo text-informacion', 'texto' => 'Información'],
        'neutro' => ['clases' => 'bg-neutro-fondo text-neutro', 'texto' => 'Neutro'],
    ];
    $definicion = $variantes[$variante] ?? $variantes['neutro'];
    $contenido = trim((string) $slot) !== '' ? $slot : $definicion['texto'];
@endphp

<span {{ $attributes->class(['inline-flex items-center whitespace-nowrap rounded-insignia px-3 py-1 text-nota font-semibold tabular-nums', $definicion['clases']]) }}>{{ $contenido }}</span>

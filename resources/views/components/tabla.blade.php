{{-- @host-owned Copied from Vinkured resources/views/components/tabla.blade.php — do not modify here; replace with the host class at integration. --}}
@props(['titulo'])

{{--
    Tabla base del brief (docs/diseno.md): encabezado fijo, filas de 40 px, texto de 14 px, sin cebrado.
    Uso: <x-tabla titulo="..."><x-slot:encabezado><tr><th scope="col">...</th></tr></x-slot:encabezado> <tr>...</tr></x-tabla>
    La región con desplazamiento horizontal es enfocable (tabindex="0") y su nombre es distinto del
    del caption y del título de la tarjeta. El anillo de foco lo dibuja la regla global de foco de app.css.
    El encabezado queda fijo solo cuando la lista es más alta que su contenedor de página.
--}}
<div
    role="region"
    aria-label="Desplazamiento horizontal: {{ $titulo }}"
    tabindex="0"
    {{ $attributes->class(['overflow-x-auto rounded-control border border-borde']) }}
>
    <table class="w-full border-collapse text-left text-tabla [&_td]:px-3 [&_td]:py-1 [&_td]:align-middle [&_tbody_tr]:h-(--alto-fila-tabla) [&_tr+tr]:border-t [&_tr+tr]:border-borde [&_th]:sticky [&_th]:top-0 [&_th]:z-(--z-fijo) [&_th]:whitespace-nowrap [&_th]:bg-superficie-atenuada [&_th]:px-3 [&_th]:py-2 [&_th]:font-semibold">
        <caption class="sr-only">{{ $titulo }}</caption>
        <thead>{{ $encabezado }}</thead>
        <tbody>{{ $slot }}</tbody>
    </table>
</div>

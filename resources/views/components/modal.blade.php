{{-- @host-owned Copied from Vinkured resources/views/components/modal.blade.php — do not modify here; replace with the host class at integration. --}}
{{--
    Modal de confirmación (brief: foco atrapado, Escape cierra, acciones irreversibles piden confirmación explícita;
    448 px de ancho y sombra `shadow-lg`, adiciones provisionales aprobadas).

    - `abierto`: expresión Alpine que dice si está abierto (p. ej. `$wire.confirmando`). Manda el servidor.
    - `cerrar`: expresión Alpine que lo cierra (p. ej. `$wire.cancelar()`); la usan Escape y el botón de cerrar.
    - `titulo`: título del diálogo; nombra al diálogo con `aria-labelledby`.
    - `descritoPor`: id del elemento (dentro del slot) que describe al diálogo (`aria-describedby`).
    - Slot por defecto: el resumen. Slot `acciones`: los botones, en el orden en que se leen y se tabulan
      (el que deba tener el foco inicial lleva `autofocus`).

    Alcanzable en pantallas bajas: el contenedor que hace scroll NO centra; un envoltorio interno de al menos su alto
    centra con `items-center`, así que un diálogo más alto que la pantalla crece hacia abajo y se recorre con scroll.

    `x-trap.noscroll` atrapa el foco, bloquea el scroll del fondo y devuelve el foco a quien abrió el modal al cerrarlo.
    La transición es solo de `opacity` (150 ms); con `prefers-reduced-motion` el CSS base la reduce a casi cero.
--}}
@props(['id', 'titulo', 'abierto', 'cerrar', 'descritoPor' => null])

<div
    x-data
    x-show="{{ $abierto }}"
    x-cloak
    x-transition.opacity.duration.150ms
    x-on:keydown.escape.window="if ({{ $abierto }}) { {{ $cerrar }} }"
    class="fixed inset-0 z-(--z-modal) overflow-y-auto overscroll-contain bg-velo"
>
    <div class="flex min-h-full items-center justify-center p-4">
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $id }}-titulo"
            @if ($descritoPor) aria-describedby="{{ $descritoPor }}" @endif
            x-trap.noscroll="{{ $abierto }}"
            class="w-full max-w-md rounded-modal border border-borde bg-superficie p-6 shadow-lg"
        >
            <div class="flex items-start justify-between gap-3">
                <h2 id="{{ $id }}-titulo" class="text-subtitulo font-semibold text-balance">{{ $titulo }}</h2>
                <button
                    type="button"
                    class="inline-flex size-(--alto-control) shrink-0 items-center justify-center rounded-control text-texto hover:bg-superficie-atenuada"
                    x-on:click="{{ $cerrar }}"
                >
                    <span class="sr-only">Cerrar</span>
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="mt-4 flex flex-col gap-4">{{ $slot }}</div>

            @isset($acciones)
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">{{ $acciones }}</div>
            @endisset
        </div>
    </div>
</div>

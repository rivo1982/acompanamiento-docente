{{-- @host-owned Copied from Vinkured resources/views/components/paginacion.blade.php — do not modify here; replace with the host class at integration. --}}
@props(['paginador', 'foco' => null])

{{--
    Paginación de los listados (Livewire `WithPagination`). Solo usa componentes y tokens del brief
    (docs/diseno.md): botón secundario, texto de 14 px, números tabulares y separador de miles chileno.
    No usa el marcado Tailwind por omisión del framework (sus clases no existen en este juego de tokens).
    Se usa dentro de la vista de un componente Livewire.

    POR QUÉ LOS BOTONES LOS MANEJA ALPINE (y no `wire:click`): tras cambiar de página hay que mover el foco al
    título de la lista (`$foco`: id de un `h2` con `tabindex="-1"`), y una acción de Livewire (`$wire.accion()`)
    devuelve una promesa que se resuelve cuando la vista ya se actualizó. La guardia contra clics repetidos que
    daba `wire:loading.attr="disabled"` ahora la da el estado local `ocupado` del `<nav>`:
      - `ir(accion)` ignora el clic si ya hay una petición en curso, pone `ocupado = true` ANTES de llamar a
        `$wire` y lo vuelve a `false` en un `finally` (también si la petición falla);
      - cada botón enlaza `x-bind:disabled="ocupado || $el.dataset.extremo === '1'"`: está deshabilitado mientras
        hay una petición en vuelo O cuando el servidor dice que es un extremo (`data-extremo`, calculado con el
        paginador). El atributo `disabled` estático del servidor se conserva para que el extremo también se vea sin
        JavaScript. No se usa `wire:loading.attr` porque quitaría el `disabled` del servidor al terminar;
      - al terminar (haya o no extremo) el foco pasa al título, así que un botón que se deshabilita no deja el foco
        de teclado en el aire. Sin `$foco` no se mueve el foco y no hay error (`titulo?.`).
    Un solo `<x-button>` por lado, con `wire:key` fijo: el nodo del botón se conserva al cambiar de página. Los
    extremos son botones `disabled` de verdad (nunca enlaces a "#"). El servidor además acota la página al rango
    válido (ver `FiltraPorEstado::paginar()`), de modo que ni un doble clic puede dejar la lista fuera de rango.
--}}
@php
    $total = $paginador->total();
    $ultima = max(1, $paginador->lastPage());
@endphp

@if ($total > 0)
    <nav
        aria-label="Paginación"
        class="mt-4 flex flex-wrap items-center justify-between gap-3"
        @if ($foco) data-foco="{{ $foco }}" @endif
        x-data="{
            ocupado: false,
            async ir(accion) {
                if (this.ocupado) return
                this.ocupado = true
                try {
                    await this.$wire[accion]()
                } finally {
                    this.ocupado = false
                    this.$nextTick(() => {
                        const titulo = this.$root.dataset.foco ? document.getElementById(this.$root.dataset.foco) : null
                        titulo?.focus()
                        titulo?.scrollIntoView()
                    })
                }
            },
        }"
    >
        <p class="text-tabla text-texto-atenuado tabular-nums">{{ \App\Support\FormatoDelPanel::resultados($total) }}</p>

        <div class="flex items-center gap-3">
            <x-button variante="secundario" wire:key="pagina-anterior" :disabled="$paginador->onFirstPage()" data-extremo="{{ $paginador->onFirstPage() ? '1' : '0' }}" x-bind:disabled="ocupado || $el.dataset.extremo === '1'" x-on:click="ir('previousPage')">Anterior</x-button>

            <p class="text-tabla tabular-nums">Página {{ $paginador->currentPage() }} de {{ $ultima }}</p>

            <x-button variante="secundario" wire:key="pagina-siguiente" :disabled="! $paginador->hasMorePages()" data-extremo="{{ $paginador->hasMorePages() ? '0' : '1' }}" x-bind:disabled="ocupado || $el.dataset.extremo === '1'" x-on:click="ir('nextPage')">Siguiente</x-button>
        </div>
    </nav>
@endif

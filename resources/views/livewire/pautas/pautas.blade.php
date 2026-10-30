{{-- Pautas: las del establecimiento activo, para verlas e imprimirlas; el administrador del módulo además las gestiona. --}}
<div>
    <x-page-header
        titulo="Pautas"
        descripcion="Pautas de observación del establecimiento, armadas con indicadores del catálogo."
    >
        @if ($puedeAdministrar)
            <x-slot:acciones>
                <x-button :href="route('pautas.create')">Nueva pauta</x-button>
            </x-slot:acciones>
        @endif
    </x-page-header>

    {{-- Mensaje de la última acción, como en "Observadores": el rol del propio mensaje más el foco del servidor. --}}
    @if ($mensaje !== null)
        <div
            id="mensaje-pautas"
            tabindex="-1"
            role="status"
            class="mb-6 rounded-control border border-exito bg-exito-fondo p-3 text-tabla text-exito text-pretty focus:outline-none"
        >{{ $mensaje }}</div>
    @endif

    <x-card aria-labelledby="titulo-pautas">
        <h2 id="titulo-pautas" class="mb-4 text-subtitulo font-semibold text-balance">Pautas del establecimiento ({{ \App\Support\FormatoDelPanel::entero($pautas->count()) }})</h2>

        @if ($pautas->isEmpty())
            <div class="flex flex-col items-start gap-4">
                <p class="text-base text-texto-atenuado text-pretty">Aún no hay pautas en este establecimiento.</p>
                @if ($puedeAdministrar)
                    <x-button :href="route('pautas.create')">Crear pauta</x-button>
                @endif
            </div>
        @else
            <x-tabla titulo="Pautas del establecimiento">
                <x-slot:encabezado>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Indicadores</th>
                        <th scope="col">Última actualización</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </x-slot:encabezado>

                @foreach ($pautas as $pauta)
                    <tr wire:key="pauta-{{ $pauta->id }}">
                        <td class="font-semibold break-words">{{ $pauta->nombre }}</td>
                        <td class="tabular-nums">{{ \App\Support\FormatoDelPanel::entero((int) $pauta->indicadores_count) }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ \App\Support\FormatoDelPanel::fechaYHora($pauta->updated_at) }}</td>
                        <td>
                            <div class="flex flex-wrap gap-2 py-1">
                                <x-button variante="secundario" :href="route('pautas.show', $pauta->id)">Ver<span class="sr-only"> la pauta {{ $pauta->nombre }}</span></x-button>
                                @if ($puedeAdministrar)
                                    <x-button variante="secundario" :href="route('pautas.edit', $pauta->id)">Editar<span class="sr-only"> la pauta {{ $pauta->nombre }}</span></x-button>
                                    <x-button
                                        variante="peligro"
                                        wire:click="confirmarEliminacion({{ $pauta->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="confirmarEliminacion({{ $pauta->id }})"
                                    >Eliminar<span class="sr-only"> la pauta {{ $pauta->nombre }}</span></x-button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        @endif
    </x-card>

    @if ($puedeAdministrar)
        <x-modal id="confirmar-eliminacion" titulo="{{ $porEliminar !== null ? '¿Eliminar la pauta '.$porEliminar->nombre.'?' : '¿Eliminar la pauta?' }}" abierto="$wire.eliminando !== null" cerrar="$wire.cancelar()" descrito-por="confirmar-eliminacion-aviso">
            <p id="confirmar-eliminacion-aviso" class="text-tabla text-texto-atenuado text-pretty">La pauta deja de listarse y no se puede usar en acompañamientos nuevos. Los acompañamientos ya registrados con ella se conservan.</p>

            <x-slot:acciones>
                <x-button variante="secundario" wire:click="cancelar" autofocus>Cancelar</x-button>
                <x-button variante="peligro" wire:click="eliminar" wire:loading.attr="disabled" wire:target="eliminar">
                    <span wire:loading.remove wire:target="eliminar">Eliminar pauta</span>
                    <span wire:loading wire:target="eliminar">Eliminando…</span>
                </x-button>
            </x-slot:acciones>
        </x-modal>
    @endif
</div>

{{-- Mis acompañamientos: los resultados propios del docente observado y los del observador, en curso (continuar o descartar) y finalizados (ver el resultado). --}}
<div>
    <x-page-header
        titulo="Mis acompañamientos"
        descripcion="Observaciones de aula en curso y finalizadas."
    >
        @if ($esObservador)
            <x-slot:acciones>
                <x-button :href="route('acompanamientos.create')">Nuevo acompañamiento</x-button>
            </x-slot:acciones>
        @endif
    </x-page-header>

    {{-- Mensaje de la última acción, como en "Pautas": el rol del propio mensaje más el foco del servidor. --}}
    @if ($mensaje !== null)
        <div
            id="mensaje-acompanamientos"
            tabindex="-1"
            role="status"
            class="mb-6 rounded-control border border-exito bg-exito-fondo p-3 text-tabla text-exito text-pretty focus:outline-none"
        >{{ $mensaje }}</div>
    @endif

    @if ($mostrarResultados)
        <x-card class="mb-6" aria-labelledby="titulo-mis-resultados">
            <h2 id="titulo-mis-resultados" class="mb-1 text-subtitulo font-semibold text-balance">Mis resultados ({{ \App\Support\FormatoDelPanel::entero($totalResultados) }})</h2>
            <p class="mb-4 text-tabla text-texto-atenuado text-pretty">Retroalimentación de las clases observadas, con las fichas de apoyo pedagógico.</p>

            @if ($resultados === [])
                <p class="text-base text-texto-atenuado text-pretty">Aún no hay acompañamientos finalizados de sus clases.</p>
            @else
                @include('livewire.acompanamientos.partials.tabla-resultados', ['filas' => $resultados])
                @if ($totalResultados > count($resultados))
                    <p class="mt-4 text-tabla text-texto-atenuado tabular-nums text-pretty">Se muestran los {{ \App\Support\FormatoDelPanel::entero(count($resultados)) }} más recientes.</p>
                @endif
            @endif
        </x-card>
    @endif

    @if (! $esObservador)
        <x-card aria-labelledby="titulo-sin-observaciones">
            <h2 id="titulo-sin-observaciones" class="mb-2 text-subtitulo font-semibold text-balance">Sin acompañamientos por observar</h2>
            <p class="text-base text-texto-atenuado text-pretty">Esta pantalla es para quienes observan clases. El resultado de una clase observada llega por correo, con un enlace para verlo.</p>
        </x-card>
    @else
        @if ($esAdministrador)
            <div class="mb-6 max-w-sm">
                <x-campo id="alcance" elemento="select" etiqueta="Mostrar" wire:model.live="alcance">
                    <option value="{{ \App\Livewire\Acompanamientos\Acompanamientos::ALCANCE_PROPIOS }}">Mis acompañamientos</option>
                    <option value="{{ \App\Livewire\Acompanamientos\Acompanamientos::ALCANCE_ESTABLECIMIENTO }}">Todos del establecimiento</option>
                </x-campo>
            </div>
        @endif

        <x-card class="mb-6" aria-labelledby="titulo-en-curso">
            <h2 id="titulo-en-curso" class="mb-4 text-subtitulo font-semibold text-balance">En curso ({{ \App\Support\FormatoDelPanel::entero(count($enCurso)) }})</h2>

            @if ($enCurso === [])
                <p class="text-base text-texto-atenuado text-pretty">Aún no hay acompañamientos en curso.</p>
            @else
                @include('livewire.acompanamientos.partials.tabla', ['filas' => $enCurso, 'titulo' => 'Acompañamientos en curso', 'columnaFecha' => 'Inicio'])
            @endif
        </x-card>

        <x-card aria-labelledby="titulo-finalizados">
            <h2 id="titulo-finalizados" tabindex="-1" class="mb-4 text-subtitulo font-semibold text-balance focus:outline-none">Finalizados ({{ \App\Support\FormatoDelPanel::entero($finalizados->total()) }})</h2>

            @if ($finalizados->isEmpty())
                <p class="text-base text-texto-atenuado text-pretty">Aún no hay acompañamientos finalizados.</p>
            @else
                @include('livewire.acompanamientos.partials.tabla', ['filas' => $finalizados->items(), 'titulo' => 'Acompañamientos finalizados', 'columnaFecha' => 'Término'])
                <x-paginacion :paginador="$finalizados" foco="titulo-finalizados" />
            @endif
        </x-card>

        <x-modal id="confirmar-descarte" titulo="{{ $porDescartar !== null ? '¿Descartar el acompañamiento de '.$porDescartar['docente'].'?' : '¿Descartar el acompañamiento?' }}" abierto="$wire.descartando !== null" cerrar="$wire.cancelar()" descrito-por="confirmar-descarte-aviso">
            <p id="confirmar-descarte-aviso" class="text-tabla text-texto-atenuado text-pretty">Se eliminan el acompañamiento y los niveles ya asignados. No se puede deshacer.</p>

            <x-slot:acciones>
                <x-button variante="secundario" wire:click="cancelar" autofocus>Cancelar</x-button>
                <x-button variante="peligro" wire:click="descartar" wire:loading.attr="disabled" wire:target="descartar">
                    <span wire:loading.remove wire:target="descartar">Descartar acompañamiento</span>
                    <span wire:loading wire:target="descartar">Descartando…</span>
                </x-button>
            </x-slot:acciones>
        </x-modal>
    @endif
</div>

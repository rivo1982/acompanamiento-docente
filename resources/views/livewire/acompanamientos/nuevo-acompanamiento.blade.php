{{-- Nuevo acompañamiento: elegir docente asignado, pauta, curso y asignatura, y pasar al formulario de observación. --}}
<div>
    <x-page-header
        titulo="Nuevo acompañamiento"
        descripcion="Elegir a quién se va a observar y con qué pauta. La hora de inicio se registra al iniciar."
    />

    {{-- Formulario hasta 768 px (brief). --}}
    <x-card class="max-w-3xl" aria-labelledby="titulo-nuevo">
        <h2 id="titulo-nuevo" class="mb-4 text-subtitulo font-semibold text-balance">Datos de la clase</h2>

        @if ($docentes === [])
            <div class="flex flex-col items-start gap-4">
                <p class="text-base text-texto-atenuado text-pretty">Aún no hay docentes asignados para observar. El administrador del módulo los asigna en Observadores.</p>
                <x-button variante="secundario" :href="route('acompanamientos.index')">Volver a Mis acompañamientos</x-button>
            </div>
        @elseif ($pautas->isEmpty())
            <div class="flex flex-col items-start gap-4">
                <p class="text-base text-texto-atenuado text-pretty">Aún no hay pautas con indicadores en este establecimiento.</p>
                <x-button variante="secundario" :href="route('pautas.index')">Ir a Pautas</x-button>
            </div>
        @else
            <form method="post" wire:submit="iniciar" class="flex flex-col gap-6" novalidate>
                <x-campo id="docente" elemento="select" etiqueta="Docente" :obligatorio="true" :error="$errors->first('docente')" ayuda="Solo los docentes asignados para observar." wire:model.live="docente">
                    <option value="">Elegir docente</option>
                    @foreach ($docentes as $docente)
                        <option value="{{ $docente['id'] }}" wire:key="docente-{{ $docente['id'] }}">{{ $docente['nombre'] }}</option>
                    @endforeach
                </x-campo>

                <x-campo id="pauta" elemento="select" etiqueta="Pauta" :obligatorio="true" :error="$errors->first('pauta')" wire:model="pauta">
                    <option value="">Elegir pauta</option>
                    @foreach ($pautas as $pauta)
                        <option value="{{ $pauta->id }}" wire:key="pauta-{{ $pauta->id }}">{{ $pauta->nombre }} ({{ $pauta->indicadores_count === 1 ? '1 indicador' : \App\Support\FormatoDelPanel::entero((int) $pauta->indicadores_count).' indicadores' }})</option>
                    @endforeach
                </x-campo>

                <div class="grid gap-6 lg:grid-cols-2">
                    <x-campo id="curso" elemento="select" etiqueta="Curso" :obligatorio="true" :error="$errors->first('curso')" wire:model="curso">
                        <option value="">Elegir curso</option>
                        @foreach ($cursos as $curso)
                            <option value="{{ $curso->id }}" wire:key="curso-{{ $curso->id }}">{{ $curso->nombreCompleto() }}</option>
                        @endforeach
                    </x-campo>

                    <x-campo id="asignatura" elemento="select" etiqueta="Asignatura" :obligatorio="true" :error="$errors->first('asignatura')" wire:model="asignatura">
                        <option value="">Elegir asignatura</option>
                        @foreach ($asignaturas as $asignatura)
                            <option value="{{ $asignatura->id }}" wire:key="asignatura-{{ $asignatura->id }}">{{ $asignatura->nombre }}</option>
                        @endforeach
                    </x-campo>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="iniciar">
                        <span wire:loading.remove wire:target="iniciar">Iniciar acompañamiento</span>
                        <span wire:loading wire:target="iniciar">Iniciando…</span>
                    </x-button>
                    <x-button variante="secundario" :href="route('acompanamientos.index')">Cancelar</x-button>
                </div>
            </form>
        @endif
    </x-card>
</div>

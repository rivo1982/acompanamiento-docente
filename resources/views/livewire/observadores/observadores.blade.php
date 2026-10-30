{{-- Observadores: quiénes observan en este establecimiento y a qué docentes acompaña cada uno, en una sola pantalla. --}}
<div>
    <x-page-header
        titulo="Observadores"
        descripcion="Quiénes observan clases en este establecimiento y a qué docentes acompaña cada uno."
    />

    {{-- Mensaje de la última acción, como en "Invitaciones pendientes": el rol del propio mensaje más el foco del servidor. --}}
    @if ($mensaje !== null)
        <div
            id="mensaje-observadores"
            tabindex="-1"
            role="{{ $tipoDeMensaje === \App\Livewire\Observadores\Observadores::EXITO ? 'status' : 'alert' }}"
            @class([
                'mb-6 rounded-control border p-3 text-tabla text-pretty focus:outline-none',
                'border-exito bg-exito-fondo text-exito' => $tipoDeMensaje === \App\Livewire\Observadores\Observadores::EXITO,
                'border-aviso bg-aviso-fondo text-aviso' => $tipoDeMensaje === \App\Livewire\Observadores\Observadores::AVISO,
            ])
        >{{ $mensaje }}</div>
    @endif

    {{-- Formulario hasta 768 px (brief); las listas, a ancho completo. --}}
    <x-card class="mb-6 max-w-3xl" aria-labelledby="titulo-habilitar">
        <h2 id="titulo-habilitar" class="mb-4 text-subtitulo font-semibold text-balance">Habilitar observador</h2>

        @if ($candidatos === [])
            <p class="text-base text-texto-atenuado text-pretty">No hay funcionarios vigentes por habilitar: todos ya son observadores.</p>
        @else
            <form method="post" wire:submit="habilitar" class="flex flex-col gap-6" novalidate>
                <x-campo
                    id="nuevoObservador"
                    elemento="select"
                    etiqueta="Funcionario"
                    :obligatorio="true"
                    ayuda="Los directivos observan por sus funciones y no necesitan habilitarse."
                    :error="$errors->first('nuevoObservador')"
                    wire:model="nuevoObservador"
                >
                    <option value="">Elegir funcionario</option>
                    @foreach ($candidatos as $candidato)
                        <option value="{{ $candidato['id'] }}" wire:key="candidato-{{ $candidato['id'] }}">{{ $candidato['nombre'] }}</option>
                    @endforeach
                </x-campo>

                <div>
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="habilitar">
                        <span wire:loading.remove wire:target="habilitar">Habilitar observador</span>
                        <span wire:loading wire:target="habilitar">Habilitando…</span>
                    </x-button>
                </div>
            </form>
        @endif
    </x-card>

    <x-card class="mb-6" aria-labelledby="titulo-observadores">
        <h2 id="titulo-observadores" class="mb-4 text-subtitulo font-semibold text-balance">Observadores del establecimiento ({{ \App\Support\FormatoDelPanel::entero(count($filas)) }})</h2>

        @if (! $hayHabilitados)
            <p class="mb-4 text-base text-texto-atenuado text-pretty">Aún no hay observadores habilitados. Los directivos observan por sus funciones; para sumar a otro funcionario, habilitarlo con el formulario de arriba.</p>
        @endif

        @if ($filas !== [])
            <x-tabla titulo="Observadores del establecimiento">
                <x-slot:encabezado>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Función</th>
                        <th scope="col">Docentes asignados</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </x-slot:encabezado>

                @foreach ($filas as $fila)
                    @php($esElegido = $elegido !== null && $elegido['id'] === $fila['id'])
                    {{-- La fila elegida se marca con color y con texto ("Viendo docentes"), nunca solo con color. --}}
                    <tr
                        wire:key="observador-{{ $fila['id'] }}"
                        @if ($esElegido) aria-current="true" class="bg-acento-sutil" @endif
                    >
                        <td>
                            <div class="flex flex-wrap items-center gap-2 py-1">
                                <span class="font-semibold">{{ $fila['nombre'] }}</span>
                                @if ($fila['automatico'])
                                    <x-badge variante="neutro">Automático</x-badge>
                                @endif
                            </div>
                        </td>
                        <td>{{ $fila['funcion'] }}</td>
                        <td class="tabular-nums">{{ \App\Support\FormatoDelPanel::entero($fila['docentes']) }}</td>
                        <td>
                            <div class="py-1">
                                <x-button
                                    variante="secundario"
                                    wire:click="seleccionar({{ $fila['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="seleccionar({{ $fila['id'] }})"
                                    aria-controls="docentes-del-observador"
                                >{{ $esElegido ? 'Viendo docentes' : 'Ver docentes' }}<span class="sr-only"> de {{ $fila['nombre'] }}</span></x-button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        @endif
    </x-card>

    <div id="docentes-del-observador">
        @if ($elegido !== null)
            <x-card aria-labelledby="titulo-docentes" wire:key="docentes-{{ $elegido['id'] }}">
                <h2 id="titulo-docentes" tabindex="-1" class="mb-1 text-subtitulo font-semibold text-balance focus:outline-none">Docentes que acompaña {{ $elegido['nombre'] }}</h2>
                <p class="mb-4 text-tabla text-texto-atenuado text-pretty">
                    @if ($elegido['automatico'])
                        Observa por sus funciones de directivo; no se puede quitar como observador.
                    @else
                        Marcar los docentes que acompaña y guardar.
                    @endif
                </p>

                @if ($docentesObservables === [])
                    <p class="mb-6 text-base text-texto-atenuado text-pretty">No hay docentes vigentes para asignar en este establecimiento.</p>
                @else
                    <form method="post" wire:submit="guardar" class="mb-6 flex flex-col gap-6" novalidate>
                        <fieldset>
                            <legend class="mb-2 text-tabla font-semibold">Docentes ({{ \App\Support\FormatoDelPanel::entero(count($docentesObservables)) }})</legend>
                            <ul class="grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($docentesObservables as $docente)
                                    <li wire:key="docente-{{ $docente['id'] }}">
                                        <label class="flex min-h-(--alto-control) cursor-pointer items-center gap-3 text-base">
                                            <input type="checkbox" wire:model="docentes" value="{{ $docente['id'] }}" class="size-4 shrink-0 accent-acento">
                                            <span class="min-w-0 break-words">{{ $docente['nombre'] }}</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </fieldset>

                        <div>
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                                <span wire:loading.remove wire:target="guardar">Guardar docentes</span>
                                <span wire:loading wire:target="guardar">Guardando…</span>
                            </x-button>
                        </div>
                    </form>
                @endif

                @unless ($elegido['automatico'])
                    <div class="border-t border-borde pt-4">
                        <x-button variante="peligro" wire:click="confirmarQuita" wire:loading.attr="disabled" wire:target="confirmarQuita">Quitar como observador</x-button>
                    </div>
                @endunless
            </x-card>
        @endif
    </div>

    <x-modal id="confirmar-quita" titulo="{{ $elegido !== null ? '¿Quitar a '.$elegido['nombre'].' como observador?' : '¿Quitar como observador?' }}" abierto="$wire.confirmandoQuita" cerrar="$wire.cancelar()" descrito-por="confirmar-quita-aviso">
        <p id="confirmar-quita-aviso" class="text-tabla text-texto-atenuado text-pretty">Deja de poder observar y se quitan sus docentes asignados. Los acompañamientos ya registrados se conservan.</p>

        <x-slot:acciones>
            <x-button variante="secundario" wire:click="cancelar" autofocus>Cancelar</x-button>
            <x-button variante="peligro" wire:click="quitar" wire:loading.attr="disabled" wire:target="quitar">
                <span wire:loading.remove wire:target="quitar">Quitar observador</span>
                <span wire:loading wire:target="quitar">Quitando…</span>
            </x-button>
        </x-slot:acciones>
    </x-modal>
</div>

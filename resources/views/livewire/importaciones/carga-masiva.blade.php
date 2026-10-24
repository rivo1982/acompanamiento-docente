{{-- Carga masiva: subir una planilla .xlsx de funcionarios y ver las cargas recientes de este establecimiento. --}}
<div>
    <x-page-header
        titulo="Carga masiva"
        descripcion="Invitar a varios funcionarios a la vez con una planilla Excel. Cada persona recibe un correo para activar su acceso."
    >
        <x-slot:acciones>
            <x-button :href="route('invitaciones.index')" variante="secundario">Ver invitaciones pendientes</x-button>
        </x-slot:acciones>
    </x-page-header>

    {{-- Formulario hasta 768 px (brief); la lista de cargas, a ancho completo. --}}
    <x-card class="mb-6 max-w-3xl" aria-labelledby="titulo-subir">
        <h2 id="titulo-subir" class="mb-4 text-subtitulo font-semibold text-balance">Subir planilla</h2>

        <div class="mb-6 flex flex-col gap-3 text-base text-pretty">
            <p>La fila 1 de la planilla lleva estas columnas, en este orden, y cada fila siguiente es una persona:</p>
            <ol class="list-decimal pl-6">
                @foreach (\App\Support\Importacion\ValidadorDePlanilla::ENCABEZADO as $columna)
                    <li>{{ $columna }}@if (in_array($columna, ['Segundo apellido', 'Teléfono'], true)) <span class="text-texto-atenuado">(opcional)</span>@endif</li>
                @endforeach
            </ol>
            <p>Función acepta: {{ \App\Livewire\Importaciones\CargaMasiva::FUNCIONES }}. Hasta {{ \App\Support\FormatoDelPanel::entero(\App\Support\Importacion\ValidadorDePlanilla::MAXIMO_DE_FILAS) }} personas por planilla.</p>
            <div>
                <x-button :href="route('importaciones.plantilla')" variante="secundario">Descargar plantilla</x-button>
            </div>
        </div>

        <form method="post" wire:submit="subir" class="flex flex-col gap-6" novalidate>
            <x-campo
                id="archivo"
                type="file"
                etiqueta="Planilla"
                :obligatorio="true"
                ayuda="Archivo Excel (.xlsx) de hasta {{ $tamanoMaximo }}, con las columnas indicadas."
                :error="$errors->first('archivo')"
                wire:model="archivo"
                name="archivo"
                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                class="py-2"
            >
                <x-slot:pie>
                    <p wire:loading wire:target="archivo" class="text-tabla text-texto-atenuado">Cargando archivo…</p>
                </x-slot:pie>
            </x-campo>

            <div>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="archivo,subir">
                    <span wire:loading.remove wire:target="subir">Subir planilla</span>
                    <span wire:loading wire:target="subir">Subiendo planilla…</span>
                </x-button>
            </div>
        </form>
    </x-card>

    <x-card aria-labelledby="titulo-cargas">
        <h2 id="titulo-cargas" tabindex="-1" class="mb-4 text-subtitulo font-semibold text-balance focus:outline-none">Cargas recientes ({{ \App\Support\FormatoDelPanel::entero($paginador->total()) }})</h2>

        @if ($cargas->isEmpty())
            <p class="text-base text-texto-atenuado text-pretty">Aún no hay cargas masivas en este establecimiento.</p>
        @else
            <x-tabla titulo="Cargas recientes">
                <x-slot:encabezado>
                    <tr>
                        <th scope="col">Archivo</th>
                        <th scope="col">Subida el</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Resultado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </x-slot:encabezado>

                @foreach ($cargas as $carga)
                    <tr wire:key="carga-{{ $carga->id }}">
                        <td class="break-all font-semibold">{{ $carga->archivo }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ \App\Support\FormatoDelPanel::fechaYHora($carga->created_at) }}</td>
                        <td>{{ \App\Livewire\Importaciones\CargaMasiva::ESTADOS[$carga->estado->value] }}</td>
                        <td class="tabular-nums">{{ \App\Livewire\Importaciones\CargaMasiva::resumen($carga) }}</td>
                        <td>
                            <x-button :href="route('importaciones.show', $carga->id)" variante="fantasma">Ver resultado<span class="sr-only"> de {{ $carga->archivo }}</span></x-button>
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        @endif

        <x-paginacion :paginador="$paginador" foco="titulo-cargas" />
    </x-card>
</div>

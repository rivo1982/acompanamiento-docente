{{-- Resultado de una carga masiva. Consulta cada 5 s mientras está pendiente o procesando; al terminar deja de hacerlo. --}}
<div @unless ($terminada) wire:poll.5s @endunless>
    <x-page-header
        titulo="Resultado de la importación"
        descripcion="Planilla subida el {{ \App\Support\FormatoDelPanel::fechaYHora($importacion->created_at) }}."
    >
        <x-slot:acciones>
            @if ($importacion->creados > 0)
                <x-button :href="route('invitaciones.index')">Ver invitaciones pendientes</x-button>
            @endif
            <x-button :href="route('importaciones.index')" variante="secundario">Volver a carga masiva</x-button>
        </x-slot:acciones>
    </x-page-header>

    <x-card class="mb-6" aria-labelledby="titulo-carga">
        <h2 id="titulo-carga" class="mb-4 break-all text-subtitulo font-semibold text-balance">{{ $importacion->archivo }}</h2>

        <p class="mb-4 text-base">Estado: <span class="font-semibold">{{ $estado }}</span></p>

        {{--
            Única región de estado de la pantalla. Su texto es el mismo mientras la carga está pendiente o procesando
            y cambia una sola vez al terminar: las consultas periódicas no repiten el anuncio.
        --}}
        <p role="status" id="estado-importacion" class="text-base text-pretty">{{ $situacion }}</p>

        @if ($importacion->estado === \App\Enums\EstadoImportacion::Completada)
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-control border border-borde p-3">
                    <dt class="text-tabla text-texto-atenuado">Filas en la planilla</dt>
                    <dd class="text-subtitulo font-semibold tabular-nums">{{ \App\Support\FormatoDelPanel::entero($importacion->total) }}</dd>
                </div>
                <div class="rounded-control border border-borde p-3">
                    <dt class="text-tabla text-texto-atenuado">Personas invitadas</dt>
                    <dd class="text-subtitulo font-semibold tabular-nums">{{ \App\Support\FormatoDelPanel::entero($importacion->creados) }}</dd>
                </div>
                <div class="rounded-control border border-borde p-3">
                    <dt class="text-tabla text-texto-atenuado">Filas con error</dt>
                    <dd class="text-subtitulo font-semibold tabular-nums">{{ \App\Support\FormatoDelPanel::entero($importacion->con_error) }}</dd>
                </div>
            </dl>
        @endif
    </x-card>

    {{-- Problema del archivo completo (fila nula): Aviso de página, sin rol propio (la región de estado ya lo anuncia). --}}
    @if ($erroresDelArchivo !== [])
        <div class="mb-6 rounded-control border border-aviso bg-aviso-fondo p-3 text-tabla text-aviso text-pretty">
            @foreach ($erroresDelArchivo as $mensaje)
                <p>{{ $mensaje }}</p>
            @endforeach
            <p class="mt-2">Corregir la planilla y volver a subirla desde la carga masiva.</p>
        </div>
    @endif

    @if ($erroresPorFila !== [])
        <x-card aria-labelledby="titulo-errores">
            <h2 id="titulo-errores" class="mb-4 text-subtitulo font-semibold text-balance">Filas con error</h2>
            <p class="mb-4 text-tabla text-texto-atenuado text-pretty">Las demás filas se procesaron. Corregir estas filas y subirlas en una planilla nueva.</p>

            <x-tabla titulo="Filas con error">
                <x-slot:encabezado>
                    <tr>
                        <th scope="col">Fila</th>
                        <th scope="col">Mensajes</th>
                    </tr>
                </x-slot:encabezado>

                @foreach ($erroresPorFila as $error)
                    <tr wire:key="fila-{{ $error['fila'] }}">
                        <td class="tabular-nums">{{ \App\Support\FormatoDelPanel::entero((int) $error['fila']) }}</td>
                        <td>
                            <ul class="flex flex-col gap-1 py-1">
                                @foreach ($error['mensajes'] as $mensaje)
                                    <li>{{ $mensaje }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        </x-card>
    @endif
</div>

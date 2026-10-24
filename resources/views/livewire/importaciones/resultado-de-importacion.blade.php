{{--
    Resultado de una carga masiva. Consulta cada 5 s mientras está pendiente o procesando; deja de hacerlo al terminar
    o al pasar el límite de seguimiento (config/importaciones.php).
--}}
<div @if ($consultando) wire:poll.5s @endif>
    <x-page-header
        titulo="Resultado de la carga masiva"
        descripcion="Planilla subida el {{ \App\Support\FormatoDelPanel::fechaYHora($importacion->created_at) }}."
    >
        <x-slot:acciones>
            @if ($importacion->creados > 0)
                <x-button :href="route('invitaciones.index')">Ver invitaciones pendientes</x-button>
            @endif
            <x-button :href="route('importaciones.index')" variante="secundario">Volver a la carga masiva</x-button>
        </x-slot:acciones>
    </x-page-header>

    <x-card class="mb-6" aria-labelledby="titulo-carga">
        <h2 id="titulo-carga" class="mb-4 break-all text-subtitulo font-semibold text-balance">{{ $importacion->archivo }}</h2>

        <p class="mb-4 text-base">Estado: <x-badge :variante="$variante">{{ $estado }}</x-badge></p>

        {{--
            Única región de estado de la pantalla. Su texto es el mismo mientras la carga está pendiente o procesando
            y cambia una sola vez al terminar (o al dejar de consultar por demora): las consultas periódicas no repiten
            el anuncio. Si la carga falló o se demoró, la región es un Aviso (qué pasó y qué hacer) y lleva los mensajes
            del archivo completo (fila nula); sin otra región ni rol propio, para que se anuncie una sola vez.
        --}}
        <div
            id="estado-importacion"
            role="status"
            @class([
                'text-pretty',
                'text-base' => ! $comoAviso,
                'rounded-control border border-aviso bg-aviso-fondo p-3 text-tabla text-aviso' => $comoAviso,
            ])
        >
            <p>{{ $situacion }}</p>
            @if ($erroresDelArchivo !== [])
                <ul class="mt-2 flex list-disc flex-col gap-1 pl-6">
                    @foreach ($erroresDelArchivo as $mensaje)
                        <li>{{ $mensaje }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($importacion->estado === \App\Enums\EstadoImportacion::Completada)
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-tarjeta border border-borde p-3">
                    <dt class="text-tabla text-texto-atenuado">Filas en la planilla</dt>
                    <dd class="text-subtitulo font-semibold tabular-nums">{{ \App\Support\FormatoDelPanel::entero($importacion->total) }}</dd>
                </div>
                <div class="rounded-tarjeta border border-borde p-3">
                    <dt class="text-tabla text-texto-atenuado">Personas invitadas</dt>
                    <dd class="text-subtitulo font-semibold tabular-nums">{{ \App\Support\FormatoDelPanel::entero($importacion->creados) }}</dd>
                </div>
                <div class="rounded-tarjeta border border-borde p-3">
                    <dt class="text-tabla text-texto-atenuado">Filas con error</dt>
                    <dd class="text-subtitulo font-semibold tabular-nums">{{ \App\Support\FormatoDelPanel::entero($importacion->con_error) }}</dd>
                </div>
            </dl>
        @endif
    </x-card>

    @if ($erroresPorFila !== [])
        <x-card aria-labelledby="titulo-errores">
            <h2 id="titulo-errores" tabindex="-1" class="mb-4 text-subtitulo font-semibold text-balance focus:outline-none">Filas con error</h2>
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

            {{-- 20 filas por página; sin controles cuando caben en una (x-paginacion solo se muestra si hay datos). --}}
            @if ($paginador->hasPages())
                <x-paginacion :paginador="$paginador" foco="titulo-errores" />
            @endif
        </x-card>
    @endif
</div>

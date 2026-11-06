{{--
    Retroalimentación de un acompañamiento: datos de la clase, resumen por nivel, indicadores en tres secciones (para
    trabajar ahora, para llevar a Destacado y logros destacados) con su ficha de apoyo pedagógico bajo Destacado, el
    ciclo sugerido y la plantilla "Plan de mejora acordado".
    Lista para imprimir o guardar como PDF (window.print()), como la pauta: con `print:` se ocultan los botones, las
    tarjetas pierden borde y relleno, y cada indicador evita partirse entre páginas. La barra lateral, la superior y el
    enlace de salto son del layout (copia de Vinkured, sin variantes de impresión): la regla de abajo los oculta solo en
    esta vista, sin tocar el layout.
--}}
@php
    // Clases completas por variante de nivel (paleta de estados del brief), escritas enteras para que Tailwind las genere.
    $tonos = [
        'aviso' => 'border-aviso bg-aviso-fondo text-aviso',
        'neutro' => 'border-neutro bg-neutro-fondo text-neutro',
        'exito' => 'border-exito bg-exito-fondo text-exito',
        'informacion' => 'border-informacion bg-informacion-fondo text-informacion',
    ];
@endphp
<div class="print:text-texto">
    <style>
        @media print {
            #barra-lateral,
            a[href="#contenido"],
            :has(> #contenido) > header {
                display: none !important;
            }

            #contenido {
                padding: 0 !important;
            }
        }
    </style>

    {{-- Línea de documento: solo en la versión impresa. --}}
    <p class="mb-4 hidden text-nota text-texto-atenuado print:block">{{ $establecimiento !== null ? $establecimiento.' · ' : '' }}Acompañamiento de {{ $docente }} · {{ $pauta }}</p>

    <x-page-header
        titulo="Retroalimentación del acompañamiento"
        descripcion="Nivel de cada indicador observado, su retroalimentación y las fichas de apoyo pedagógico para el próximo ciclo."
    >
        <x-slot:acciones>
            <x-button :href="route('acompanamientos.index')" variante="secundario" class="print:hidden">Volver a acompañamientos</x-button>
            @if ($puedeContinuar)
                <x-button :href="route('acompanamientos.edit', $acompanamientoId)" variante="secundario" class="print:hidden">Continuar observación</x-button>
            @endif
            <x-button x-data x-on:click="window.print()" class="print:hidden">Imprimir o guardar PDF</x-button>
        </x-slot:acciones>
    </x-page-header>

    @if (! $finalizado)
        <div role="status" class="mb-6 rounded-control border border-aviso bg-aviso-fondo p-3 text-tabla text-aviso text-pretty">
            Acompañamiento en curso: el resultado queda disponible para el docente al finalizarlo.
        </div>
    @endif

    <x-card class="mb-6 break-inside-avoid print:border-0 print:p-0" aria-labelledby="titulo-clase">
        <h2 id="titulo-clase" class="sr-only">Datos de la clase</h2>
        <dl class="grid gap-4 text-tabla sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="font-semibold">Docente</dt>
                <dd class="break-words text-texto-atenuado">{{ $docente }}</dd>
            </div>
            <div>
                <dt class="font-semibold">Observador</dt>
                <dd class="break-words text-texto-atenuado">{{ $observador }}</dd>
            </div>
            <div>
                <dt class="font-semibold">Pauta</dt>
                <dd class="break-words text-texto-atenuado">{{ $pauta }}</dd>
            </div>
            <div>
                <dt class="font-semibold">Curso</dt>
                <dd class="break-words text-texto-atenuado">{{ $curso }}</dd>
            </div>
            <div>
                <dt class="font-semibold">Asignatura</dt>
                <dd class="break-words text-texto-atenuado">{{ $asignatura }}</dd>
            </div>
            <div>
                <dt class="font-semibold">{{ $finalizado ? 'Término' : 'Inicio' }}</dt>
                <dd class="tabular-nums text-texto-atenuado">{{ $fecha }}</dd>
            </div>
        </dl>
    </x-card>

    <x-card class="mb-6 break-inside-avoid print:border-0 print:p-0" aria-labelledby="titulo-resumen">
        <h2 id="titulo-resumen" class="mb-4 text-subtitulo font-semibold text-balance">Resumen por nivel</h2>
        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($resumen as $nivel)
                <div class="flex flex-col gap-1 rounded-control border p-4 {{ $tonos[$nivel['variante']] ?? $tonos['neutro'] }}">
                    <dt class="text-tabla font-semibold">{{ $nivel['etiqueta'] }}</dt>
                    <dd class="text-titulo font-semibold tabular-nums">{{ $nivel['cantidad'] }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-4 text-tabla text-texto-atenuado text-pretty">{{ $orientaciones->notaDeUso() }}</p>
    </x-card>

    <div class="flex flex-col gap-6">
        @foreach ($secciones as $seccion)
            <x-card wire:key="seccion-{{ $seccion['clave'] }}" class="print:border-0 print:p-0" aria-labelledby="seccion-{{ $seccion['clave'] }}">
                <div class="mb-6 break-after-avoid">
                    <h2 id="seccion-{{ $seccion['clave'] }}" class="text-subtitulo font-semibold text-balance">{{ $seccion['titulo'] }} <span class="font-normal text-texto-atenuado tabular-nums">({{ count($seccion['indicadores']) }})</span></h2>
                    <p class="mt-1 text-tabla text-texto-atenuado text-pretty">{{ $seccion['descripcion'] }}</p>
                </div>

                <div class="flex flex-col gap-8">
                    @foreach ($seccion['indicadores'] as $indicador)
                        @include('livewire.acompanamientos.partials.indicador-resultado', ['indicador' => $indicador, 'tonos' => $tonos])
                    @endforeach
                </div>
            </x-card>
        @endforeach

        <x-card class="break-inside-avoid print:border-0 print:p-0" aria-labelledby="titulo-ciclo">
            <h2 id="titulo-ciclo" class="mb-2 text-subtitulo font-semibold text-balance">Ciclo sugerido de acompañamiento</h2>
            <p class="mb-4 text-tabla text-texto-atenuado text-pretty">{{ $orientaciones->fundamentoCiclo() }}</p>
            <ol class="flex flex-col gap-3">
                @foreach ($orientaciones->cicloSugerido() as $paso)
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-insignia bg-acento-sutil text-tabla font-semibold text-acento tabular-nums">{{ $loop->iteration }}</span>
                        <span class="pt-1 text-base text-pretty"><span class="sr-only">Paso {{ $loop->iteration }}: </span>{{ $paso }}</span>
                    </li>
                @endforeach
            </ol>
        </x-card>

        <x-card class="break-inside-avoid print:border-0 print:p-0" aria-labelledby="titulo-plan">
            <h2 id="titulo-plan" class="mb-2 text-subtitulo font-semibold text-balance">Plan de mejora acordado</h2>
            <p class="mb-4 text-tabla text-texto-atenuado text-pretty">{{ $orientaciones->planDeMejora() }}</p>
            <div role="region" aria-label="Desplazamiento horizontal: Plan de mejora acordado" tabindex="0" class="overflow-x-auto rounded-control border border-borde-campo">
                <table class="w-full min-w-3xl border-collapse text-left text-tabla print:min-w-0 [&_td]:h-16 [&_td]:border-l [&_td]:border-borde-campo [&_td:first-child]:border-l-0 [&_th]:border-l [&_th]:border-borde-campo [&_th:first-child]:border-l-0 [&_th]:bg-superficie-atenuada [&_th]:px-3 [&_th]:py-2 [&_th]:align-top [&_th]:font-semibold [&_tr+tr]:border-t [&_tr+tr]:border-borde-campo">
                    <caption class="sr-only">Plantilla para completar en la conversación reflexiva</caption>
                    <thead>
                        <tr>
                            <th scope="col">Descriptor (n.º y nombre)</th>
                            <th scope="col">Acción acordada (metodológica, procedimental o formativa)</th>
                            <th scope="col">Responsable y apoyo</th>
                            <th scope="col">Fecha de revisión</th>
                            <th scope="col">Evidencia que se mirará</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([1, 2] as $fila)
                            <tr>
                                <td><span class="sr-only">Fila {{ $fila }}, por completar</span></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</div>

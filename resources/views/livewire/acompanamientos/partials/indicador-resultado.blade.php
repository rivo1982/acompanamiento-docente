{{--
    Un indicador de la retroalimentación (ResultadoDeAcompanamiento::indicador): ámbito, nivel, descriptor observado,
    felicitación, retroalimentación automática y personal, nota de protocolo (descriptores 42 y 45) y, bajo Destacado,
    su ficha de apoyo pedagógico. Recibe $indicador y $tonos (clases por variante de nivel).
--}}
@php
    // Partes de la ficha, cada una con su color de la paleta de estados del brief y siempre con su nombre escrito.
    $partesDeLaFicha = [
        'metodologica' => ['titulo' => 'Acción metodológica', 'clases' => 'border-informacion bg-informacion-fondo', 'titulo_clases' => 'text-informacion'],
        'procedimental' => ['titulo' => 'Acción procedimental', 'clases' => 'border-aviso bg-aviso-fondo', 'titulo_clases' => 'text-aviso'],
        'formativa' => ['titulo' => 'Acción formativa', 'clases' => 'border-exito bg-exito-fondo', 'titulo_clases' => 'text-exito'],
    ];
@endphp
<article
    wire:key="indicador-{{ $indicador['id'] }}"
    aria-labelledby="indicador-{{ $indicador['id'] }}"
    class="flex flex-col gap-4 border-t border-borde pt-6 first:border-t-0 first:pt-0"
>
    <header class="flex flex-col gap-2 break-inside-avoid sm:flex-row sm:items-start sm:justify-between sm:gap-4">
        <div class="min-w-0">
            @if ($indicador['ambito'] !== null)
                <p class="text-nota font-semibold text-texto-atenuado text-pretty">{{ $indicador['ambito'] }}</p>
            @endif
            <h3 id="indicador-{{ $indicador['id'] }}" class="text-base font-semibold text-pretty">
                <span class="tabular-nums">{{ $indicador['numero'] }}.</span> {{ $indicador['nombre'] }}
            </h3>
        </div>
        <x-badge :variante="$indicador['variante']" class="self-start"><span class="sr-only">Nivel: </span>{{ $indicador['nivel'] }}</x-badge>
    </header>

    @if ($indicador['descriptor'] !== null)
        <blockquote class="break-inside-avoid rounded-control border p-3 text-base text-texto text-pretty {{ $tonos[$indicador['variante']] ?? $tonos['neutro'] }}">
            <p class="mb-1 text-nota font-semibold">Lo observado</p>
            <p class="text-texto">{{ $indicador['descriptor'] }}</p>
        </blockquote>
    @endif

    @if ($indicador['felicitacion'] !== null)
        <p class="break-inside-avoid rounded-control border border-exito bg-exito-fondo p-3 text-base font-semibold text-exito text-pretty">{{ $indicador['felicitacion'] }}</p>
    @endif

    <dl class="grid gap-4 text-tabla md:grid-cols-2">
        @if ($indicador['queSignifica'] !== null)
            <div class="break-inside-avoid">
                <dt class="font-semibold">Qué significa</dt>
                <dd class="mt-1 text-texto-atenuado text-pretty">{{ $indicador['queSignifica'] }}</dd>
            </div>
        @endif
        @if ($indicador['siguientePaso'] !== null)
            <div class="break-inside-avoid">
                <dt class="font-semibold">{{ $indicador['siguientePaso']['titulo'] }}</dt>
                <dd class="mt-1 text-texto-atenuado text-pretty">{{ $indicador['siguientePaso']['texto'] }}</dd>
            </div>
        @endif
        @if ($indicador['retroalimentacionPersonal'] !== null)
            <div class="break-inside-avoid md:col-span-2">
                <dt class="font-semibold">Retroalimentación del observador</dt>
                <dd class="mt-1 whitespace-pre-line text-texto text-pretty">{{ $indicador['retroalimentacionPersonal'] }}</dd>
            </div>
        @endif
        @if ($indicador['fuentes'] !== null)
            <div class="break-inside-avoid md:col-span-2">
                <dt class="font-semibold">Fuentes</dt>
                <dd class="mt-1 text-texto-atenuado text-pretty">{{ $indicador['fuentes'] }}</dd>
            </div>
        @endif
    </dl>

    @if ($indicador['notaProtocolo'] !== null)
        <p role="note" class="break-inside-avoid rounded-control border border-peligro bg-peligro-fondo p-3 text-tabla text-peligro text-pretty">
            <span class="font-semibold">Protocolo: </span>{{ $indicador['notaProtocolo'] }}
        </p>
    @endif

    @if ($indicador['ficha'] !== null)
        @php($ficha = $indicador['ficha'])
        <section aria-labelledby="ficha-{{ $indicador['id'] }}" class="flex flex-col gap-3 rounded-tarjeta border border-borde bg-superficie-atenuada p-4 print:bg-superficie">
            <h4 id="ficha-{{ $indicador['id'] }}" class="text-base font-semibold text-balance">Ficha de apoyo pedagógico</h4>

            <div class="break-inside-avoid rounded-control border border-acento bg-acento-sutil p-3">
                <h5 class="text-nota font-semibold text-acento">Foco de mejora</h5>
                <p class="mt-1 text-base text-texto text-pretty">{{ $ficha['foco'] }}</p>
            </div>

            <div class="grid gap-3 lg:grid-cols-3">
                @foreach ($partesDeLaFicha as $clave => $parte)
                    <div class="break-inside-avoid rounded-control border p-3 {{ $parte['clases'] }}">
                        <h5 class="text-nota font-semibold {{ $parte['titulo_clases'] }}">{{ $parte['titulo'] }}</h5>
                        <p class="mt-1 text-tabla text-texto text-pretty">{{ $ficha[$clave] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="break-inside-avoid rounded-control border border-neutro bg-neutro-fondo p-3">
                    <h5 class="text-nota font-semibold text-neutro">Evidencia de avance</h5>
                    <p class="mt-1 text-tabla text-texto text-pretty">{{ $ficha['evidencia'] }}</p>
                </div>
                <div class="break-inside-avoid rounded-control border border-borde-campo bg-superficie p-3">
                    <h5 class="text-nota font-semibold text-texto-atenuado">Referencias y recursos</h5>
                    <p class="mt-1 text-tabla text-texto-atenuado text-pretty">{{ $ficha['referencias'] }}</p>
                </div>
            </div>
        </section>
    @endif
</article>

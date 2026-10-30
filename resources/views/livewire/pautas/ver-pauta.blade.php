{{--
    Una pauta: significado de los niveles y cada indicador con sus cuatro descriptores, por ámbito.
    Lista para imprimir o guardar como PDF (window.print()): con `print:` se ocultan los botones, las tarjetas pierden
    borde y relleno, y cada indicador evita partirse entre páginas. La barra lateral, la superior y el enlace de salto
    son del layout (copia de Vinkured, sin variantes de impresión): la regla de abajo los oculta solo en esta vista, sin
    tocar el layout. Al integrar, conviene que Vinkured agregue `print:hidden` a esos elementos del layout.
--}}
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

    {{-- Línea de documento: solo en la versión impresa, para saber de qué establecimiento y pauta es cada hoja. --}}
    <p class="mb-4 hidden text-nota text-texto-atenuado print:block">{{ $establecimiento !== null ? $establecimiento.' · ' : '' }}Pauta: {{ $pauta->nombre }}</p>

    <x-page-header
        :titulo="$pauta->nombre"
        descripcion="{{ $totalIndicadores === 1 ? '1 indicador' : \App\Support\FormatoDelPanel::entero($totalIndicadores).' indicadores' }}, cada uno con sus cuatro niveles de logro."
    >
        <x-slot:acciones>
            <x-button :href="route('pautas.index')" variante="secundario" class="print:hidden">Volver a pautas</x-button>
            @if ($puedeEditar)
                <x-button :href="route('pautas.edit', $pauta->id)" variante="secundario" class="print:hidden">Editar</x-button>
            @endif
            @if ($puedeExportar)
                <x-button x-data x-on:click="window.print()" class="print:hidden">Imprimir o guardar PDF</x-button>
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-card class="mb-6 break-inside-avoid print:border-0 print:p-0" aria-labelledby="titulo-niveles">
        <h2 id="titulo-niveles" class="mb-4 text-subtitulo font-semibold text-balance">Significado de los niveles</h2>

        <div class="overflow-hidden rounded-control border border-borde print:border-borde-campo">
            <table class="w-full border-collapse text-left text-tabla [&_td]:px-3 [&_td]:py-2 [&_td]:align-top [&_th]:px-3 [&_th]:py-2 [&_th]:align-top [&_th]:font-semibold [&_tr+tr]:border-t [&_tr+tr]:border-borde print:[&_tr+tr]:border-borde-campo">
                <caption class="sr-only">Significado de los cuatro niveles de logro, de menor a mayor</caption>
                <thead class="bg-superficie-atenuada">
                    <tr>
                        <th scope="col" class="w-1/3 sm:w-1/4">Nivel</th>
                        <th scope="col">Significado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($niveles as $nivel)
                        <tr>
                            <th scope="row">{{ $nivel['etiqueta'] }}</th>
                            <td class="text-pretty">{{ $nivel['significado'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card class="print:border-0 print:p-0" aria-labelledby="titulo-indicadores">
        <h2 id="titulo-indicadores" class="mb-4 text-subtitulo font-semibold text-balance break-after-avoid">Indicadores ({{ \App\Support\FormatoDelPanel::entero($totalIndicadores) }})</h2>

        <div class="flex flex-col gap-8">
            @foreach ($ambitos as $ambito)
                <section wire:key="ambito-{{ $ambito['id'] }}" aria-labelledby="ambito-{{ $ambito['id'] }}" class="flex flex-col gap-6">
                    <div class="break-after-avoid">
                        <h3 id="ambito-{{ $ambito['id'] }}" class="text-base font-semibold text-balance">{{ $ambito['nombre'] }}</h3>
                        <p class="text-tabla text-texto-atenuado text-pretty">Pauta base: {{ $ambito['pautaBase'] }}</p>
                    </div>

                    @foreach ($ambito['indicadores'] as $indicador)
                        <article wire:key="indicador-{{ $indicador['id'] }}" aria-labelledby="indicador-{{ $indicador['id'] }}" class="flex flex-col gap-2 break-inside-avoid">
                            <h4 id="indicador-{{ $indicador['id'] }}" class="text-base font-semibold text-pretty">{{ $indicador['numero'] }}. {{ $indicador['nombre'] }}</h4>

                            <div class="overflow-hidden rounded-control border border-borde print:border-borde-campo">
                                <table class="w-full border-collapse text-left text-tabla [&_td]:px-3 [&_td]:py-2 [&_td]:align-top [&_th]:px-3 [&_th]:py-2 [&_th]:align-top [&_th]:font-semibold [&_tr+tr]:border-t [&_tr+tr]:border-borde print:[&_tr+tr]:border-borde-campo">
                                    <caption class="sr-only">Descriptores por nivel del indicador {{ $indicador['numero'] }}</caption>
                                    <thead class="sr-only">
                                        <tr>
                                            <th scope="col">Nivel</th>
                                            <th scope="col">Descriptor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($indicador['niveles'] as $nivel)
                                            <tr>
                                                <th scope="row" class="w-1/3 bg-superficie-atenuada sm:w-1/4">{{ $nivel['etiqueta'] }}</th>
                                                <td class="text-pretty">{{ $nivel['descriptor'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endforeach
                </section>
            @endforeach
        </div>
    </x-card>
</div>

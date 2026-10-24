{{-- @host-owned Copied from Vinkured resources/views/components/layouts/app.blade.php — do not modify here; replace with the host class at integration. --}}
@props([
    'titulo' => null,
    'navegacion' => null,
    'ancho' => 'completo',
])

@php
    // Cada entrada aparece solo a quien puede verla, según `viewAny` de su política: Alertas con la gate `ver-alertas`
    // (Coordinador, directivo o profesor jefe) y Avisos con `ver-panel-convivencia` (Coordinador o directivo).
    $navegacion ??= array_values(array_filter([
        ['etiqueta' => 'Panel', 'href' => route('panel'), 'ariaCurrent' => request()->routeIs('panel') ? 'page' : null],
        // Cada ítem trae `ariaCurrent`: 'page' (la página actual), 'true' (la sección actual desde un detalle) o null.
        // El estilo "activo" sale de que no sea null. Cualquier otro valor lanza (ver `App\Support\AriaCurrent`).
        auth()->user()?->can('viewAny', \App\Models\Alerta::class)
            ? ['etiqueta' => 'Alertas', 'href' => route('alertas.index'), 'ariaCurrent' => request()->routeIs('alertas.show') ? 'true' : (request()->routeIs('alertas.index') ? 'page' : null)]
            : null,
        auth()->user()?->can('viewAny', \App\Models\Aviso::class)
            ? ['etiqueta' => 'Avisos', 'href' => route('avisos.index'), 'ariaCurrent' => request()->routeIs('avisos.show') ? 'true' : (request()->routeIs('avisos.index') ? 'page' : null)]
            : null,
    ]));
    // Se valida una vez por ítem, antes de pintar.
    $navegacion = array_map(fn (array $item): array => [...$item, 'ariaCurrent' => \App\Support\AriaCurrent::de($item['ariaCurrent'] ?? null)], $navegacion);
    $anchoContenido = $ancho === 'formulario' ? 'max-w-3xl' : 'w-full';
@endphp
<!DOCTYPE html>
<html lang="es-CL">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f8fafc">
        <title>{{ $titulo ? $titulo.' - VINKURED' : 'VINKURED' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-dvh">
        <a
            href="#contenido"
            class="sr-only rounded-control border border-acento bg-superficie px-4 py-2 font-semibold text-acento focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-(--z-aviso)"
        >Saltar al contenido</a>

        <div
            class="flex min-h-dvh"
            x-data="{
                cajon: false,
                movil: window.matchMedia('(max-width: 1023.98px)').matches,
                colapsada: $persist(false).as('vinkured-barra-colapsada'),
                get comprimida() { return this.colapsada && ! this.movil },
                cerrar() {
                    this.cajon = false
                    this.$nextTick(() => this.$refs.hamburguesa.focus())
                },
                init() {
                    const consulta = window.matchMedia('(max-width: 1023.98px)')
                    consulta.addEventListener('change', (e) => {
                        this.movil = e.matches
                        if (! e.matches) this.cajon = false
                    })
                },
            }"
            x-on:keydown.escape.window="if (cajon) cerrar()"
        >
            <div
                x-show="cajon"
                x-cloak
                x-transition.opacity.duration.150ms
                x-on:click="cerrar()"
                class="fixed inset-0 z-(--z-modal) bg-velo lg:hidden"
                aria-hidden="true"
            ></div>

            <aside
                id="barra-lateral"
                class="fixed inset-y-0 left-0 z-(--z-modal) flex w-(--ancho-barra-lateral) flex-col border-r border-borde bg-superficie transition-transform duration-150 ease-out motion-reduce:transition-none lg:sticky lg:top-0 lg:z-(--z-fijo) lg:h-dvh lg:shrink-0"
                x-bind:class="[
                    movil && ! cajon ? '-translate-x-full' : '',
                    comprimida ? 'lg:w-(--ancho-barra-lateral-colapsada)' : 'lg:w-(--ancho-barra-lateral)',
                ]"
                data-cloak-movil
                x-init="$el.removeAttribute('data-cloak-movil')"
                aria-label="Menú de navegación"
                x-bind:role="movil && cajon ? 'dialog' : null"
                x-bind:aria-modal="(movil && cajon) ? 'true' : null"
                x-bind:inert="movil && ! cajon"
                x-trap.noscroll="movil && cajon"
            >
                <div class="flex h-(--alto-barra-superior) shrink-0 items-center justify-between gap-2 border-b border-borde px-3">
                    <a href="{{ route('panel') }}" translate="no" aria-label="VINKURED, ir al inicio" class="rounded-control font-semibold text-acento" x-show="! comprimida">VINKURED</a>
                    <button
                        type="button"
                        class="inline-flex size-(--alto-control) items-center justify-center rounded-control text-texto hover:bg-superficie-atenuada lg:hidden"
                        x-on:click="cerrar()"
                    >
                        <span class="sr-only">Cerrar menú de navegación</span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                    <button
                        type="button"
                        class="hidden size-(--alto-control) items-center justify-center rounded-control text-texto hover:bg-superficie-atenuada lg:inline-flex"
                        aria-controls="navegacion-principal"
                        x-bind:aria-expanded="(! comprimida).toString()"
                        x-on:click="colapsada = ! colapsada"
                    >
                        <span class="sr-only" x-text="comprimida ? 'Expandir barra lateral' : 'Contraer barra lateral'">Contraer barra lateral</span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6 motion-safe:transition-transform motion-safe:duration-150" x-bind:class="comprimida && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                    </button>
                </div>

                <nav id="navegacion-principal" aria-label="Navegación principal" class="flex-1 overflow-y-auto overscroll-contain p-3">
                    <ul class="flex flex-col gap-1">
                        @foreach ($navegacion as $item)
                            <li>
                                <a
                                    href="{{ $item['href'] }}"
                                    title="{{ $item['etiqueta'] }}"
                                    @if ($item['ariaCurrent'] !== null) aria-current="{{ $item['ariaCurrent'] }}" @endif
                                    @class([
                                        'flex min-h-(--alto-control) items-center gap-3 rounded-control px-3 text-tabla',
                                        'bg-acento-sutil font-semibold text-acento' => $item['ariaCurrent'] !== null,
                                        'text-texto hover:bg-superficie-atenuada' => $item['ariaCurrent'] === null,
                                    ])
                                >
                                    <span aria-hidden="true" x-cloak x-show="comprimida" class="w-full text-center font-semibold">{{ mb_strtoupper(mb_substr($item['etiqueta'], 0, 1)).mb_strtolower(mb_substr($item['etiqueta'], 1, 1)) }}</span>
                                    <span x-bind:class="comprimida && 'sr-only'">{{ $item['etiqueta'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-(--z-fijo) flex h-(--alto-barra-superior) shrink-0 items-center gap-3 border-b border-borde bg-superficie px-4 md:px-6"
                    x-bind:inert="movil && cajon"
                >
                    <button
                        type="button"
                        class="inline-flex size-(--alto-control) items-center justify-center rounded-control text-texto hover:bg-superficie-atenuada lg:hidden"
                        aria-controls="barra-lateral"
                        x-ref="hamburguesa"
                        x-bind:aria-expanded="cajon.toString()"
                        x-on:click="cajon = true"
                    >
                        <span class="sr-only">Abrir menú de navegación</span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    @auth
                        <livewire:selector-de-establecimiento />

                        <div class="ml-auto flex min-w-0 items-center gap-3">
                            <span class="hidden truncate text-tabla text-texto-atenuado sm:inline">{{ auth()->user()->correo }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-button type="submit" variante="secundario">Cerrar sesión</x-button>
                            </form>
                        </div>
                    @endauth
                </header>

                <main id="contenido" tabindex="-1" class="flex-1 p-4 focus:outline-none md:p-6 lg:p-8" x-bind:inert="movil && cajon">
                    <div class="{{ $anchoContenido }}">
                        {{-- Región de avisos de estado (p. ej. cambio de establecimiento): Éxito, siempre con texto. --}}
                        <div role="status">
                            @if (session('estado'))
                                <p class="mb-6 rounded-control border border-exito bg-exito-fondo p-3 text-tabla text-exito">{{ session('estado') }}</p>
                            @endif
                        </div>
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>

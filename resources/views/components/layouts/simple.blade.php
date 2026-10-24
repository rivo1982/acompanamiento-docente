{{-- @host-owned Copied from Vinkured resources/views/components/layouts/simple.blade.php — do not modify here; replace with the host class at integration. --}}
@props([
    'titulo' => null,
])
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

        <header class="flex h-(--alto-barra-superior) items-center justify-between gap-3 border-b border-borde bg-superficie px-4 md:px-6">
            <span translate="no" class="font-semibold text-acento">VINKURED</span>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button type="submit" variante="secundario">Cerrar sesión</x-button>
                </form>
            @endauth
        </header>

        <main id="contenido" tabindex="-1" class="flex justify-center p-4 focus:outline-none md:p-6 lg:p-8">
            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </main>

        @livewireScripts
    </body>
</html>

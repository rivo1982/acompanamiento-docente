{{-- Inicio provisional del módulo de acompañamiento docente (no es una copia de Vinkured). En Vinkured estos accesos
     viven en su menú; al integrar, esta vista se quita junto con la ruta `panel`. --}}
<x-layouts.simple titulo="Inicio">
    <x-page-header titulo="Acompañamiento docente" descripcion="Elija la pantalla con la que quiere trabajar." />

    @canany(['gestionar-observadores', 'invitar-funcionarios'])
        <nav aria-label="Pantallas del módulo" class="mt-6 grid gap-4 md:grid-cols-2">
            @can('gestionar-observadores')
                <x-card titulo="Observadores">
                    <p class="mb-4 text-texto-atenuado text-pretty">Quiénes observan clases y a qué docentes acompaña cada uno.</p>
                    <x-button :href="route('observadores.index')">Ir a Observadores</x-button>
                </x-card>
            @endcan

            @can('invitar-funcionarios')
                <x-card titulo="Funcionarios">
                    <p class="mb-4 text-texto-atenuado text-pretty">Invitar personas, revisar invitaciones y cargar planillas.</p>
                    <div class="flex flex-wrap gap-3">
                        <x-button :href="route('invitaciones.index')">Invitaciones pendientes</x-button>
                        <x-button :href="route('invitaciones.create')" variante="secundario">Invitar funcionario</x-button>
                        <x-button :href="route('importaciones.index')" variante="secundario">Carga masiva</x-button>
                    </div>
                </x-card>
            @endcan
        </nav>
    @else
        <p class="mt-6 text-texto-atenuado">Aún no hay pantallas disponibles para su perfil.</p>
    @endcanany
</x-layouts.simple>

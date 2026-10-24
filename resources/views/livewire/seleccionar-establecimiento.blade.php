{{-- @host-owned Copied from Vinkured resources/views/livewire/seleccionar-establecimiento.blade.php — do not modify here; replace with the host class at integration. --}}
<div>
    <x-page-header titulo="Elegir establecimiento" descripcion="Esta cuenta tiene acceso a más de un establecimiento. Elegir con cuál trabajar." />

    <x-card>
        {{-- Entrar es explícito: el select no envía al cambiar, así que las flechas del teclado no cambian el establecimiento.
             Etiqueta y select replican las clases de `campo` y del selector de la barra superior (campo solo pinta inputs). --}}
        <form wire:submit="entrar" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <label for="establecimiento" class="text-tabla font-semibold">Establecimiento</label>
                <select
                    id="establecimiento"
                    wire:model="seleccionado"
                    class="min-h-(--alto-control) w-full min-w-0 max-w-full truncate rounded-control border border-borde-campo bg-superficie px-3 text-base text-texto"
                >
                    @foreach ($membresias as $membresia)
                        <option wire:key="establecimiento-{{ $membresia->establecimiento_id }}" value="{{ $membresia->establecimiento_id }}">{{ $membresia->establecimiento->nombre }} · RBD {{ $membresia->establecimiento->rbd }}-{{ $membresia->establecimiento->dv_rbd }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="entrar">Entrar</x-button>
            </div>
        </form>
    </x-card>
</div>

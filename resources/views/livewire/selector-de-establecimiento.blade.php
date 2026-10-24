{{-- @host-owned Copied from Vinkured resources/views/livewire/selector-de-establecimiento.blade.php — do not modify here; replace with the host class at integration. --}}
<div class="min-w-0">
    @if ($membresias->count() > 1)
        {{-- Cambiar de establecimiento es explícito: las flechas del select no cambian el contexto. --}}
        <form wire:submit="cambiar" class="flex min-w-0 items-center gap-2">
            <label for="selector-establecimiento" class="sr-only">Establecimiento</label>
            <select
                id="selector-establecimiento"
                wire:model="seleccionado"
                class="min-h-(--alto-control) w-full min-w-0 max-w-full truncate rounded-control border border-borde-campo bg-superficie px-3 text-base text-texto"
            >
                @foreach ($membresias as $membresia)
                    <option value="{{ $membresia->establecimiento_id }}">{{ $membresia->establecimiento->nombre }}</option>
                @endforeach
            </select>
            <x-button type="submit" variante="secundario" wire:loading.attr="disabled" wire:target="cambiar">Cambiar</x-button>
        </form>
    @else
        <p class="truncate text-tabla font-semibold" title="{{ $actualNombre }}">
            <span class="sr-only">Establecimiento: </span>{{ $actualNombre ?? 'Sin establecimiento' }}
        </p>
    @endif
</div>

{{-- Nueva pauta / Editar pauta: nombre e indicadores del catálogo, agrupados por pauta base y ámbito. --}}
<div>
    <x-page-header
        :titulo="$titulo"
        descripcion="Escribir un nombre y marcar los indicadores del catálogo. En la pauta quedan en el orden del catálogo."
    >
        <x-slot:acciones>
            <x-button :href="$pautaId === null ? route('pautas.index') : route('pautas.show', $pautaId)" variante="secundario">{{ $pautaId === null ? 'Volver a pautas' : 'Volver a la pauta' }}</x-button>
        </x-slot:acciones>
    </x-page-header>

    <x-card>
        <form method="post" wire:submit="guardar" wire:loading.attr="aria-busy" wire:target="guardar" class="flex flex-col gap-6" novalidate>
            <x-campo
                id="nombre"
                etiqueta="Nombre"
                :obligatorio="true"
                :error="$errors->first('nombre')"
                wire:model="nombre"
                name="nombre"
                autocomplete="off"
                maxlength="{{ \App\Actions\GuardarPauta::NOMBRE_MAXIMO }}"
            />

            {{-- Grupo enfocable para llevar el foco al error de indicadores (como el campo con error en los formularios). --}}
            <fieldset
                id="indicadores"
                tabindex="-1"
                aria-describedby="indicadores-ayuda{{ $errors->has('indicadores') ? ' indicadores-error' : '' }}"
                class="flex flex-col gap-3 focus:outline-none"
            >
                <legend class="text-tabla font-semibold">Indicadores (obligatorio)</legend>
                <p id="indicadores-ayuda" class="text-tabla text-texto-atenuado text-pretty">Abrir cada pauta base para ver sus ámbitos e indicadores.</p>
                <p class="text-tabla font-semibold tabular-nums" aria-live="polite" x-data x-text="(n => n === 1 ? '1 indicador elegido' : n + ' indicadores elegidos')($wire.indicadores.length)">{{ $elegidos === 1 ? '1 indicador elegido' : \App\Support\FormatoDelPanel::entero($elegidos).' indicadores elegidos' }}</p>
                @if ($errors->has('indicadores'))
                    <p id="indicadores-error" class="text-tabla text-peligro">{{ $errors->first('indicadores') }}</p>
                @endif

                <div class="flex flex-col gap-3">
                    @foreach ($bases as $base)
                        {{-- wire:ignore.self: Livewire no cierra ni abre la sección al actualizar; manda quien la usa. --}}
                        <details
                            wire:key="base-{{ $base['id'] }}"
                            wire:ignore.self
                            @if ($base['marcados'] > 0) open @endif
                            x-data="{ ids: @js($base['ids']) }"
                            class="rounded-tarjeta border border-borde"
                        >
                            <summary class="flex min-h-(--alto-control) cursor-pointer flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-tarjeta px-4 py-2 hover:bg-superficie-atenuada">
                                <span class="font-semibold text-pretty">{{ $base['nombre'] }}</span>
                                <span class="text-tabla text-texto-atenuado tabular-nums"><span x-text="$wire.indicadores.filter((id) => ids.includes(String(id))).length">{{ $base['marcados'] }}</span> de {{ count($base['ids']) }} elegidos</span>
                            </summary>

                            <div class="flex flex-col gap-4 border-t border-borde p-4">
                                <div class="flex flex-wrap gap-3">
                                    <x-button variante="secundario" wire:click="seleccionarTodos({{ $base['id'] }})" wire:loading.attr="disabled" wire:target="seleccionarTodos, quitarTodos">Seleccionar todos<span class="sr-only"> los indicadores de {{ $base['nombre'] }}</span></x-button>
                                    <x-button variante="secundario" wire:click="quitarTodos({{ $base['id'] }})" wire:loading.attr="disabled" wire:target="seleccionarTodos, quitarTodos">Quitar todos<span class="sr-only"> los indicadores de {{ $base['nombre'] }}</span></x-button>
                                </div>

                                @foreach ($base['ambitos'] as $ambito)
                                    <fieldset wire:key="ambito-{{ $ambito['id'] }}">
                                        <legend class="mb-2 text-tabla font-semibold text-pretty">{{ $ambito['nombre'] }}</legend>
                                        <ul class="flex flex-col">
                                            @foreach ($ambito['indicadores'] as $indicador)
                                                <li wire:key="indicador-{{ $indicador['id'] }}">
                                                    <label class="flex min-h-(--alto-control) cursor-pointer items-start gap-3 py-2 text-base">
                                                        <input type="checkbox" wire:model="indicadores" value="{{ $indicador['id'] }}" class="mt-1 size-4 shrink-0 accent-acento">
                                                        <span class="min-w-0 break-words text-pretty">{{ $indicador['numero'] }}. {{ $indicador['nombre'] }}</span>
                                                    </label>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </fieldset>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Guardar pauta</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </x-button>
            </div>
        </form>
    </x-card>
</div>

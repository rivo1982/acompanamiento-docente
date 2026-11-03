{{--
    Formulario de observación: un nivel por indicador (se guarda al elegirlo) y retroalimentación personal opcional
    (se guarda al salir del campo). Pensado para usarse en la sala con teléfono o tableta: opciones grandes, una
    columna en móvil y una barra fija abajo con el avance y el botón de finalizar.
--}}
<div>
    <x-page-header
        titulo="Acompañamiento de {{ $docente }}"
        descripcion="Elegir un nivel por indicador. Cada cambio se guarda solo."
    />

    <x-card class="mb-6" aria-labelledby="titulo-clase">
        <h2 id="titulo-clase" class="sr-only">Datos de la clase</h2>
        <dl class="grid gap-4 text-tabla sm:grid-cols-2 lg:grid-cols-4">
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
                <dt class="font-semibold">Inicio</dt>
                <dd class="tabular-nums text-texto-atenuado">{{ $iniciadoEn }}</dd>
            </div>
        </dl>
    </x-card>

    <x-card class="mb-6">
        <details>
            <summary class="flex min-h-(--alto-control) cursor-pointer items-center text-subtitulo font-semibold">Significado de los niveles</summary>
            <div class="mt-4 overflow-hidden rounded-control border border-borde">
                <table class="w-full border-collapse text-left text-tabla [&_td]:px-3 [&_td]:py-2 [&_td]:align-top [&_th]:px-3 [&_th]:py-2 [&_th]:align-top [&_th]:font-semibold [&_tr+tr]:border-t [&_tr+tr]:border-borde">
                    <caption class="sr-only">Significado de los cuatro niveles de logro, de menor a mayor</caption>
                    <thead class="bg-superficie-atenuada">
                        <tr>
                            <th scope="col" class="w-1/3 sm:w-1/4">Nivel</th>
                            <th scope="col">Significado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($significados as $significado)
                            <tr>
                                <th scope="row">{{ $significado['etiqueta'] }}</th>
                                <td class="text-pretty">{{ $significado['significado'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </x-card>

    <div class="flex flex-col gap-6">
        @foreach ($ambitos as $posicion => $ambito)
            <x-card wire:key="ambito-{{ $posicion }}" aria-labelledby="ambito-{{ $posicion }}">
                <h2 id="ambito-{{ $posicion }}" class="mb-4 text-subtitulo font-semibold text-balance">{{ $ambito['nombre'] }}</h2>

                <div class="flex flex-col gap-8">
                    @foreach ($ambito['indicadores'] as $indicador)
                        @php($errorNivel = $errors->first('niveles.'.$indicador['id']))
                        <fieldset
                            wire:key="indicador-{{ $indicador['id'] }}"
                            class="flex flex-col gap-4 border-t border-borde pt-6 first:border-t-0 first:pt-0"
                            @if ($errorNivel) aria-describedby="nivel-{{ $indicador['id'] }}-error" @endif
                        >
                            <legend class="float-left mb-4 w-full text-base font-semibold text-pretty">
                                <span class="tabular-nums">{{ $indicador['numero'] }}.</span> {{ $indicador['nombre'] }}
                            </legend>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($indicador['niveles'] as $nivel)
                                    <label
                                        for="nivel-{{ $indicador['id'] }}-{{ $nivel['valor'] }}"
                                        class="flex min-h-(--alto-control) cursor-pointer gap-3 rounded-control border border-borde-campo bg-superficie p-3 hover:bg-superficie-atenuada has-checked:border-acento has-checked:bg-acento-sutil"
                                    >
                                        <input
                                            type="radio"
                                            id="nivel-{{ $indicador['id'] }}-{{ $nivel['valor'] }}"
                                            name="nivel-{{ $indicador['id'] }}"
                                            value="{{ $nivel['valor'] }}"
                                            wire:model.live="niveles.{{ $indicador['id'] }}"
                                            class="mt-1 size-5 shrink-0 accent-acento"
                                        >
                                        <span class="flex min-w-0 flex-col gap-1">
                                            <span class="text-base font-semibold">{{ $nivel['etiqueta'] }}</span>
                                            @if ($nivel['descriptor'] !== '')
                                                <span class="text-tabla text-texto-atenuado text-pretty break-words">{{ $nivel['descriptor'] }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            @if ($errorNivel)
                                <p id="nivel-{{ $indicador['id'] }}-error" class="text-tabla text-peligro">{{ $errorNivel }}</p>
                            @endif

                            <x-campo
                                id="retro-{{ $indicador['id'] }}"
                                elemento="textarea"
                                etiqueta="Retroalimentación personal"
                                ayuda="Opcional. Hasta {{ \App\Support\FormatoDelPanel::entero($maximo) }} caracteres; se guarda al salir del campo."
                                :error="$errors->first('retroalimentaciones.'.$indicador['id'])"
                                rows="3"
                                maxlength="{{ $maximo }}"
                                wire:model.blur="retroalimentaciones.{{ $indicador['id'] }}"
                            />
                        </fieldset>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>

    @error('respuestas')
        <div id="error-respuestas" tabindex="-1" role="alert" class="mt-6 rounded-control border border-peligro bg-peligro-fondo p-3 text-tabla text-peligro text-pretty focus:outline-none">{{ $message }}</div>
    @enderror

    {{-- Barra fija abajo: avance, estado de guardado (aria-live) y finalizar, al alcance del pulgar. --}}
    <div class="sticky bottom-0 z-(--z-fijo) mt-6 rounded-tarjeta border border-borde bg-superficie p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-1">
                <p class="text-base font-semibold tabular-nums">{{ \App\Support\FormatoDelPanel::entero($conNivel) }} de {{ \App\Support\FormatoDelPanel::entero($total) }} indicadores con nivel</p>
                <p class="text-tabla text-texto-atenuado" aria-live="polite" aria-atomic="true">
                    <span wire:loading wire:target="niveles, retroalimentaciones">Guardando…</span>
                    <span wire:loading.remove wire:target="niveles, retroalimentaciones">{{ $estadoDeGuardado }}</span>
                </p>
            </div>
            <x-button wire:click="confirmarFinalizacion" wire:loading.attr="disabled" wire:target="confirmarFinalizacion">Finalizar acompañamiento</x-button>
        </div>
    </div>

    <x-modal id="confirmar-finalizacion" titulo="¿Finalizar el acompañamiento de {{ $docente }}?" abierto="$wire.confirmando" cerrar="$wire.cancelar()" descrito-por="confirmar-finalizacion-aviso">
        <p id="confirmar-finalizacion-aviso" class="text-tabla text-texto-atenuado text-pretty">Al finalizar, se avisa por correo a {{ $docente }} para que vea el resultado, y ya no se pueden cambiar los niveles ni la retroalimentación.</p>

        <x-slot:acciones>
            <x-button variante="secundario" wire:click="cancelar" autofocus>Cancelar</x-button>
            <x-button wire:click="finalizar" wire:loading.attr="disabled" wire:target="finalizar">
                <span wire:loading.remove wire:target="finalizar">Finalizar acompañamiento</span>
                <span wire:loading wire:target="finalizar">Finalizando…</span>
            </x-button>
        </x-slot:acciones>
    </x-modal>
</div>

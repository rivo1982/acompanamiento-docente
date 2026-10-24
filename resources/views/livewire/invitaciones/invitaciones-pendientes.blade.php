{{-- Invitaciones pendientes: membresías `invitada` de este establecimiento, con reenviar y anular. --}}
<div>
    <x-page-header
        titulo="Invitaciones pendientes"
        descripcion="Personas invitadas que aún no activan su acceso a este establecimiento."
    >
        <x-slot:acciones>
            <x-button :href="route('invitaciones.create')">Invitar funcionario</x-button>
        </x-slot:acciones>
    </x-page-header>

    {{--
        Mensaje de la última acción, como en "Aceptar invitación" (C4.1): un solo mecanismo, el rol del propio mensaje
        (Éxito como estado; aviso como alerta) más el foco que le da el servidor. Sin región aria-live envolvente, que lo
        anunciaría dos veces.
    --}}
    @if ($mensaje !== null)
        <div
            id="mensaje-invitaciones"
            tabindex="-1"
            role="{{ $tipoDeMensaje === \App\Livewire\Invitaciones\InvitacionesPendientes::EXITO ? 'status' : 'alert' }}"
            @class([
                'mb-6 rounded-control border p-3 text-tabla text-pretty focus:outline-none',
                'border-exito bg-exito-fondo text-exito' => $tipoDeMensaje === \App\Livewire\Invitaciones\InvitacionesPendientes::EXITO,
                'border-aviso bg-aviso-fondo text-aviso' => $tipoDeMensaje === \App\Livewire\Invitaciones\InvitacionesPendientes::AVISO,
            ])
        >{{ $mensaje }}</div>
    @endif

    <x-card aria-labelledby="titulo-invitaciones">
        <h2 id="titulo-invitaciones" tabindex="-1" class="mb-4 text-subtitulo font-semibold text-balance focus:outline-none">Invitaciones por activar ({{ \App\Support\FormatoDelPanel::entero($paginador->total()) }})</h2>

        {{--
            Sin región de estado para el total: el total no cambia al paginar ni con reenviar/anular (la invitación
            queda en la lista), así que una región viva solo repetiría la cantidad junto al mensaje de la acción. Al
            cambiar de página la paginación lleva el foco a este título, que ya dice la cantidad: se anuncia una vez.
        --}}

        @if ($filas === [])
            <div class="flex flex-col items-start gap-4">
                <p class="text-base text-texto-atenuado text-pretty">No hay invitaciones pendientes.</p>
                <x-button :href="route('invitaciones.create')" variante="secundario">Invitar funcionario</x-button>
            </div>
        @else
            {{-- Aviso de Reenviar (decisión del usuario 2026-10-24): una vez sobre la tabla, asociado a cada botón. --}}
            <p id="aviso-reenviar" class="mb-4 text-tabla text-texto-atenuado text-pretty">Se enviará un nuevo enlace; el anterior dejará de funcionar.</p>

            <x-tabla titulo="Invitaciones por activar">
                <x-slot:encabezado>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Función</th>
                        <th scope="col">Invitada el</th>
                        <th scope="col">Situación</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </x-slot:encabezado>

                @foreach ($filas as $fila)
                    <tr wire:key="invitacion-{{ $fila['id'] }}">
                        <td class="font-semibold">{{ $fila['nombre'] }}</td>
                        <td class="break-all">{{ $fila['correo'] }}</td>
                        <td>{{ $fila['funcion'] }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ \App\Support\FormatoDelPanel::fecha($fila['invitada']) }}</td>
                        <td><x-badge :variante="$fila['situacion']['variante']">{{ $fila['situacion']['texto'] }}</x-badge></td>
                        <td>
                            <div class="flex flex-wrap gap-2 py-1">
                                <x-button
                                    variante="secundario"
                                    wire:click="reenviar({{ $fila['id'] }})"
                                    aria-describedby="aviso-reenviar"
                                    wire:loading.attr="disabled"
                                    wire:target="reenviar({{ $fila['id'] }})"
                                >
                                    <span wire:loading.remove wire:target="reenviar({{ $fila['id'] }})">Reenviar<span class="sr-only"> invitación de {{ $fila['nombre'] }}</span></span>
                                    <span wire:loading wire:target="reenviar({{ $fila['id'] }})">Reenviando…</span>
                                </x-button>

                                @if ($fila['anulable'])
                                    <x-button
                                        variante="peligro"
                                        wire:click="confirmarAnulacion({{ $fila['id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="confirmarAnulacion({{ $fila['id'] }})"
                                    >Anular<span class="sr-only"> invitación de {{ $fila['nombre'] }}</span></x-button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        @endif

        <x-paginacion :paginador="$paginador" foco="titulo-invitaciones" />
    </x-card>

    <x-modal id="confirmar-anulacion" titulo="{{ $nombreAnulando !== null ? '¿Anular la invitación de '.$nombreAnulando.'?' : '¿Anular la invitación?' }}" abierto="$wire.anulando !== null" cerrar="$wire.cancelar()" descrito-por="confirmar-anulacion-aviso">
        <p id="confirmar-anulacion-aviso" class="text-tabla text-texto-atenuado text-pretty">El enlace enviado deja de servir. La invitación queda en la lista y se puede reenviar después.</p>

        <x-slot:acciones>
            <x-button variante="secundario" wire:click="cancelar" autofocus>Cancelar</x-button>
            <x-button variante="peligro" wire:click="anular" wire:loading.attr="disabled" wire:target="anular">
                <span wire:loading.remove wire:target="anular">Anular invitación</span>
                <span wire:loading wire:target="anular">Anulando…</span>
            </x-button>
        </x-slot:acciones>
    </x-modal>
</div>

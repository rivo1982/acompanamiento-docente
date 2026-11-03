{{-- Tabla de acompañamientos de "Mis acompañamientos" (en curso o finalizados). Recibe $filas, $titulo, $columnaFecha y $deTodos. --}}
<x-tabla :titulo="$titulo">
    <x-slot:encabezado>
        <tr>
            <th scope="col">Docente</th>
            @if ($deTodos)
                <th scope="col">Observador</th>
            @endif
            <th scope="col">Pauta</th>
            <th scope="col">Curso y asignatura</th>
            <th scope="col">{{ $columnaFecha }}</th>
            <th scope="col">Estado</th>
            <th scope="col">Acciones</th>
        </tr>
    </x-slot:encabezado>

    @foreach ($filas as $fila)
        <tr wire:key="acompanamiento-{{ $fila['id'] }}">
            <td class="font-semibold break-words">{{ $fila['docente'] }}</td>
            @if ($deTodos)
                <td class="break-words">{{ $fila['observador'] }}</td>
            @endif
            <td class="break-words">{{ $fila['pauta'] }}</td>
            <td class="break-words">{{ $fila['curso'] }} · {{ $fila['asignatura'] }}</td>
            <td class="whitespace-nowrap tabular-nums">{{ \App\Support\FormatoDelPanel::fechaYHora($fila['fecha']) }}</td>
            <td>
                @if ($fila['finalizado'])
                    <x-badge variante="exito">Finalizado</x-badge>
                @else
                    <x-badge variante="neutro">En curso</x-badge>
                @endif
            </td>
            <td>
                <div class="flex flex-wrap gap-2 py-1">
                    @if ($fila['finalizado'])
                        <x-button variante="secundario" :href="route('acompanamientos.show', $fila['id'])">Ver resultado<span class="sr-only"> del acompañamiento de {{ $fila['docente'] }}</span></x-button>
                    @elseif ($fila['esPropio'])
                        <x-button :href="route('acompanamientos.edit', $fila['id'])">Continuar<span class="sr-only"> el acompañamiento de {{ $fila['docente'] }}</span></x-button>
                        <x-button
                            variante="peligro"
                            wire:click="confirmarDescarte({{ $fila['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="confirmarDescarte({{ $fila['id'] }})"
                        >Descartar<span class="sr-only"> el acompañamiento de {{ $fila['docente'] }}</span></x-button>
                    @else
                        <span class="text-tabla text-texto-atenuado">Lo registra su observador</span>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
</x-tabla>

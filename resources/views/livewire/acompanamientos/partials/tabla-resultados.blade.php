{{-- Tabla "Mis resultados": acompañamientos finalizados del docente observado. Recibe $filas. --}}
<x-tabla titulo="Mis resultados">
    <x-slot:encabezado>
        <tr>
            <th scope="col">Pauta</th>
            <th scope="col">Observador</th>
            <th scope="col">Curso y asignatura</th>
            <th scope="col">Término</th>
            <th scope="col">Acciones</th>
        </tr>
    </x-slot:encabezado>

    @foreach ($filas as $fila)
        <tr wire:key="resultado-{{ $fila['id'] }}">
            <td class="font-semibold break-words">{{ $fila['pauta'] }}</td>
            <td class="break-words">{{ $fila['observador'] }}</td>
            <td class="break-words">{{ $fila['curso'] }} · {{ $fila['asignatura'] }}</td>
            <td class="whitespace-nowrap tabular-nums">{{ \App\Support\FormatoDelPanel::fechaYHora($fila['fecha']) }}</td>
            <td>
                <div class="flex flex-wrap gap-2 py-1">
                    <x-button :href="route('acompanamientos.show', $fila['id'])">Ver retroalimentación<span class="sr-only"> de {{ $fila['pauta'] }} del {{ \App\Support\FormatoDelPanel::fechaYHora($fila['fecha']) }}</span></x-button>
                </div>
            </td>
        </tr>
    @endforeach
</x-tabla>

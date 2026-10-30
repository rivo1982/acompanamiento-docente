<?php

namespace App\Support;

use App\Models\Ambito;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\PautaBase;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lecturas de las pantallas de pautas: el catálogo GLOBAL agrupado para elegir indicadores, y las pautas del
 * establecimiento ACTIVO. Consultas fijas, sin una por fila. No autoriza: quien la usa exige antes PautaPolicy.
 */
final class CatalogoDePautas
{
    /**
     * Pautas base por número, cada una con sus ámbitos por posición y los indicadores de cada ámbito por número.
     * Tres consultas.
     *
     * @return list<array{id: int, numero: int, nombre: string, ambitos: list<array{id: int, nombre: string, indicadores: list<array{id: int, numero: int, nombre: string}>}>}>
     */
    public function catalogo(): array
    {
        return PautaBase::query()
            ->orderBy('numero')
            ->with(['ambitos' => fn ($consulta) => $consulta->select(['id', 'pauta_base_id', 'nombre', 'posicion'])])
            ->with(['ambitos.indicadores' => fn ($consulta) => $consulta->select(['id', 'ambito_id', 'numero', 'nombre'])])
            ->get(['id', 'numero', 'nombre'])
            ->map(fn (PautaBase $base): array => [
                'id' => (int) $base->id,
                'numero' => (int) $base->numero,
                'nombre' => $base->nombre,
                'ambitos' => $base->ambitos->map(fn (Ambito $ambito): array => [
                    'id' => (int) $ambito->id,
                    'nombre' => $ambito->nombre,
                    'indicadores' => $ambito->indicadores->map(fn (Indicador $indicador): array => [
                        'id' => (int) $indicador->id,
                        'numero' => (int) $indicador->numero,
                        'nombre' => $indicador->nombre,
                    ])->values()->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Pautas vigentes (no eliminadas) del establecimiento activo, por nombre sin distinguir mayúsculas, con
     * `indicadores_count`. Una consulta.
     *
     * @return Collection<int, Pauta>
     */
    public function pautasDelEstablecimiento(): Collection
    {
        return Pauta::query()
            ->withCount('indicadores')
            ->orderByRaw('lower(nombre)')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ids de los indicadores de la pauta, en su posición guardada (para precargar el formulario de edición).
     *
     * @return list<int>
     */
    public function indicadoresDe(Pauta $pauta): array
    {
        return $pauta->indicadores()->pluck('indicadores.id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}

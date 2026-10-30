<?php

namespace App\Actions;

use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Crea y modifica las pautas del establecimiento ACTIVO: un nombre y un conjunto de indicadores del catálogo.
 * Solo el administrador del módulo (PautaPolicy::crear / ::actualizar).
 *
 * - Nombre: se recortan los espacios de los extremos y se colapsan los interiores; obligatorio, hasta
 *   NOMBRE_MAXIMO caracteres y único entre las pautas NO eliminadas del establecimiento, sin distinguir
 *   mayúsculas. La base no tiene un índice único (la pauta eliminada conserva su nombre), así que la unicidad se
 *   comprueba bajo un pg_advisory_xact_lock por establecimiento: dos guardados simultáneos se serializan.
 * - Indicadores: al menos uno; ids enteros positivos que existan en el catálogo; los repetidos se ignoran. Se
 *   guardan en `indicador_pauta.posicion` (1..n) según el número del indicador en el catálogo, que crece en el
 *   orden pauta base → ámbito → indicador (catalog.json): el orden en que llegan no importa.
 *
 * Una pauta con acompañamientos SÍ se puede renombrar y cambiar de indicadores: cada acompañamiento congeló su
 * contenido en `pauta_snapshot` al iniciarse y responde contra ese snapshot, así que el historial no cambia;
 * solo los acompañamientos nuevos usan el contenido nuevo.
 *
 * Los errores de datos se lanzan como ValidationException con las claves `nombre` e `indicadores`.
 */
class GuardarPauta
{
    public const NOMBRE_MAXIMO = 120;

    public function __construct(private readonly EstablecimientoActual $actual) {}

    /**
     * @param  array<int|string>  $indicadorIds
     *
     * @throws AuthorizationException si $administrador no es el administrador del módulo autenticado
     * @throws ValidationException si el nombre o los indicadores no son válidos
     */
    public function crear(Usuario $administrador, string $nombre, array $indicadorIds): Pauta
    {
        Gate::forUser($administrador)->authorize('crear', Pauta::class);

        [$nombre, $ordenados] = $this->validar($nombre, $indicadorIds);

        return DB::transaction(function () use ($nombre, $ordenados): Pauta {
            $this->exigirNombreLibre($nombre, null);

            $pauta = Pauta::query()->create(['nombre' => $nombre]);
            $pauta->indicadores()->attach($this->conPosiciones($ordenados));

            return $pauta;
        });
    }

    /**
     * @param  array<int|string>  $indicadorIds
     *
     * @throws ModelNotFoundException si la pauta no existe, está eliminada o es de otro establecimiento
     * @throws AuthorizationException si $administrador no es el administrador del módulo autenticado
     * @throws ValidationException si el nombre o los indicadores no son válidos
     */
    public function actualizar(Usuario $administrador, int $pautaId, string $nombre, array $indicadorIds): Pauta
    {
        $gate = Gate::forUser($administrador);
        $gate->authorize('actualizar', Pauta::query()->findOrFail($pautaId));

        [$nombre, $ordenados] = $this->validar($nombre, $indicadorIds);

        return DB::transaction(function () use ($gate, $pautaId, $nombre, $ordenados): Pauta {
            $this->exigirNombreLibre($nombre, $pautaId);

            // Relee y bloquea la pauta: si otro administrador la eliminó entretanto, no se modifica.
            $pauta = Pauta::query()->lockForUpdate()->findOrFail($pautaId);
            $gate->authorize('actualizar', $pauta);

            $pauta->nombre = $nombre;
            $pauta->save();
            $pauta->indicadores()->sync($this->conPosiciones($ordenados));
            $pauta->touch();

            return $pauta;
        });
    }

    /**
     * @param  array<int|string>  $indicadorIds
     * @return array{0: string, 1: list<int>} nombre normalizado e ids en orden de catálogo
     *
     * @throws ValidationException
     */
    private function validar(string $nombre, array $indicadorIds): array
    {
        $nombre = trim((string) preg_replace('/\s+/u', ' ', $nombre));
        $errores = [];

        if ($nombre === '') {
            $errores['nombre'] = 'Escribe un nombre para la pauta.';
        } elseif (mb_strlen($nombre) > self::NOMBRE_MAXIMO) {
            $errores['nombre'] = 'El nombre puede tener hasta '.self::NOMBRE_MAXIMO.' caracteres.';
        }

        $ids = [];
        $invalidos = false;

        foreach ($indicadorIds as $id) {
            $entero = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $entero === false ? $invalidos = true : $ids[$entero] = $entero;
        }

        $ordenados = $ids === [] ? [] : Indicador::query()->whereKey(array_values($ids))
            ->orderBy('numero')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($ids === [] && ! $invalidos) {
            $errores['indicadores'] = 'Elige al menos un indicador.';
        } elseif ($invalidos || count($ordenados) !== count($ids)) {
            $errores['indicadores'] = 'Algunos indicadores elegidos no existen en el catálogo.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return [$nombre, $ordenados];
    }

    /**
     * Serializa los guardados del establecimiento activo y rechaza un nombre ya usado por otra pauta vigente.
     *
     * @throws ValidationException
     */
    private function exigirNombreLibre(string $nombre, ?int $excepto): void
    {
        $clave = unpack('q', substr(hash('sha256', 'pautas:'.$this->actual->id(), true), 0, 8))[1];
        DB::statement('select pg_advisory_xact_lock(?)', [$clave]);

        $ocupado = Pauta::query()
            ->whereRaw('lower(nombre) = lower(?)', [$nombre])
            ->when($excepto !== null, fn ($consulta) => $consulta->whereKeyNot($excepto))
            ->exists();

        if ($ocupado) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una pauta con este nombre.']);
        }
    }

    /**
     * @param  list<int>  $ordenados
     * @return array<int, array{posicion: int}>
     */
    private function conPosiciones(array $ordenados): array
    {
        $filas = [];

        foreach ($ordenados as $indice => $id) {
            $filas[$id] = ['posicion' => $indice + 1];
        }

        return $filas;
    }
}

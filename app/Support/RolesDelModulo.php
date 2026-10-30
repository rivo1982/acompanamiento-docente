<?php

namespace App\Support;

use App\Models\AsignacionDocente;
use App\Models\Funcion;
use App\Models\Funcionario;
use App\Models\FuncionarioFuncion;
use App\Models\Observador;
use App\Models\Usuario;

/**
 * Roles del módulo de acompañamiento del usuario autenticado en el establecimiento ACTIVO, derivados
 * como en Vinkured (sin columna de rol): de la membresía, las funciones y la tabla `observadores`.
 *
 * - Administrador del módulo: un directivo (RolesDelUsuario::esDirectivo). El Administrador de la
 *   plataforma (membresias.es_administrador) por sí solo no lo es.
 * - Observador: el administrador del módulo o un funcionario habilitado en `observadores`.
 * - Docente: un funcionario con una función de categoría docente o con una asignación docente.
 *
 * Todo se consulta con modelos filtrados por el establecimiento activo, solo cuenta a funcionarios
 * vigentes y falla cerrado: sin usuario autenticado, sin establecimiento activo o para un usuario
 * distinto del autenticado, todo es false. Se evalúa EN EL MOMENTO, sin caché: quien deja de ser
 * observador pierde el acceso en la misma petición. Se registra como "scoped", como RolesDelUsuario.
 */
final class RolesDelModulo
{
    public const CATEGORIA_DOCENTE = 'docente';

    public function __construct(
        private readonly EstablecimientoActual $actual,
        private readonly RolesDelUsuario $roles,
    ) {}

    public function esDelUsuario(Usuario $usuario): bool
    {
        return $this->roles->esDelUsuario($usuario);
    }

    /**
     * Si la fila pertenece al establecimiento activo. Las políticas lo exigen además del filtro de
     * los modelos, porque reciben modelos que pudieron cargarse sin él.
     */
    public function esDelEstablecimientoActivo(int|string|null $establecimientoId): bool
    {
        $activo = $this->actual->id();

        return $activo !== null && $establecimientoId !== null && (int) $establecimientoId === $activo;
    }

    /**
     * Ids de los funcionarios vigentes del usuario autenticado en el establecimiento activo.
     *
     * @return list<int>
     */
    public function funcionarios(): array
    {
        $personaId = $this->actual->membresia()?->persona_id;

        if ($personaId === null) {
            return [];
        }

        return Funcionario::query()->vigentes()->where('persona_id', $personaId)
            ->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function esFuncionario(Usuario $usuario): bool
    {
        return $this->esDelUsuario($usuario) && $this->funcionarios() !== [];
    }

    public function esFuncionarioPropio(Usuario $usuario, int|string|null $funcionarioId): bool
    {
        return $funcionarioId !== null
            && $this->esDelUsuario($usuario)
            && in_array((int) $funcionarioId, $this->funcionarios(), true);
    }

    public function esAdministrador(Usuario $usuario): bool
    {
        return $this->esDelUsuario($usuario) && $this->roles->esDirectivo();
    }

    public function esObservador(Usuario $usuario): bool
    {
        return $this->esAdministrador($usuario)
            || ($this->esDelUsuario($usuario) && Observador::query()->whereIn('funcionario_id', $this->funcionarios())->exists());
    }

    public function esDocente(Usuario $usuario): bool
    {
        return $this->esDelUsuario($usuario)
            && collect($this->funcionarios())->contains(fn (int $id): bool => $this->puedeSerObservado($id));
    }

    /**
     * Si el funcionario dado, vigente en el establecimiento activo, puede observar: es directivo o
     * está habilitado en `observadores`.
     */
    public function puedeObservar(int $funcionarioId): bool
    {
        return $this->esObservadorAutomatico($funcionarioId)
            || ($this->esVigente($funcionarioId) && Observador::query()->where('funcionario_id', $funcionarioId)->exists());
    }

    /**
     * Si el funcionario dado, vigente en el establecimiento activo, observa por derivación de sus
     * funciones (directivo, A3): no necesita fila en `observadores` y no se le puede quitar.
     */
    public function esObservadorAutomatico(int $funcionarioId): bool
    {
        return $this->esVigente($funcionarioId)
            && $this->tieneFuncionDeCategoria($funcionarioId, Funcion::CATEGORIA_DIRECTIVO);
    }

    /**
     * Si el funcionario dado, vigente en el establecimiento activo, califica como docente: tiene una
     * función de categoría docente o una asignación docente.
     */
    public function puedeSerObservado(int $funcionarioId): bool
    {
        return $this->esVigente($funcionarioId)
            && ($this->tieneFuncionDeCategoria($funcionarioId, self::CATEGORIA_DOCENTE)
                || AsignacionDocente::query()->where('funcionario_id', $funcionarioId)->exists());
    }

    private function esVigente(int $funcionarioId): bool
    {
        return $this->actual->id() !== null
            && Funcionario::query()->vigentes()->whereKey($funcionarioId)->exists();
    }

    private function tieneFuncionDeCategoria(int $funcionarioId, string $categoria): bool
    {
        return Funcion::query()
            ->where('categoria', $categoria)
            ->whereIn('id', FuncionarioFuncion::query()->where('funcionario_id', $funcionarioId)->select('funcion_id'))
            ->exists();
    }
}

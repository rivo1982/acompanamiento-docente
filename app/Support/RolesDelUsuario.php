<?php

namespace App\Support;

use App\Models\AsignacionDocente;
use App\Models\Funcion;
use App\Models\Funcionario;
use App\Models\FuncionarioFuncion;
use App\Models\Membresia;
use App\Models\Usuario;

/**
 * Roles del usuario autenticado en el establecimiento ACTIVO (ADR-0005: los permisos nacen de
 * la membresía de ese colegio, nunca de otro). Se registra como "scoped": una instancia por
 * petición; el resultado se recuerda por membresía, así que cambiar de establecimiento
 * recalcula y los roles de uno no pasan al otro.
 *
 * Falla cerrado: sin usuario, sin establecimiento activo o con una membresía no vigente,
 * todo es false. Los roles de función no se dan a un funcionario con fecha de término ya vencida.
 *
 * @host-owned Copied from Vinkured app/Support/RolesDelUsuario.php — do not modify here; replace with the host class at integration.
 */
final class RolesDelUsuario
{
    /** @var array<int, array{administrador: bool, coordinador: bool, directivo: bool, profesorJefe: bool}> */
    private array $porMembresia = [];

    public function __construct(private readonly EstablecimientoActual $actual) {}

    /**
     * Si la membresía activa pertenece a este usuario. Las gates lo exigen para no responder
     * por un usuario distinto del autenticado.
     */
    public function esDelUsuario(Usuario $usuario): bool
    {
        $membresia = $this->actual->membresia();

        return $membresia !== null && (int) $membresia->usuario_id === (int) $usuario->getKey();
    }

    public function esAdministrador(): bool
    {
        return $this->roles()['administrador'];
    }

    public function esCoordinador(): bool
    {
        return $this->roles()['coordinador'];
    }

    public function esDirectivo(): bool
    {
        return $this->roles()['directivo'];
    }

    public function esProfesorJefe(): bool
    {
        return $this->roles()['profesorJefe'];
    }

    /**
     * Ve alertas SOLO como profesor jefe: lo es y no es Coordinador ni directivo (que ven todas las del
     * establecimiento). El Administrador no suma ni resta: por sí solo no ve alertas.
     */
    public function soloVeAlertasComoProfesorJefe(): bool
    {
        return $this->esProfesorJefe() && ! $this->esCoordinador() && ! $this->esDirectivo();
    }

    /**
     * @return array{administrador: bool, coordinador: bool, directivo: bool, profesorJefe: bool}
     */
    private function roles(): array
    {
        $membresia = $this->actual->membresia();

        if ($membresia === null) {
            return ['administrador' => false, 'coordinador' => false, 'directivo' => false, 'profesorJefe' => false];
        }

        return $this->porMembresia[$membresia->getKey()] ??= $this->calcular($membresia);
    }

    /**
     * @return array{administrador: bool, coordinador: bool, directivo: bool, profesorJefe: bool}
     */
    private function calcular(Membresia $membresia): array
    {
        $roles = ['administrador' => (bool) $membresia->es_administrador, 'coordinador' => false, 'directivo' => false, 'profesorJefe' => false];

        if ($membresia->persona_id === null) {
            return $roles;
        }

        // Todas estas consultas pasan por modelos con filtro: quedan acotadas al establecimiento activo.
        $funcionarios = Funcionario::query()->vigentes()->where('persona_id', $membresia->persona_id)->select('id');

        $funciones = Funcion::query()
            ->whereIn('id', FuncionarioFuncion::query()->whereIn('funcionario_id', $funcionarios)->select('funcion_id'))
            ->get(['clave', 'categoria']);

        $roles['coordinador'] = $funciones->contains('clave', Funcion::CLAVE_COORDINADOR_CONVIVENCIA);
        $roles['directivo'] = $funciones->contains('categoria', Funcion::CATEGORIA_DIRECTIVO);
        $roles['profesorJefe'] = AsignacionDocente::query()
            ->where('es_jefatura', true)
            ->whereIn('funcionario_id', $funcionarios)
            ->exists();

        return $roles;
    }
}

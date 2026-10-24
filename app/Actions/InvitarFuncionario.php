<?php

namespace App\Actions;

use App\Exceptions\InvitacionEnConflicto;
use App\Jobs\EnviarInvitacion;
use App\Models\Funcion;
use App\Models\Funcionario;
use App\Models\FuncionarioFuncion;
use App\Models\Membresia;
use App\Models\Persona;
use App\Models\Usuario;
use App\Support\Importacion\FilaDePlanilla;
use App\Support\Invitaciones\AdministradorDelEstablecimiento;
use App\Support\Invitaciones\InvitacionRegistrada;
use App\Support\Invitaciones\TokenDeInvitacion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Invita a un funcionario al establecimiento activo (Q18; carga unitaria y, fila por fila, la masiva).
 *
 * En UNA transacción: reutiliza la cuenta del correo si existe (credencial global, ADR-0005) o crea una con una
 * contraseña aleatoria que nadie conoce; reutiliza la persona del establecimiento con ese RUT (sin sobrescribir sus
 * datos: los del anfitrión mandan) o la crea; crea el funcionario y su función genérica si faltan; y crea la
 * membresía `invitada` con vencimiento. Ya confirmada, encola EnviarInvitacion, que genera el token justo antes de
 * enviar: el texto plano del token nunca se guarda, ni en la membresía ni en la cola.
 *
 * Los conflictos se evalúan solo dentro de este establecimiento. Si el correo tiene cuenta en otro, el resultado, el
 * trabajo encolado y el correo son los mismos que para un correo nuevo.
 */
class InvitarFuncionario
{
    /** Categoría de la planilla (funciones.categoria) => clave de la función genérica (FuncionesGenericasSeeder). */
    public const CLAVE_POR_CATEGORIA = [
        'docente' => 'docente',
        'directivo' => 'directivo',
        'asistente' => 'asistente',
        'profesional_apoyo' => 'profesional_apoyo',
    ];

    public function __construct(private readonly AdministradorDelEstablecimiento $administradores) {}

    /**
     * @throws AuthorizationException si $administrador no es Administrador vigente del establecimiento activo
     * @throws InvitacionEnConflicto si la persona o el correo ya tienen una membresía en este establecimiento
     */
    public function invitar(Membresia $administrador, FilaDePlanilla $fila): InvitacionRegistrada
    {
        $establecimientoId = $this->administradores->exigir($administrador);
        $funcion = $this->funcionGenerica($fila->categoriaFuncion);

        $membresia = DB::transaction(function () use ($establecimientoId, $fila, $funcion): Membresia {
            // Persona está filtrada por establecimiento: el mismo RUT en otro colegio es otra persona (ADR-0004).
            $persona = Persona::query()
                ->where('tipo_documento', 'rut')
                ->where('numero_documento', $fila->rut->paraAlmacenar())
                ->lockForUpdate()
                ->first();

            $usuario = Usuario::query()
                ->whereRaw('lower(correo) = ?', [$fila->correo])
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $this->rechazarConflictos($establecimientoId, $persona, $usuario);

            $usuario ??= $this->crearUsuario($fila->correo);
            $persona ??= $this->crearPersona($fila);

            $funcionario = Funcionario::query()->firstOrCreate(['persona_id' => $persona->getKey()]);
            FuncionarioFuncion::query()->firstOrCreate(['funcionario_id' => $funcionario->getKey(), 'funcion_id' => $funcion->getKey()]);

            $membresia = new Membresia;
            $membresia->forceFill([
                'usuario_id' => $usuario->getKey(),
                'establecimiento_id' => $establecimientoId,
                'persona_id' => $persona->getKey(),
                'es_administrador' => false,
                'estado' => 'invitada',
                'invitada_en' => now(),
                'invitacion_hash' => TokenDeInvitacion::hashSinEnlace(),
                'invitacion_expira_en' => TokenDeInvitacion::vencimientoDesdeAhora(),
            ])->save();

            return $membresia;
        });

        EnviarInvitacion::encolar($establecimientoId, (int) $membresia->getKey());

        return new InvitacionRegistrada((int) $membresia->getKey());
    }

    private function rechazarConflictos(int $establecimientoId, ?Persona $persona, ?Usuario $usuario): void
    {
        $deLaPersona = $persona === null
            ? null
            : Membresia::query()->where('persona_id', $persona->getKey())->lockForUpdate()->first();

        if ($deLaPersona !== null) {
            if ($usuario === null || (int) $deLaPersona->usuario_id !== (int) $usuario->getKey()) {
                throw InvitacionEnConflicto::rutConOtroCorreo();
            }

            throw match ($deLaPersona->estado) {
                Membresia::ESTADO_ACTIVA => InvitacionEnConflicto::yaTieneAcceso(),
                'suspendida' => InvitacionEnConflicto::suspendida(),
                default => InvitacionEnConflicto::yaInvitada(),
            };
        }

        // Solo la membresía de ESTE establecimiento: las de otros no se consultan (ADR-0005).
        $correoUsadoAqui = $usuario !== null && Membresia::query()
            ->where('usuario_id', $usuario->getKey())
            ->where('establecimiento_id', $establecimientoId)
            ->exists();

        if ($correoUsadoAqui) {
            throw InvitacionEnConflicto::correoDeOtraPersona();
        }
    }

    /** Cuenta nueva: `password` es obligatorio, así que lleva una aleatoria que nadie conoce hasta aceptar. */
    private function crearUsuario(string $correo): Usuario
    {
        $usuario = new Usuario;
        $usuario->forceFill(['correo' => $correo, 'password' => Str::random(64)])->save();

        return $usuario;
    }

    private function crearPersona(FilaDePlanilla $fila): Persona
    {
        return Persona::query()->create([
            'tipo_documento' => 'rut',
            'numero_documento' => $fila->rut->paraAlmacenar(),
            'nombres' => $fila->nombres,
            'primer_apellido' => $fila->primerApellido,
            'segundo_apellido' => $fila->segundoApellido,
            'correo' => $fila->correo,
            'telefono' => $fila->telefono,
        ]);
    }

    private function funcionGenerica(string $categoria): Funcion
    {
        $clave = self::CLAVE_POR_CATEGORIA[$categoria]
            ?? throw new LogicException("Categoría de función desconocida: {$categoria}.");

        return Funcion::query()->where('clave', $clave)->first()
            ?? throw new LogicException("Falta la función genérica '{$clave}' en el catálogo. Ejecute FuncionesGenericasSeeder.");
    }
}

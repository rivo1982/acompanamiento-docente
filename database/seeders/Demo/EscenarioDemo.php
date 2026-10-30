<?php

namespace Database\Seeders\Demo;

use App\Enums\EstadoImportacion;
use App\Support\Invitaciones\TokenDeInvitacion;
use App\Support\Rut;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Escenario ficticio del entorno de demostración: "Colegio Demostración", su Administrador, docentes activos,
 * invitaciones en cada estado de la lista (En plazo, Vence hoy, Vencida, Anulada), observadores con sus docentes
 * asignados y un historial de cargas masivas (completada con errores por fila, fallida con error del archivo y
 * pendiente).
 *
 * Escribe filas directamente (sin enviar correos ni encolar trabajos) y es idempotente: cada fila se identifica por su
 * clave natural y se actualiza si ya existe. Los plazos se calculan desde `now()`, así que las insignias son
 * deterministas respecto del momento de la siembra. Los hashes de invitación son SHA-256 de tokens aleatorios que
 * nadie conoce. Quien lo llame debe asegurar antes el esquema (DemoSeeder::asegurarEsquemaDemo()).
 */
final class EscenarioDemo
{
    public const RBD = 999001;

    public const NOMBRE_ESTABLECIMIENTO = 'Colegio Demostración';

    public const CORREO_ADMINISTRADOR = 'admin@demo.example.cl';

    /** Contraseña de demostración del Administrador y de los docentes activos (≥ 12, letras y números). */
    public const CONTRASENA = 'Demo2026demo';

    /** @var list<array{rut: string, nombres: string, primer_apellido: string, correo: string}> */
    public const DOCENTES = [
        ['rut' => '22222222-2', 'nombres' => 'Beatriz Demo', 'primer_apellido' => 'Ficticia', 'correo' => 'beatriz.docente@demo.example.cl'],
        ['rut' => '33333333-3', 'nombres' => 'Carlos Demo', 'primer_apellido' => 'Ficticio', 'correo' => 'carlos.docente@demo.example.cl'],
        ['rut' => '44444444-4', 'nombres' => 'Daniela Demo', 'primer_apellido' => 'Ficticia', 'correo' => 'daniela.docente@demo.example.cl'],
    ];

    /** Situación de cada invitación pendiente => datos de la persona invitada. */
    public const INVITACIONES = [
        'en_plazo' => ['rut' => '55555555-5', 'nombres' => 'Elena Demo', 'primer_apellido' => 'En Plazo', 'correo' => 'elena.invitada@demo.example.cl'],
        'vence_hoy' => ['rut' => '66666666-6', 'nombres' => 'Fernando Demo', 'primer_apellido' => 'Vence Hoy', 'correo' => 'fernando.invitado@demo.example.cl'],
        'vencida' => ['rut' => '77777777-7', 'nombres' => 'Gabriela Demo', 'primer_apellido' => 'Vencida', 'correo' => 'gabriela.invitada@demo.example.cl'],
        'anulada' => ['rut' => '88888888-8', 'nombres' => 'Hugo Demo', 'primer_apellido' => 'Anulado', 'correo' => 'hugo.invitado@demo.example.cl'],
    ];

    private Carbon $ahora;

    /** Funcionario directivo de la Administradora (observadora automática). */
    private int $directivoId;

    public function sembrar(): void
    {
        $this->ahora = Carbon::now();

        DB::transaction(function (): void {
            $establecimientoId = $this->establecimiento();
            $this->cursos($establecimientoId);

            $membresiaAdministrador = $this->administrador($establecimientoId);
            $docentes = $this->docentes($establecimientoId);
            $this->observadores($establecimientoId, $docentes);
            $this->invitaciones($establecimientoId);
            $this->importaciones($establecimientoId, $membresiaAdministrador);
        });
    }

    private function establecimiento(): int
    {
        $regionId = $this->fila('regiones', ['codigo' => 'DEMO-R'], ['nombre' => 'Región Demostración']);
        $comunaId = $this->fila('comunas', ['codigo' => 'DEMO-C'], ['nombre' => 'Comuna Demostración', 'region_id' => $regionId]);

        return $this->fila('establecimientos', ['rbd' => self::RBD], [
            'dv_rbd' => '1',
            'nombre' => self::NOMBRE_ESTABLECIMIENTO,
            'comuna_id' => $comunaId,
            'correo_institucional' => 'contacto@demo.example.cl',
        ]);
    }

    /**
     * Año escolar en curso y dos cursos (1° A y 1° B básico).
     */
    private function cursos(int $establecimientoId): void
    {
        $anioId = $this->fila('anios_escolares', ['establecimiento_id' => $establecimientoId, 'anio' => $this->ahora->year], []);
        $nivelId = $this->fila('niveles_ensenanza', ['nombre' => 'Básica (demo)'], ['orden' => 1]);
        $gradoId = $this->fila('grados', ['nivel_ensenanza_id' => $nivelId, 'nombre' => '1° básico (demo)'], ['orden' => 1]);

        foreach (['A', 'B'] as $letra) {
            $this->fila('cursos', [
                'establecimiento_id' => $establecimientoId, 'anio_escolar_id' => $anioId, 'grado_id' => $gradoId, 'letra' => $letra,
            ], []);
        }
    }

    /**
     * Administrador de la cuenta (membresía activa con es_administrador) y funcionario directivo: Administrador y
     * observador del módulo. Devuelve el id de su membresía.
     */
    private function administrador(int $establecimientoId): int
    {
        $personaId = $this->persona($establecimientoId, [
            'rut' => '11111111-1', 'nombres' => 'Alicia Demo', 'primer_apellido' => 'Administradora', 'correo' => self::CORREO_ADMINISTRADOR,
        ]);
        $this->directivoId = $this->funcionario($establecimientoId, $personaId, 'directivo');

        return $this->membresia($establecimientoId, $personaId, self::CORREO_ADMINISTRADOR, Hash::make(self::CONTRASENA), [
            'es_administrador' => true,
            'estado' => 'activa',
            'activada_en' => $this->ahora,
            'invitacion_hash' => null,
            'invitacion_expira_en' => null,
        ]);
    }

    /**
     * Docentes activos; el primero es profesor jefe del 1° A. Devuelve sus ids de funcionario en el orden de DOCENTES.
     *
     * @return list<int>
     */
    private function docentes(int $establecimientoId): array
    {
        $contrasena = Hash::make(self::CONTRASENA);
        $funcionarios = [];

        foreach (self::DOCENTES as $indice => $docente) {
            $personaId = $this->persona($establecimientoId, $docente);
            $funcionarioId = $this->funcionario($establecimientoId, $personaId, 'docente');
            $funcionarios[] = $funcionarioId;
            $this->membresia($establecimientoId, $personaId, $docente['correo'], $contrasena, [
                'es_administrador' => false,
                'estado' => 'activa',
                'activada_en' => $this->ahora,
                'invitacion_hash' => null,
                'invitacion_expira_en' => null,
            ]);

            if ($indice === 0) {
                $cursoId = (int) DB::table('cursos')->where('establecimiento_id', $establecimientoId)->where('letra', 'A')->value('id');
                $this->fila('asignaciones_docentes', ['curso_id' => $cursoId, 'es_jefatura' => true], [
                    'establecimiento_id' => $establecimientoId, 'funcionario_id' => $funcionarioId, 'asignatura_id' => null,
                ]);
            }
        }

        return $funcionarios;
    }

    /**
     * La primera docente queda habilitada como observadora. La Administradora (directiva, observadora automática)
     * acompaña a la segunda y tercera docente, y la observadora habilitada a la segunda.
     *
     * @param  list<int>  $docentes
     */
    private function observadores(int $establecimientoId, array $docentes): void
    {
        [$beatriz, $carlos, $daniela] = $docentes;

        $this->fila('observadores', ['establecimiento_id' => $establecimientoId, 'funcionario_id' => $beatriz], []);

        foreach ([[$this->directivoId, $carlos], [$this->directivoId, $daniela], [$beatriz, $carlos]] as [$observadorId, $docenteId]) {
            $this->fila('asignaciones_acompanamiento', ['observador_id' => $observadorId, 'docente_id' => $docenteId], [
                'establecimiento_id' => $establecimientoId,
            ]);
        }
    }

    /**
     * Membresías `invitada` en cada situación de la lista. Las cuentas llevan una contraseña aleatoria que nadie conoce.
     */
    private function invitaciones(int $establecimientoId): void
    {
        $vencimientos = [
            'en_plazo' => $this->ahora->copy()->addDays(5),
            'vence_hoy' => $this->ahora->copy()->endOfDay(),
            'vencida' => $this->ahora->copy()->subDays(3),
            'anulada' => null,
        ];

        foreach (self::INVITACIONES as $situacion => $invitado) {
            $personaId = $this->persona($establecimientoId, $invitado);
            $this->funcionario($establecimientoId, $personaId, 'docente');
            $vencimiento = $vencimientos[$situacion];

            $this->membresia($establecimientoId, $personaId, $invitado['correo'], Hash::make(Str::random(64)), [
                'es_administrador' => false,
                'estado' => 'invitada',
                'activada_en' => null,
                'invitada_en' => $vencimiento === null ? $this->ahora->copy()->subDays(2) : $vencimiento->copy()->subDays(7),
                'invitacion_hash' => $vencimiento === null ? null : TokenDeInvitacion::hashSinEnlace(),
                'invitacion_expira_en' => $vencimiento,
            ]);
        }
    }

    /**
     * Historial de cargas masivas (sin archivos): completada con errores por fila, fallida con un error del archivo y
     * pendiente recién subida (la página de resultado consulta hasta el límite de seguimiento).
     */
    private function importaciones(int $establecimientoId, int $membresiaId): void
    {
        $cargas = [
            'demo-funcionarios-completada.xlsx' => [
                'estado' => EstadoImportacion::Completada->value, 'total' => 5, 'creados' => 3, 'con_error' => 2,
                'errores' => [
                    ['fila' => 3, 'mensajes' => ['El RUT no es válido.']],
                    ['fila' => 5, 'mensajes' => ['El correo no es válido.', 'Falta la función.']],
                ],
                'creada' => $this->ahora->copy()->subDays(2),
            ],
            'demo-funcionarios-fallida.xlsx' => [
                'estado' => EstadoImportacion::Fallida->value, 'total' => 0, 'creados' => 0, 'con_error' => 0,
                'errores' => [['fila' => null, 'mensajes' => ['El archivo no tiene las columnas esperadas.']]],
                'creada' => $this->ahora->copy()->subDay(),
            ],
            'demo-funcionarios-pendiente.xlsx' => [
                'estado' => EstadoImportacion::Pendiente->value, 'total' => 0, 'creados' => 0, 'con_error' => 0,
                'errores' => null,
                'creada' => $this->ahora->copy(),
            ],
        ];

        foreach ($cargas as $archivo => $carga) {
            DB::table('importaciones_usuarios')->updateOrInsert(
                ['establecimiento_id' => $establecimientoId, 'archivo' => $archivo],
                [
                    'membresia_id' => $membresiaId,
                    'estado' => $carga['estado'],
                    'total' => $carga['total'],
                    'creados' => $carga['creados'],
                    'con_error' => $carga['con_error'],
                    'errores' => $carga['errores'] === null ? null : json_encode($carga['errores'], JSON_THROW_ON_ERROR),
                    'created_at' => $carga['creada'],
                    'updated_at' => $carga['creada'],
                ],
            );
        }
    }

    /**
     * @param  array{rut: string, nombres: string, primer_apellido: string, correo: string}  $datos
     */
    private function persona(int $establecimientoId, array $datos): int
    {
        return $this->fila('personas', [
            'establecimiento_id' => $establecimientoId,
            'tipo_documento' => 'rut',
            'numero_documento' => Rut::desde($datos['rut'])->paraAlmacenar(),
        ], [
            'nombres' => $datos['nombres'],
            'primer_apellido' => $datos['primer_apellido'],
            'correo' => $datos['correo'],
        ]);
    }

    /**
     * Funcionario de la persona con la función genérica de la categoría (FuncionesGenericasSeeder).
     */
    private function funcionario(int $establecimientoId, int $personaId, string $clave): int
    {
        $funcionarioId = $this->fila('funcionarios', ['persona_id' => $personaId], ['establecimiento_id' => $establecimientoId]);
        $funcionId = (int) DB::table('funciones')->where('clave', $clave)->value('id');

        $this->fila('funcionario_funciones', ['funcionario_id' => $funcionarioId, 'funcion_id' => $funcionId], [
            'establecimiento_id' => $establecimientoId,
        ]);

        return $funcionarioId;
    }

    /**
     * Cuenta (por correo) y su membresía en el establecimiento. Devuelve el id de la membresía.
     *
     * @param  array<string, mixed>  $membresia
     */
    private function membresia(int $establecimientoId, int $personaId, string $correo, string $hashContrasena, array $membresia): int
    {
        $usuarioId = $this->fila('usuarios', ['correo' => $correo], [
            'password' => $hashContrasena,
            'es_soporte_vinkured' => false,
            'email_verified_at' => $membresia['estado'] === 'activa' ? $this->ahora : null,
        ]);

        return $this->fila('membresias', ['usuario_id' => $usuarioId, 'establecimiento_id' => $establecimientoId], [
            'persona_id' => $personaId,
            'invitada_en' => $this->ahora->copy()->subDays(30),
            ...$membresia,
        ]);
    }

    /**
     * Inserta o actualiza la fila identificada por $clave y devuelve su id.
     *
     * @param  array<string, mixed>  $clave
     * @param  array<string, mixed>  $valores
     */
    private function fila(string $tabla, array $clave, array $valores): int
    {
        $existente = DB::table($tabla)->where($clave)->value('id');

        if ($existente !== null) {
            DB::table($tabla)->where('id', $existente)->update([...$valores, 'updated_at' => $this->ahora]);

            return (int) $existente;
        }

        return (int) DB::table($tabla)->insertGetId([
            ...$clave, ...$valores, 'created_at' => $this->ahora, 'updated_at' => $this->ahora,
        ]);
    }
}

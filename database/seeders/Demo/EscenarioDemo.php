<?php

namespace Database\Seeders\Demo;

use App\Actions\FinalizarAcompanamiento;
use App\Actions\GuardarRespuesta;
use App\Actions\IniciarAcompanamiento;
use App\Enums\EstadoImportacion;
use App\Models\Acompanamiento;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use App\Support\Invitaciones\TokenDeInvitacion;
use App\Support\Rut;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Escenario ficticio del entorno de demostración: "Colegio Demostración", su Administrador, docentes activos,
 * invitaciones en cada estado de la lista (En plazo, Vence hoy, Vencida, Anulada), observadores con sus docentes
 * asignados, tres pautas de ejemplo, un historial de cargas masivas (completada con errores por fila, fallida con
 * error del archivo y pendiente) y dos acompañamientos de aula de la observadora habilitada al docente observado: uno
 * en curso y otro finalizado.
 *
 * Escribe filas directamente (sin enviar correos ni encolar trabajos), salvo los acompañamientos, que se crean con las
 * acciones del módulo para respetar sus reglas (el servidor fija `iniciado_en`): finalizar avisa por correo al docente
 * una sola vez. Es idempotente: cada fila se identifica por su clave natural y se actualiza si ya existe; cada
 * acompañamiento, por su docente y su pauta, y no se vuelve a iniciar ni a finalizar. Los plazos se calculan desde `now()`, así que las insignias son
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

    /** Pautas de ejemplo => números de los indicadores del catálogo que las forman (en orden del catálogo). */
    public const PAUTAS = [
        'Observación de aula (demo)' => [1, 2, 3, 4, 5, 6, 7, 8],
        'Planificación y convivencia (demo)' => [12, 13, 14, 46, 47],
        'Ética y convivencia (demo)' => [42, 43, 44, 45, 46, 47],
    ];

    /** Observadora habilitada (primera docente) que registra los acompañamientos de demostración. */
    public const CORREO_OBSERVADOR = 'beatriz.docente@demo.example.cl';

    /** Docente observado en los acompañamientos de demostración (segundo docente, sin rol de observador). */
    public const CORREO_DOCENTE_OBSERVADO = 'carlos.docente@demo.example.cl';

    public const ASIGNATURA = 'Lenguaje y Comunicación (demo)';

    /**
     * Acompañamientos de demostración: pauta, si se finaliza y, por número de indicador, el nivel y la retroalimentación
     * personal. El en curso responde solo una parte de la pauta; el finalizado, toda, con los cuatro niveles y los
     * descriptores con nota de protocolo (42 y 45).
     *
     * @var list<array{pauta: string, finalizar: bool, respuestas: array<int, array{0: string, 1: ?string}>}>
     */
    public const ACOMPANAMIENTOS = [
        [
            'pauta' => 'Observación de aula (demo)',
            'finalizar' => false,
            'respuestas' => [
                1 => ['logrado', null],
                2 => ['en_desarrollo', 'Conviene retomar el objetivo de la clase al cierre.'],
                3 => ['destacado', null],
            ],
        ],
        [
            'pauta' => 'Ética y convivencia (demo)',
            'finalizar' => true,
            'respuestas' => [
                42 => ['en_inicio', 'Conversemos sobre los límites del rol frente a situaciones personales de los estudiantes.'],
                43 => ['en_desarrollo', null],
                44 => ['logrado', null],
                45 => ['destacado', 'Muy buen manejo del protocolo de uso de dispositivos en la sala.'],
                46 => ['logrado', 'El conflicto del recreo se resolvió con diálogo y acuerdos claros.'],
                47 => ['en_desarrollo', null],
            ],
        ],
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

        $establecimientoId = DB::transaction(function (): int {
            $establecimientoId = $this->establecimiento();
            $this->cursos($establecimientoId);

            $membresiaAdministrador = $this->administrador($establecimientoId);
            $docentes = $this->docentes($establecimientoId);
            $this->observadores($establecimientoId, $docentes);
            $this->pautas($establecimientoId);
            $this->invitaciones($establecimientoId);
            $this->importaciones($establecimientoId, $membresiaAdministrador);

            return $establecimientoId;
        });

        // Fuera de la transacción: FinalizarAcompanamiento no debe correr dentro de otra (ver su avisarAlDocente).
        $this->acompanamientos($establecimientoId);
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
     * Pautas de ejemplo armadas con indicadores del catálogo (CatalogSeeder debe haber corrido antes); `posicion`
     * sigue el orden del catálogo, como GuardarPauta.
     */
    private function pautas(int $establecimientoId): void
    {
        foreach (self::PAUTAS as $nombre => $numeros) {
            $pautaId = $this->fila('pautas', ['establecimiento_id' => $establecimientoId, 'nombre' => $nombre], []);
            $indicadores = DB::table('indicadores')->whereIn('numero', $numeros)->orderBy('numero')->pluck('id');

            foreach ($indicadores as $indice => $indicadorId) {
                $this->fila('indicador_pauta', ['pauta_id' => $pautaId, 'indicador_id' => $indicadorId], [
                    'establecimiento_id' => $establecimientoId, 'posicion' => $indice + 1,
                ]);
            }
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
     * Acompañamientos de ACOMPANAMIENTOS, creados como la observadora con IniciarAcompanamiento, GuardarRespuesta y
     * FinalizarAcompanamiento. Si ya existe uno del docente con esa pauta se reutiliza: si sigue en curso se
     * completan sus respuestas (y se finaliza si corresponde); si ya está finalizado no se toca.
     */
    private function acompanamientos(int $establecimientoId): void
    {
        $observador = Usuario::query()->where('correo', self::CORREO_OBSERVADOR)->sole();
        $docenteId = (int) DB::table('funcionarios')
            ->join('personas', 'personas.id', '=', 'funcionarios.persona_id')
            ->where('funcionarios.establecimiento_id', $establecimientoId)
            ->where('personas.correo', self::CORREO_DOCENTE_OBSERVADO)
            ->value('funcionarios.id');
        $cursoId = (int) DB::table('cursos')->where('establecimiento_id', $establecimientoId)->where('letra', 'A')->value('id');
        $asignaturaId = $this->fila('asignaturas', ['nombre' => self::ASIGNATURA], []);

        $this->comoUsuario($observador, $establecimientoId, function () use ($observador, $establecimientoId, $docenteId, $cursoId, $asignaturaId): void {
            foreach (self::ACOMPANAMIENTOS as $datos) {
                $pautaId = (int) DB::table('pautas')
                    ->where('establecimiento_id', $establecimientoId)->where('nombre', $datos['pauta'])->value('id');

                $acompanamiento = Acompanamiento::query()->where('docente_id', $docenteId)->where('pauta_id', $pautaId)->orderBy('id')->first()
                    ?? app(IniciarAcompanamiento::class)->iniciar($observador, $docenteId, $pautaId, $cursoId, $asignaturaId);

                if ($acompanamiento->estaFinalizado()) {
                    continue;
                }

                foreach ($datos['respuestas'] as $numero => [$nivel, $retroalimentacion]) {
                    $indicadorId = (int) DB::table('indicadores')->where('numero', $numero)->value('id');
                    app(GuardarRespuesta::class)->guardar($observador, (int) $acompanamiento->getKey(), $indicadorId, $nivel, $retroalimentacion);
                }

                if ($datos['finalizar']) {
                    app(FinalizarAcompanamiento::class)->finalizar($observador, (int) $acompanamiento->getKey());
                }
            }
        });
    }

    /**
     * Corre $trabajo con $usuario autenticado y $establecimientoId como establecimiento activo, como en una petición,
     * y deja después la sesión sin usuario ni establecimiento (también si $trabajo lanza).
     *
     * @param  Closure(): void  $trabajo
     */
    private function comoUsuario(Usuario $usuario, int $establecimientoId, Closure $trabajo): void
    {
        app()->forgetScopedInstances();
        Auth::setUser($usuario);

        try {
            if (! app(EstablecimientoActual::class)->seleccionar($establecimientoId)) {
                throw new RuntimeException("The demo user {$usuario->correo} has no active membership in the demo establishment.");
            }

            $trabajo();
        } finally {
            app(EstablecimientoActual::class)->olvidar();
            Auth::forgetUser();
            app()->forgetScopedInstances();
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

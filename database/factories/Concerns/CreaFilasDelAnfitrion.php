<?php

namespace Database\Factories\Concerns;

use App\Support\EstablecimientoActual;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Crea las filas del anfitrión (funcionarios, cursos, asignaturas) que necesitan las fábricas del
 * módulo, en el establecimiento ACTIVO. Usa DB::table porque Vinkured es dueño de esas tablas y sus
 * fábricas no se copian. Mismas filas que los ayudantes de tests/Pest.php, con documentos únicos de Faker.
 */
trait CreaFilasDelAnfitrion
{
    /**
     * Año escolar de los cursos de fábrica. Es el mismo que usa newCurso() en tests/Pest.php, así
     * fábricas y ayudantes reutilizan la única fila de anios_escolares por establecimiento y año
     * (UNIQUE establecimiento_id, anio) en vez de chocar al crearla dos veces.
     */
    public const ANIO_ESCOLAR_DE_FABRICA = 2026;

    protected static function establecimientoActivo(): int
    {
        return app(EstablecimientoActual::class)->id()
            ?? throw new RuntimeException('Las fábricas del módulo requieren un establecimiento activo.');
    }

    protected static function nuevoFuncionario(): int
    {
        $establecimientoId = self::establecimientoActivo();
        $personaId = DB::table('personas')->insertGetId([
            'establecimiento_id' => $establecimientoId, 'tipo_documento' => 'otro',
            'numero_documento' => fake()->unique()->numerify('fab-#########'), 'nombres' => 'Ana', 'primer_apellido' => 'Perez',
        ]);

        return DB::table('funcionarios')->insertGetId(['establecimiento_id' => $establecimientoId, 'persona_id' => $personaId]);
    }

    protected static function nuevoCurso(): int
    {
        $establecimientoId = self::establecimientoActivo();
        $anio = ['establecimiento_id' => $establecimientoId, 'anio' => self::ANIO_ESCOLAR_DE_FABRICA];
        $anioId = DB::table('anios_escolares')->where($anio)->value('id') ?? DB::table('anios_escolares')->insertGetId($anio);
        $nivelId = DB::table('niveles_ensenanza')->insertGetId(['nombre' => 'Basica', 'orden' => 1]);
        $gradoId = DB::table('grados')->insertGetId(['nivel_ensenanza_id' => $nivelId, 'nombre' => '1', 'orden' => 1]);

        return DB::table('cursos')->insertGetId([
            'establecimiento_id' => $establecimientoId, 'anio_escolar_id' => $anioId, 'grado_id' => $gradoId, 'letra' => 'A',
        ]);
    }

    protected static function nuevaAsignatura(): int
    {
        return DB::table('asignaturas')->insertGetId(['nombre' => 'Asignatura '.fake()->unique()->numerify('#########')]);
    }
}

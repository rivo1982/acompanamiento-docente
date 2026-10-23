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
        $anioId = DB::table('anios_escolares')->where(['establecimiento_id' => $establecimientoId, 'anio' => 2026])->value('id')
            ?? DB::table('anios_escolares')->insertGetId(['establecimiento_id' => $establecimientoId, 'anio' => 2026]);
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

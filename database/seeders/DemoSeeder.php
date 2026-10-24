<?php

namespace Database\Seeders;

use Database\Seeders\Demo\EscenarioDemo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siembra el entorno de DEMOSTRACIÓN con datos ficticios (`composer demo:setup`).
 *
 * Solo corre en el esquema aislado `acompanamiento_demo` de roberto_dev: se niega (antes de cualquier escritura) si la
 * conexión por defecto no es pgsql con search_path exactamente ese esquema, o si la sesión de la base no resuelve a
 * él. Así nunca escribe en `public`, donde vive Vinkured. Idempotente: puede volver a correr sin duplicar filas y
 * recalcula los plazos de las invitaciones desde el momento de la siembra.
 */
class DemoSeeder extends Seeder
{
    public const SCHEMA = 'acompanamiento_demo';

    /**
     * @throws RuntimeException
     */
    public function run(): void
    {
        self::asegurarEsquemaDemo();

        $this->call(CatalogSeeder::class);
        $this->call(FuncionesGenericasSeeder::class);

        (new EscenarioDemo)->sembrar();
    }

    /**
     * Falla cerrado salvo que la conexión por defecto sea pgsql limitada al esquema de demostración, tanto en la
     * configuración como en la sesión viva de la base.
     *
     * @throws RuntimeException
     */
    public static function asegurarEsquemaDemo(): void
    {
        $conexion = (string) config('database.default');
        $driver = config("database.connections.{$conexion}.driver");
        $searchPath = config("database.connections.{$conexion}.search_path");

        if ($driver !== 'pgsql' || $searchPath !== self::SCHEMA) {
            throw new RuntimeException(sprintf(
                'Refusing to seed the demo: the default connection must be pgsql with search_path "%s" (got connection "%s", driver "%s", search_path "%s").',
                self::SCHEMA,
                $conexion,
                (string) $driver,
                is_scalar($searchPath) ? (string) $searchPath : json_encode($searchPath),
            ));
        }

        $esquemaActual = DB::connection($conexion)->scalar('select current_schema()');

        if ($esquemaActual !== self::SCHEMA) {
            throw new RuntimeException(sprintf(
                'Refusing to seed the demo: the database session resolves to schema "%s", not "%s".',
                (string) $esquemaActual,
                self::SCHEMA,
            ));
        }
    }
}

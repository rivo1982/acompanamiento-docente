<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integridad de respuestas_acompanamiento en la base, como los triggers de integridad de Vinkured:
 * ninguna escritura (Eloquent, constructor de consultas en bloque o SQL crudo) crea, modifica,
 * mueve ni elimina una respuesta de un acompañamiento finalizado, ni guarda un indicador ausente
 * de su `pauta_snapshot`. La guarda de Eloquent sigue dando la excepción legible; esta es la red.
 *
 * Bloqueo del acompañamiento: el trigger lo lee con FOR NO KEY UPDATE, el mismo modo de bloqueo que
 * toma un UPDATE de columnas no clave (finalizar(), o tocar updated_at). Así:
 * - choca con finalizar(): si finalizar() llegó antes, la escritura espera su COMMIT y, en READ
 *   COMMITTED, vuelve a leer la fila ya finalizada y se rechaza; si la respuesta llegó antes,
 *   finalizar() espera a que se confirme (la clave foránea sola, FOR KEY SHARE, no chocaría);
 * - serializa las escrituras de respuestas de un mismo acompañamiento, y una transacción que luego
 *   actualiza ese acompañamiento ya tiene el bloqueo que necesita: no hay escalada de FOR SHARE a
 *   UPDATE, que dejaba a dos transacciones esperándose mutuamente (deadlock, R3-001). Serializar es
 *   aceptable: cada acompañamiento tiene un único observador que lo escribe;
 * - a diferencia de FOR UPDATE, no bloquea las comprobaciones FOR KEY SHARE de claves foráneas
 *   que apuntan al acompañamiento.
 *
 * Cada rama tiene su propio mensaje para distinguir el motivo del rechazo (R3-002).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION respuestas_acompanamiento_exigen_acompanamiento_en_curso() RETURNS trigger AS $$
            DECLARE
                acompanamiento_estado text;
                snapshot jsonb;
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    SELECT estado INTO acompanamiento_estado
                    FROM acompanamientos WHERE id = OLD.acompanamiento_id FOR NO KEY UPDATE;

                    IF acompanamiento_estado = 'finalizado' THEN
                        RAISE EXCEPTION 'Las respuestas de un acompañamiento finalizado no se pueden crear, modificar ni eliminar'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                SELECT estado, pauta_snapshot INTO acompanamiento_estado, snapshot
                FROM acompanamientos WHERE id = NEW.acompanamiento_id FOR NO KEY UPDATE;

                IF acompanamiento_estado = 'finalizado' THEN
                    RAISE EXCEPTION 'Las respuestas de un acompañamiento finalizado no se pueden crear, modificar ni eliminar'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF acompanamiento_estado IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM jsonb_array_elements(COALESCE(snapshot -> 'indicadores', '[]'::jsonb)) AS entrada
                    WHERE entrada ->> 'id' = NEW.indicador_id::text
                ) THEN
                    RAISE EXCEPTION 'El indicador de la respuesta no forma parte del pauta_snapshot de su acompañamiento'
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER respuestas_acompanamiento_exigen_acompanamiento_en_curso
            BEFORE INSERT OR UPDATE OR DELETE ON respuestas_acompanamiento
            FOR EACH ROW EXECUTE FUNCTION respuestas_acompanamiento_exigen_acompanamiento_en_curso()
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS respuestas_acompanamiento_exigen_acompanamiento_en_curso ON respuestas_acompanamiento');
        DB::unprepared('DROP FUNCTION IF EXISTS respuestas_acompanamiento_exigen_acompanamiento_en_curso()');
    }
};

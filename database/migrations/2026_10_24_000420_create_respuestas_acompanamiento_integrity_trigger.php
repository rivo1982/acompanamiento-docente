<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integridad de respuestas_acompanamiento en la base, como los triggers de integridad de Vinkured:
 * ninguna escritura (Eloquent, constructor de consultas en bloque o SQL crudo) crea, modifica,
 * mueve ni elimina una respuesta de un acompañamiento finalizado, ni guarda un indicador ausente
 * de su `pauta_snapshot`. La guarda de Eloquent sigue dando la excepción legible; esta es la red.
 *
 * Carrera con finalizar(): la clave foránea solo toma FOR KEY SHARE sobre el acompañamiento, que no
 * choca con el UPDATE de finalizar(). El trigger lee el acompañamiento con FOR SHARE, que sí choca:
 * si finalizar() llegó antes, la escritura espera su COMMIT y, en READ COMMITTED, vuelve a leer la
 * fila ya finalizada y se rechaza; si la respuesta llegó antes, finalizar() espera a que se confirme.
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
                    FROM acompanamientos WHERE id = OLD.acompanamiento_id FOR SHARE;

                    IF acompanamiento_estado = 'finalizado' THEN
                        RAISE EXCEPTION 'respuestas_acompanamiento of a finalizado acompanamiento cannot change'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                SELECT estado, pauta_snapshot INTO acompanamiento_estado, snapshot
                FROM acompanamientos WHERE id = NEW.acompanamiento_id FOR SHARE;

                IF acompanamiento_estado = 'finalizado' THEN
                    RAISE EXCEPTION 'respuestas_acompanamiento of a finalizado acompanamiento cannot change'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF acompanamiento_estado IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM jsonb_array_elements(COALESCE(snapshot -> 'indicadores', '[]'::jsonb)) AS entrada
                    WHERE entrada ->> 'id' = NEW.indicador_id::text
                ) THEN
                    RAISE EXCEPTION 'respuestas_acompanamiento.indicador_id must be in the acompanamiento pauta_snapshot'
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

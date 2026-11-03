<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza los apoyos pedagógicos de tres consejos por indicador (nunca poblados) por UNA ficha por indicador del
 * catálogo GLOBAL, tomada del anexo de apoyo pedagógico (database/seeders/data/apoyo_pedagogico.json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('apoyos_pedagogicos');

        Schema::create('apoyos_pedagogicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_id')->unique()->constrained('indicadores')->restrictOnDelete();
            $table->string('contexto');
            $table->text('foco_mejora');
            $table->text('accion_metodologica');
            $table->text('accion_procedimental');
            $table->text('accion_formativa');
            $table->text('evidencia_avance');
            $table->text('referencias');
            $table->timestamps();
        });
    }

    /**
     * Vuelve a la estructura anterior (vacía): las fichas no caben en ella y se pierden.
     */
    public function down(): void
    {
        Schema::dropIfExists('apoyos_pedagogicos');

        Schema::create('apoyos_pedagogicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_id')->constrained('indicadores')->restrictOnDelete();
            $table->unsignedInteger('posicion');
            $table->string('herramienta');
            $table->string('nivel_objetivo');
            $table->text('como_hacerlo');
            $table->text('ejemplo');
            $table->string('duracion');
            $table->text('senal_de_logro');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE apoyos_pedagogicos ADD CONSTRAINT apoyos_pedagogicos_nivel_objetivo_check CHECK (nivel_objetivo IN ('logrado', 'destacado'))");
    }
};

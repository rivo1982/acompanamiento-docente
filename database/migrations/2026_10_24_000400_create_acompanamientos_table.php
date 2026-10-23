<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acompanamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->unsignedBigInteger('pauta_id');
            $table->foreign(['establecimiento_id', 'pauta_id'])->references(['establecimiento_id', 'id'])->on('pautas')->restrictOnDelete();
            $table->unsignedBigInteger('observador_id');
            $table->foreign(['establecimiento_id', 'observador_id'])->references(['establecimiento_id', 'id'])->on('funcionarios')->restrictOnDelete();
            $table->unsignedBigInteger('docente_id');
            $table->foreign(['establecimiento_id', 'docente_id'])->references(['establecimiento_id', 'id'])->on('funcionarios')->restrictOnDelete();
            $table->unsignedBigInteger('curso_id');
            $table->foreign(['establecimiento_id', 'curso_id'])->references(['establecimiento_id', 'id'])->on('cursos')->restrictOnDelete();
            $table->foreignId('asignatura_id')->constrained('asignaturas')->restrictOnDelete();
            $table->timestamp('iniciado_en');
            $table->timestamp('finalizado_en')->nullable();
            $table->unsignedInteger('duracion_minutos')->nullable();
            $table->string('estado')->default('en_curso');
            $table->jsonb('pauta_snapshot');
            $table->timestamps();

            $table->unique(['establecimiento_id', 'id']);
        });

        DB::statement("ALTER TABLE acompanamientos ADD CONSTRAINT acompanamientos_estado_check CHECK (estado IN ('en_curso', 'finalizado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('acompanamientos');
    }
};

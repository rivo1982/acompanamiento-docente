<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_acompanamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->unsignedBigInteger('acompanamiento_id');
            $table->foreign(['establecimiento_id', 'acompanamiento_id'])->references(['establecimiento_id', 'id'])->on('acompanamientos')->restrictOnDelete();
            $table->foreignId('indicador_id')->constrained('indicadores')->restrictOnDelete();
            $table->string('nivel');
            $table->text('retroalimentacion_personal')->nullable();
            $table->timestamps();

            $table->unique(['establecimiento_id', 'id']);
            $table->unique(['acompanamiento_id', 'indicador_id']);
        });

        DB::statement("ALTER TABLE respuestas_acompanamiento ADD CONSTRAINT respuestas_acompanamiento_nivel_check CHECK (nivel IN ('en_inicio', 'en_desarrollo', 'logrado', 'destacado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_acompanamiento');
    }
};

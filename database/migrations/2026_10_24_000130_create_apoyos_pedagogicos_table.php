<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('apoyos_pedagogicos');
    }
};

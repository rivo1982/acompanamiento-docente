<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_acompanamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->unsignedBigInteger('observador_id');
            $table->foreign(['establecimiento_id', 'observador_id'])->references(['establecimiento_id', 'id'])->on('funcionarios')->restrictOnDelete();
            $table->unsignedBigInteger('docente_id');
            $table->foreign(['establecimiento_id', 'docente_id'])->references(['establecimiento_id', 'id'])->on('funcionarios')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['establecimiento_id', 'id']);
            $table->unique(['observador_id', 'docente_id']);
        });

        DB::statement('ALTER TABLE asignaciones_acompanamiento ADD CONSTRAINT asignaciones_acompanamiento_distintos_check CHECK (observador_id <> docente_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_acompanamiento');
    }
};

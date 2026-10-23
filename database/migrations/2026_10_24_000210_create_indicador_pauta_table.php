<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicador_pauta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->unsignedBigInteger('pauta_id');
            $table->foreign(['establecimiento_id', 'pauta_id'])->references(['establecimiento_id', 'id'])->on('pautas')->restrictOnDelete();
            $table->foreignId('indicador_id')->constrained('indicadores')->restrictOnDelete();
            $table->unsignedInteger('posicion');
            $table->timestamps();

            $table->unique(['establecimiento_id', 'id']);
            $table->unique(['pauta_id', 'indicador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_pauta');
    }
};

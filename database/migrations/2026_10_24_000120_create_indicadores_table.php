<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambito_id')->constrained('ambitos')->restrictOnDelete();
            $table->unsignedInteger('numero')->unique();
            $table->string('nombre');
            $table->text('nivel_en_inicio');
            $table->text('nivel_en_desarrollo');
            $table->text('nivel_logrado');
            $table->text('nivel_destacado');
            $table->text('retro_que_significa');
            $table->text('retro_para_logrado');
            $table->text('retro_para_destacado')->nullable();
            $table->text('fuentes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicadores');
    }
};

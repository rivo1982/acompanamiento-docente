<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pautas_base', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique();
            $table->string('nombre');
            $table->text('evidencia')->nullable();
            $table->text('estandares')->nullable();
            $table->text('protocolo')->nullable();
            $table->text('advertencia_uso')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pautas_base');
    }
};

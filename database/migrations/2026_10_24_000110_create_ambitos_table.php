<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pauta_base_id')->constrained('pautas_base')->restrictOnDelete();
            $table->string('nombre');
            $table->unsignedInteger('posicion');
            $table->timestamps();

            $table->unique(['pauta_base_id', 'posicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambitos');
    }
};

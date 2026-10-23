<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->unsignedBigInteger('funcionario_id');
            $table->foreign(['establecimiento_id', 'funcionario_id'])->references(['establecimiento_id', 'id'])->on('funcionarios')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['establecimiento_id', 'id']);
            $table->unique(['establecimiento_id', 'funcionario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observadores');
    }
};

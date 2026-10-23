<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pautas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->string('nombre');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['establecimiento_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pautas');
    }
};

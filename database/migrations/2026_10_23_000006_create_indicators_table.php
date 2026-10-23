<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number')->unique();
            $table->string('name');
            $table->text('level_beginning');
            $table->text('level_developing');
            $table->text('level_achieved');
            $table->text('level_outstanding');
            $table->text('feedback_meaning');
            $table->text('feedback_to_achieved');
            $table->text('feedback_to_outstanding')->nullable();
            $table->text('sources');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};

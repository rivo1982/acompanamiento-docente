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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('rut')->nullable()->unique()->after('name');
            $table->string('role')->default('docente')->after('rut');
            $table->boolean('is_observer')->default(false)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropUnique(['rut']);
            $table->dropColumn(['rut', 'role', 'is_observer']);
        });
    }
};

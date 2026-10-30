<?php

use App\Models\AsignacionAcompanamiento;
use App\Models\Funcionario;
use App\Support\ObservadoresDelEstablecimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDeObservadores($this);
    $this->habilitadoId = funcionarioConFuncion($this->a, 'docente');
    habilitarObservador($this->a, $this->habilitadoId);
    habilitarObservador($this->b, $this->docenteAjenoId);

    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->habilitadoId, 'docente_id' => $this->docenteId]);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->habilitadoId, 'docente_id' => $this->otroDocenteId]);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->directivoId, 'docente_id' => $this->docenteId]);
});

it('lista los observadores automáticos y habilitados del establecimiento activo con sus docentes asignados', function () {
    $filas = collect(app(ObservadoresDelEstablecimiento::class)->observadores())
        ->mapWithKeys(fn (array $fila): array => [(int) $fila['funcionario']->id => [$fila['automatico'], $fila['docentes_asignados']]])
        ->all();

    expect($filas)->toEqualCanonicalizing([
        $this->administradorId => [true, 0],
        $this->directivoId => [true, 1],
        $this->habilitadoId => [false, 2],
    ]);
});

it('marca como automático a un directivo aunque también esté en observadores', function () {
    habilitarObservador($this->a, $this->directivoId);

    $directivo = collect(app(ObservadoresDelEstablecimiento::class)->observadores())
        ->filter(fn (array $fila): bool => (int) $fila['funcionario']->id === $this->directivoId);

    expect($directivo)->toHaveCount(1)
        ->and($directivo->first()['automatico'])->toBeTrue();
});

it('omite a los observadores que ya no están vigentes', function () {
    DB::table('funcionarios')->where('id', $this->habilitadoId)->update(['fecha_termino' => now()->subDay()->toDateString()]);

    $ids = collect(app(ObservadoresDelEstablecimiento::class)->observadores())->map(fn (array $fila): int => (int) $fila['funcionario']->id);

    expect($ids)->not->toContain($this->habilitadoId);
});

it('lista los docentes observables del establecimiento activo con su persona', function () {
    DB::table('funcionarios')->where('id', $this->otroDocenteId)->update(['fecha_termino' => now()->subDay()->toDateString()]);

    $docentes = app(ObservadoresDelEstablecimiento::class)->docentesObservables();

    expect($docentes->map(fn (Funcionario $f): int => (int) $f->id)->all())
        ->toEqualCanonicalizing([$this->docenteId, $this->habilitadoId])
        ->and($docentes->every(fn (Funcionario $f): bool => $f->relationLoaded('persona')))->toBeTrue();
});

it('devuelve los docentes asignados a un observador', function () {
    expect(app(ObservadoresDelEstablecimiento::class)->docentesAsignados($this->habilitadoId))
        ->toEqualCanonicalizing([$this->docenteId, $this->otroDocenteId])
        ->and(app(ObservadoresDelEstablecimiento::class)->docentesAsignados($this->docenteAjenoId))->toBe([]);
});

it('cuenta solo los docentes asignados que hoy se pueden observar, como la lista de selección', function () {
    DB::table('funcionarios')->where('id', $this->otroDocenteId)->update(['fecha_termino' => now()->subDay()->toDateString()]);

    $habilitado = collect(app(ObservadoresDelEstablecimiento::class)->observadores())
        ->first(fn (array $fila): bool => (int) $fila['funcionario']->id === $this->habilitadoId);

    expect($habilitado['docentes_asignados'])->toBe(1);
});

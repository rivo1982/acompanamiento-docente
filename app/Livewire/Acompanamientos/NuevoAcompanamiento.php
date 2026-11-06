<?php

namespace App\Livewire\Acompanamientos;

use App\Actions\IniciarAcompanamiento;
use App\Exceptions\PautaNoDisponible;
use App\Models\AnioEscolar;
use App\Models\AsignacionAcompanamiento;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Funcionario;
use App\Models\Pauta;
use App\Models\Usuario;
use App\Support\CatalogoDePautas;
use App\Support\ObservadoresDelEstablecimiento;
use App\Support\RolesDelModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pantalla "Nuevo acompañamiento" (T7; ruta `acompanamientos.create`). Clase nueva del módulo para entregar a
 * Vinkured. Solo para un observador del establecimiento activo (RolesDelModulo::esObservador, revisado al abrir y al
 * iniciar; IniciarAcompanamiento lo revisa de nuevo con AcompanamientoPolicy::crear).
 *
 * Ofrece los docentes observables que el observador tiene asignados, las pautas del establecimiento con al menos un
 * indicador, los cursos del año escolar más reciente y las asignaturas del catálogo. Si el docente tiene una sola
 * asignación docente en esos cursos, la preselecciona. Al iniciar lleva al formulario de observación.
 */
#[Layout('components.layouts.app', ['titulo' => 'Nuevo acompañamiento'])]
class NuevoAcompanamiento extends Component
{
    /** Ids elegidos, como texto (así los envían los select). */
    public string $docente = '';

    public string $pauta = '';

    public string $curso = '';

    public string $asignatura = '';

    public function mount(): void
    {
        $this->autorizar();
    }

    /**
     * Preselecciona curso y asignatura cuando el docente tiene una sola asignación docente en los cursos ofrecidos; si
     * no, los deja sin elegir para no arrastrar los del docente anterior.
     */
    public function updatedDocente(): void
    {
        $this->curso = '';
        $this->asignatura = '';
        $id = $this->entero($this->docente);

        if ($id === null) {
            return;
        }

        $asignaciones = AsignacionDocente::query()
            ->where('funcionario_id', $id)
            ->where('es_jefatura', false)
            ->whereIn('curso_id', $this->consultaDeCursos()->select('id'))
            ->get(['curso_id', 'asignatura_id']);

        if ($asignaciones->count() === 1) {
            $this->curso = (string) $asignaciones->first()->curso_id;
            $this->asignatura = (string) $asignaciones->first()->asignatura_id;
        }
    }

    public function iniciar(IniciarAcompanamiento $inicios): void
    {
        $this->autorizar();
        $this->resetErrorBag();

        $ids = [];
        $mensajes = [
            'docente' => 'Elegir el docente.',
            'pauta' => 'Elegir la pauta.',
            'curso' => 'Elegir el curso.',
            'asignatura' => 'Elegir la asignatura.',
        ];

        foreach ($mensajes as $campo => $mensaje) {
            $ids[$campo] = $this->entero($this->{$campo});

            if ($ids[$campo] === null) {
                $this->addError($campo, $mensaje);
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->enfocarPrimerError();

            return;
        }

        try {
            $acompanamiento = $inicios->iniciar($this->usuario(), $ids['docente'], $ids['pauta'], $ids['curso'], $ids['asignatura']);
        } catch (ModelNotFoundException|AuthorizationException) {
            $this->addError('docente', 'El docente elegido ya no está asignado para observar. Elegir otro.');
            $this->enfocarPrimerError();

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $errores) {
                $this->addError($campo, (string) ($errores[0] ?? ''));
            }
            $this->enfocarPrimerError();

            return;
        } catch (PautaNoDisponible) {
            // La pauta se eliminó entre la validación y la creación.
            $this->addError('pauta', 'La pauta elegida no está disponible.');
            $this->enfocarPrimerError();

            return;
        }

        $this->redirectRoute('acompanamientos.edit', ['acompanamiento' => $acompanamiento->getKey()]);
    }

    public function render(CatalogoDePautas $catalogo, ObservadoresDelEstablecimiento $lecturas, RolesDelModulo $roles): View
    {
        $asignados = AsignacionAcompanamiento::query()
            ->whereIn('observador_id', $roles->funcionarios())
            ->pluck('docente_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return view('livewire.acompanamientos.nuevo-acompanamiento', [
            'docentes' => $lecturas->docentesObservables()
                ->filter(fn (Funcionario $docente): bool => in_array((int) $docente->getKey(), $asignados, true))
                ->map(fn (Funcionario $docente): array => [
                    'id' => (int) $docente->getKey(),
                    'nombre' => $docente->persona?->nombre_completo ?? 'Sin persona asociada',
                ])
                ->values()->all(),
            'pautas' => $catalogo->pautasDelEstablecimiento()
                ->filter(fn (Pauta $pauta): bool => (int) $pauta->indicadores_count > 0)
                ->values(),
            'cursos' => $this->consultaDeCursos()->with('grado')->get()
                ->sortBy([fn (Curso $a, Curso $b): int => [$a->grado->orden, $a->letra] <=> [$b->grado->orden, $b->letra]])
                ->values(),
            'asignaturas' => DB::table('asignaturas')->orderByRaw('lower(nombre)')->orderBy('id')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Cursos del año escolar más reciente del establecimiento activo.
     *
     * @return Builder<Curso>
     */
    private function consultaDeCursos(): Builder
    {
        return Curso::query()->where('anio_escolar_id', AnioEscolar::query()->orderByDesc('anio')->limit(1)->select('id'));
    }

    private function autorizar(): void
    {
        abort_unless(app(RolesDelModulo::class)->esObservador($this->usuario()), 403);
    }

    private function entero(string $valor): ?int
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    private function enfocarPrimerError(): void
    {
        $primero = collect(['docente', 'pauta', 'curso', 'asignatura'])->first(fn (string $campo): bool => $this->getErrorBag()->has($campo)) ?? 'docente';
        $this->js("document.getElementById('{$primero}')?.focus()");
    }

    private function usuario(): Usuario
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof Usuario, 403);

        return $usuario;
    }
}

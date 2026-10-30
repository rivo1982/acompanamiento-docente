<?php

namespace App\Livewire\Pautas;

use App\Actions\GuardarPauta;
use App\Models\Pauta;
use App\Models\Usuario;
use App\Support\CatalogoDePautas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantallas "Nueva pauta" y "Editar pauta" (T5; rutas `pautas.create` y `pautas.edit`). Clase nueva del módulo para
 * entregar a Vinkured. Solo el administrador del módulo (PautaPolicy::crear / ::actualizar, revisadas al abrir y al
 * guardar; GuardarPauta las revisa de nuevo).
 *
 * Un nombre y los indicadores elegidos del catálogo (CatalogoDePautas::catalogo), agrupados por pauta base y ámbito,
 * con atajos para marcar o desmarcar todos los de una pauta base. GuardarPauta normaliza, valida y ordena; sus
 * errores (`nombre`, `indicadores`) se muestran junto a cada campo. Al guardar lleva a la vista de la pauta con un
 * mensaje de éxito (`estado` de la sesión, que muestra el layout).
 *
 * Al editar, la pauta se busca con el filtro del establecimiento activo: de otro establecimiento o eliminada, 404.
 */
class FormularioDePauta extends Component
{
    /** Pauta que se edita; null al crear. Solo la fija el servidor. */
    #[Locked]
    public ?int $pautaId = null;

    public string $nombre = '';

    /** Ids (texto, como los envían las casillas) de los indicadores marcados. */
    public array $indicadores = [];

    public function mount(mixed $pauta = null): void
    {
        if ($pauta === null) {
            Gate::authorize('crear', Pauta::class);

            return;
        }

        $existente = $this->pautaDelEstablecimiento($pauta);
        Gate::authorize('actualizar', $existente);

        $this->pautaId = (int) $existente->getKey();
        $this->nombre = $existente->nombre;
        $this->indicadores = array_map('strval', app(CatalogoDePautas::class)->indicadoresDe($existente));
    }

    /** Marca todos los indicadores de una pauta base del catálogo, sin desmarcar otros. */
    public function seleccionarTodos(mixed $pautaBaseId, CatalogoDePautas $catalogo): void
    {
        $this->autorizar();
        $this->indicadores = array_values(array_unique([...$this->indicadores, ...$this->idsDeLaBase($pautaBaseId, $catalogo)]));
    }

    /** Desmarca todos los indicadores de una pauta base del catálogo, sin tocar los de otras. */
    public function quitarTodos(mixed $pautaBaseId, CatalogoDePautas $catalogo): void
    {
        $this->autorizar();
        $deLaBase = $this->idsDeLaBase($pautaBaseId, $catalogo);
        $this->indicadores = array_values(array_filter($this->indicadores, fn (mixed $id): bool => ! in_array((string) $id, $deLaBase, true)));
    }

    public function guardar(GuardarPauta $guardado): void
    {
        $this->autorizar();
        $this->resetErrorBag();

        $indicadores = array_values(array_filter($this->indicadores, fn (mixed $id): bool => is_string($id) || is_int($id)));

        try {
            $pauta = $this->pautaId === null
                ? $guardado->crear($this->usuario(), $this->nombre, $indicadores)
                : $guardado->actualizar($this->usuario(), $this->pautaId, $this->nombre, $indicadores);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, implode(' ', $mensajes));
            }
            $primero = array_key_first($e->errors()) === 'indicadores' ? 'indicadores' : 'nombre';
            $this->js("document.getElementById('{$primero}')?.focus()");

            return;
        }

        session()->flash('estado', $this->pautaId === null
            ? "Se creó la pauta {$pauta->nombre}."
            : "Se guardaron los cambios de la pauta {$pauta->nombre}.");

        $this->redirectRoute('pautas.show', ['pauta' => $pauta->getKey()]);
    }

    public function render(CatalogoDePautas $catalogo): View
    {
        $titulo = $this->pautaId === null ? 'Nueva pauta' : 'Editar pauta';
        $marcados = array_map('strval', $this->indicadores);

        $bases = array_map(function (array $base) use ($marcados): array {
            $ids = $this->idsDeIndicadores($base);

            return [...$base, 'ids' => $ids, 'marcados' => count(array_intersect($ids, $marcados))];
        }, $catalogo->catalogo());

        return view('livewire.pautas.formulario-de-pauta', [
            'titulo' => $titulo,
            'bases' => $bases,
            'elegidos' => count(array_unique($marcados)),
        ])->layout('components.layouts.app', ['titulo' => $titulo, 'ancho' => 'formulario']);
    }

    /**
     * @return list<string>
     */
    private function idsDeLaBase(mixed $pautaBaseId, CatalogoDePautas $catalogo): array
    {
        $pautaBaseId = filter_var($pautaBaseId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        foreach ($catalogo->catalogo() as $base) {
            if ($base['id'] === $pautaBaseId) {
                return $this->idsDeIndicadores($base);
            }
        }

        return [];
    }

    /**
     * @param  array{ambitos: list<array{indicadores: list<array{id: int}>}>}  $base
     * @return list<string>
     */
    private function idsDeIndicadores(array $base): array
    {
        $ids = [];

        foreach ($base['ambitos'] as $ambito) {
            foreach ($ambito['indicadores'] as $indicador) {
                $ids[] = (string) $indicador['id'];
            }
        }

        return $ids;
    }

    private function autorizar(): void
    {
        if ($this->pautaId === null) {
            Gate::authorize('crear', Pauta::class);

            return;
        }

        Gate::authorize('actualizar', $this->pautaDelEstablecimiento($this->pautaId));
    }

    private function pautaDelEstablecimiento(mixed $id): Pauta
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return Pauta::query()->find($id) ?? abort(404);
    }

    private function usuario(): Usuario
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof Usuario, 403);

        return $usuario;
    }
}

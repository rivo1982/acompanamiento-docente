<?php

namespace App\Livewire\Pautas;

use App\Actions\EliminarPauta;
use App\Models\Pauta;
use App\Models\Usuario;
use App\Support\CatalogoDePautas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla "Pautas" (T5; ruta `pautas.index`). Clase nueva del módulo para entregar a Vinkured.
 * Lista las pautas vigentes del establecimiento activo (nombre, cantidad de indicadores, última actualización) para
 * cualquier funcionario (PautaPolicy::verCualquiera). El administrador del módulo además crea, edita y elimina; la
 * eliminación pide confirmación en un modal y la revisa PautaPolicy::eliminar (aquí y en EliminarPauta).
 *
 * Las pautas se leen con CatalogoDePautas (filtradas por el establecimiento activo): un id de otro establecimiento
 * no aparece y, si llega en una acción, da 404.
 */
#[Layout('components.layouts.app', ['titulo' => 'Pautas'])]
class Pautas extends Component
{
    public const EXITO = 'exito';

    /** Pauta cuya eliminación se está confirmando. Solo la fija el servidor, tras comprobar que se puede eliminar. */
    #[Locked]
    public ?int $eliminando = null;

    #[Locked]
    public ?string $mensaje = null;

    public function mount(): void
    {
        Gate::authorize('verCualquiera', Pauta::class);
    }

    public function confirmarEliminacion(mixed $id): void
    {
        $this->reset('mensaje');
        $pauta = $this->pautaDelEstablecimiento($id);
        Gate::authorize('eliminar', $pauta);

        $this->eliminando = (int) $pauta->getKey();
    }

    public function cancelar(): void
    {
        $this->eliminando = null;
    }

    public function eliminar(EliminarPauta $eliminaciones): void
    {
        if ($this->eliminando === null) {
            return;
        }

        $pauta = $this->pautaDelEstablecimiento($this->eliminando);
        Gate::authorize('eliminar', $pauta);

        $eliminaciones->eliminar($this->usuario(), (int) $pauta->getKey());

        $this->eliminando = null;
        $this->mensaje = "Se eliminó la pauta {$pauta->nombre}.";
        // Después de que el modal devuelva el foco a quien lo abrió (x-trap lo hace en un setTimeout 0).
        $this->js("setTimeout(() => document.getElementById('mensaje-pautas')?.focus(), 0)");
    }

    public function render(CatalogoDePautas $catalogo): View
    {
        $pautas = $catalogo->pautasDelEstablecimiento();

        return view('livewire.pautas.pautas', [
            'pautas' => $pautas,
            'puedeAdministrar' => Gate::allows('crear', Pauta::class),
            'porEliminar' => $this->eliminando === null ? null : $pautas->firstWhere('id', $this->eliminando),
        ]);
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

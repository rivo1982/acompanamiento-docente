<?php

namespace App\Livewire;

use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Selector de la barra superior: muestra el establecimiento activo y, con más
 * de una membresía activa, permite cambiarlo con un botón explícito.
 *
 * @host-owned Copied from Vinkured app/Livewire/SelectorDeEstablecimiento.php — do not modify here; replace with the host class at integration.
 */
class SelectorDeEstablecimiento extends Component
{
    public string $seleccionado = '';

    public function mount(EstablecimientoActual $actual): void
    {
        $this->seleccionado = (string) $actual->id();
    }

    /**
     * El id llega del navegador y no es de fiar: se valida en el servidor
     * contra las membresías activas del usuario autenticado.
     */
    public function cambiar(EstablecimientoActual $actual): void
    {
        abort_unless($actual->seleccionar($this->seleccionado), 403);

        // El anuncio (role="status") se muestra en la página a la que se redirige.
        session()->flash('estado', 'Establecimiento cambiado a '.$actual->establecimiento()?->nombre.'.');

        $this->redirectRoute('panel');
    }

    public function render(EstablecimientoActual $actual): View
    {
        /** @var Usuario $usuario */
        $usuario = Auth::user();

        return view('livewire.selector-de-establecimiento', [
            'actualNombre' => $actual->establecimiento()?->nombre,
            'membresias' => $usuario->membresiasConAcceso()->with('establecimiento')->get()
                ->sortBy(fn ($membresia) => $membresia->establecimiento->nombre)->values(),
        ]);
    }
}

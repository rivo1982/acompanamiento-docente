<?php

namespace App\Livewire;

use App\Models\Membresia;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** @host-owned Copied from Vinkured app/Livewire/SeleccionarEstablecimiento.php — do not modify here; replace with the host class at integration. */
#[Layout('components.layouts.simple', ['titulo' => 'Elegir establecimiento'])]
class SeleccionarEstablecimiento extends Component
{
    public string $seleccionado = '';

    public function mount(EstablecimientoActual $actual): void
    {
        $membresias = $this->membresias();

        if ($membresias->isEmpty()) {
            $actual->cerrarSesion();
            $this->redirectRoute('login');

            return;
        }

        if ($membresias->count() === 1) {
            $actual->seleccionar($membresias->first()->establecimiento_id);
            $this->redirectRoute('panel');

            return;
        }

        $this->seleccionado = (string) $membresias->first()->establecimiento_id;
    }

    /**
     * El id llega del navegador y no es de fiar: se valida en el servidor contra
     * las membresías activas del usuario autenticado.
     */
    public function entrar(EstablecimientoActual $actual): void
    {
        abort_unless($actual->seleccionar($this->seleccionado), 403);

        $this->redirectRoute('panel');
    }

    public function render(): View
    {
        return view('livewire.seleccionar-establecimiento', ['membresias' => $this->membresias()]);
    }

    /**
     * @return Collection<int, Membresia>
     */
    private function membresias(): Collection
    {
        return $this->usuario()->membresiasConAcceso()->with('establecimiento')->get()
            ->sortBy(fn (Membresia $membresia) => $membresia->establecimiento->nombre)->values();
    }

    private function usuario(): Usuario
    {
        /** @var Usuario */
        return Auth::user();
    }
}

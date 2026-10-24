<?php

namespace App\Livewire\Invitaciones;

use App\Actions\InvitarFuncionario;
use App\Exceptions\InvitacionEnConflicto;
use App\Exceptions\InvitacionNoEncolada;
use App\Support\EstablecimientoActual;
use App\Support\Importacion\ValidadorDePlanilla;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla "Invitar funcionario" (Q18, carga unitaria; ruta `invitaciones.create`). Clase nueva del módulo para
 * entregar a Vinkured. Solo para el Administrador del establecimiento activo (gate `invitar-funcionarios`, revisada al
 * abrir y en cada envío; la acción además relee su membresía de la base).
 *
 * Los campos y sus mensajes son los de la planilla de carga masiva (ValidadorDePlanilla::validarFuncionario). El
 * mensaje de éxito es el mismo tenga o no el correo una cuenta en otro establecimiento (ADR-0005): nombra a la persona
 * con lo que escribió el Administrador, nunca con datos guardados ni con el correo.
 */
#[Layout('components.layouts.app', ['titulo' => 'Invitar funcionario', 'ancho' => 'formulario'])]
class NuevaInvitacion extends Component
{
    public const EXITO = 'exito';

    public const AVISO = 'aviso';

    public const PELIGRO = 'peligro';

    /** Opciones del campo Función: valor de la planilla => etiqueta. */
    public const FUNCIONES = [
        'docente' => 'Docente',
        'directivo' => 'Directivo',
        'asistente' => 'Asistente de la educación',
        'profesional de apoyo' => 'Profesional de apoyo',
    ];

    public string $rut = '';

    public string $nombres = '';

    public string $primer_apellido = '';

    public string $segundo_apellido = '';

    public string $correo = '';

    public string $telefono = '';

    public string $funcion = '';

    /** Mensaje de página tras el último envío (éxito, aviso o error que no es de un campo). */
    #[Locked]
    public ?string $mensaje = null;

    #[Locked]
    public ?string $tipoDeMensaje = null;

    public function mount(): void
    {
        Gate::authorize('invitar-funcionarios');
    }

    public function invitar(ValidadorDePlanilla $validador, InvitarFuncionario $invitaciones, EstablecimientoActual $actual): void
    {
        Gate::authorize('invitar-funcionarios');

        $this->resetErrorBag();
        $this->reset('mensaje', 'tipoDeMensaje');

        $validacion = $validador->validarFuncionario(array_map(fn (string $campo): string => $this->{$campo}, ValidadorDePlanilla::CAMPOS));

        if ($validacion->fila === null) {
            foreach ($validacion->erroresPorCampo as $campo => $mensajes) {
                $this->addError($campo, implode(' ', $mensajes));
            }
            $primero = array_key_first($validacion->erroresPorCampo);
            $this->js("document.getElementById('".(in_array($primero, ValidadorDePlanilla::CAMPOS, true) ? $primero : 'rut')."').focus()");

            return;
        }

        $nombre = $validacion->fila->nombres.' '.$validacion->fila->primerApellido;

        try {
            $invitaciones->invitar($actual->membresia() ?? abort(403), $validacion->fila);
            $this->mostrarMensaje(self::EXITO, "Invitación enviada a {$nombre}.");
            $this->limpiarFormulario();
        } catch (InvitacionEnConflicto $e) {
            // Mensajes que solo hablan de este establecimiento; los datos quedan para corregirlos.
            $this->mostrarMensaje(self::PELIGRO, $e->getMessage());
        } catch (InvitacionNoEncolada) {
            $this->mostrarMensaje(self::AVISO, 'La invitación quedó registrada, pero su correo no se pudo programar. Reenviarla más tarde desde las invitaciones pendientes.');
            $this->limpiarFormulario();
        }
    }

    public function render(): View
    {
        return view('livewire.invitaciones.nueva-invitacion');
    }

    private function mostrarMensaje(string $tipo, string $mensaje): void
    {
        $this->tipoDeMensaje = $tipo;
        $this->mensaje = $mensaje;
        $this->js("document.getElementById('mensaje-invitacion').focus()");
    }

    private function limpiarFormulario(): void
    {
        $this->reset(ValidadorDePlanilla::CAMPOS);
    }
}

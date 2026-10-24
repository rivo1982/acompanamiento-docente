{{-- Invitar funcionario (carga unitaria). Mismos campos y orden que la planilla de carga masiva. --}}
<div>
    <x-page-header
        titulo="Invitar funcionario"
        descripcion="La persona recibe un correo con un enlace para activar su acceso a este establecimiento."
    >
        <x-slot:acciones>
            <x-button :href="route('invitaciones.index')" variante="secundario">Ver invitaciones pendientes</x-button>
            <x-button :href="route('importaciones.index')" variante="secundario">Carga masiva</x-button>
        </x-slot:acciones>
    </x-page-header>

    {{--
        Mensaje del último envío, como en "Aceptar invitación" (C4.1): un solo mecanismo, el rol del propio mensaje
        (Éxito como estado; aviso y error como alerta) más el foco que le da el servidor. Sin región aria-live
        envolvente, que lo anunciaría dos veces.
    --}}
    @if ($mensaje !== null)
        <div
            id="mensaje-invitacion"
            tabindex="-1"
            role="{{ $tipoDeMensaje === \App\Livewire\Invitaciones\NuevaInvitacion::EXITO ? 'status' : 'alert' }}"
            @class([
                'mb-6 rounded-control border p-3 text-tabla text-pretty focus:outline-none',
                'border-exito bg-exito-fondo text-exito' => $tipoDeMensaje === \App\Livewire\Invitaciones\NuevaInvitacion::EXITO,
                'border-aviso bg-aviso-fondo text-aviso' => $tipoDeMensaje === \App\Livewire\Invitaciones\NuevaInvitacion::AVISO,
                'border-peligro bg-peligro-fondo text-peligro' => $tipoDeMensaje === \App\Livewire\Invitaciones\NuevaInvitacion::PELIGRO,
            ])
        >{{ $mensaje }}</div>
    @endif

    <x-card>
        <form method="post" wire:submit="invitar" wire:loading.attr="aria-busy" wire:target="invitar" class="flex flex-col gap-6" novalidate>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <x-campo
                    id="rut"
                    etiqueta="RUT"
                    :obligatorio="true"
                    ayuda="Con dígito verificador, por ejemplo 12.345.678-5."
                    :error="$errors->first('rut')"
                    wire:model="rut"
                    name="rut"
                    autocomplete="off"
                    spellcheck="false"
                    autocapitalize="characters"
                    class="tabular-nums"
                />

                <x-campo
                    id="nombres"
                    etiqueta="Nombres"
                    :obligatorio="true"
                    :error="$errors->first('nombres')"
                    wire:model="nombres"
                    name="nombres"
                    autocomplete="off"
                    maxlength="{{ \App\Support\Importacion\ValidadorDePlanilla::MAXIMO_DEL_NOMBRE }}"
                />

                <x-campo
                    id="primer_apellido"
                    etiqueta="Primer apellido"
                    :obligatorio="true"
                    :error="$errors->first('primer_apellido')"
                    wire:model="primer_apellido"
                    name="primer_apellido"
                    autocomplete="off"
                    maxlength="{{ \App\Support\Importacion\ValidadorDePlanilla::MAXIMO_DEL_NOMBRE }}"
                />

                <x-campo
                    id="segundo_apellido"
                    etiqueta="Segundo apellido"
                    :error="$errors->first('segundo_apellido')"
                    wire:model="segundo_apellido"
                    name="segundo_apellido"
                    autocomplete="off"
                    maxlength="{{ \App\Support\Importacion\ValidadorDePlanilla::MAXIMO_DEL_NOMBRE }}"
                />

                <x-campo
                    id="correo"
                    type="email"
                    etiqueta="Correo"
                    :obligatorio="true"
                    :error="$errors->first('correo')"
                    wire:model="correo"
                    name="correo"
                    autocomplete="off"
                    spellcheck="false"
                    autocapitalize="off"
                />

                <x-campo
                    id="telefono"
                    type="tel"
                    etiqueta="Teléfono"
                    :error="$errors->first('telefono')"
                    wire:model="telefono"
                    name="telefono"
                    autocomplete="off"
                    maxlength="{{ \App\Support\Importacion\ValidadorDePlanilla::MAXIMO_DEL_TELEFONO }}"
                    class="tabular-nums"
                />

                <x-campo
                    id="funcion"
                    elemento="select"
                    etiqueta="Función"
                    :obligatorio="true"
                    :error="$errors->first('funcion')"
                    wire:model="funcion"
                    name="funcion"
                >
                    <option value="">Elegir función</option>
                    @foreach (\App\Livewire\Invitaciones\NuevaInvitacion::FUNCIONES as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </x-campo>
            </div>

            <div>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="invitar">
                    <span wire:loading.remove wire:target="invitar">Enviar invitación</span>
                    <span wire:loading wire:target="invitar">Enviando invitación…</span>
                </x-button>
            </div>
        </form>
    </x-card>
</div>

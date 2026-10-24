{{-- Pantalla del enlace de invitación. Nunca muestra el token, el correo ni el RUT de la persona invitada. --}}
<div>
    <x-page-header
        titulo="Aceptar invitación"
        :descripcion="$establecimiento ? 'Establecimiento: '.$establecimiento.'.' : null"
    />

    <x-card>
        @if ($estado === \App\Livewire\Invitaciones\AceptarInvitacion::NUEVA_CONTRASENA)
            {{-- Requiere JavaScript, como el inicio de sesión: method="post" evita que la contraseña viaje en la URL. --}}
            <form method="post" wire:submit="aceptar" wire:loading.attr="aria-busy" wire:target="aceptar" class="flex flex-col gap-6" novalidate>
                <p class="text-base text-pretty">Crear una contraseña para entrar a VINKURED con el correo de la invitación.</p>

                <x-campo
                    id="contrasena"
                    name="contrasena"
                    type="password"
                    etiqueta="Contraseña nueva"
                    :obligatorio="true"
                    :ayuda="$reglaDeContrasena"
                    :error="$errors->first('contrasena')"
                    wire:model="contrasena"
                    autocomplete="new-password"
                />

                <x-campo
                    id="contrasena_confirmation"
                    name="contrasena_confirmation"
                    type="password"
                    etiqueta="Confirmar contraseña"
                    :obligatorio="true"
                    :error="$errors->first('contrasena_confirmation')"
                    wire:model="contrasena_confirmation"
                    autocomplete="new-password"
                />

                <div>
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="aceptar">
                        <span wire:loading.remove wire:target="aceptar">Aceptar invitación</span>
                        <span wire:loading wire:target="aceptar">Aceptando invitación…</span>
                    </x-button>
                </div>
            </form>
        @elseif ($estado === \App\Livewire\Invitaciones\AceptarInvitacion::CONFIRMAR)
            <form method="post" wire:submit="aceptar" wire:loading.attr="aria-busy" wire:target="aceptar" class="flex flex-col gap-6">
                <p class="text-base text-pretty">Aceptar la invitación para trabajar en este establecimiento con la cuenta de la sesión iniciada.</p>

                <div>
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="aceptar">
                        <span wire:loading.remove wire:target="aceptar">Aceptar invitación</span>
                        <span wire:loading wire:target="aceptar">Aceptando invitación…</span>
                    </x-button>
                </div>
            </form>
        @elseif ($estado === \App\Livewire\Invitaciones\AceptarInvitacion::ACEPTADA)
            <div class="flex flex-col gap-6">
                <div id="estado-invitacion" role="status" tabindex="-1" class="rounded-control border border-exito bg-exito-fondo p-3 text-tabla text-exito focus:outline-none">
                    Invitación aceptada. Ya es posible iniciar sesión con el correo de la invitación y la contraseña nueva.
                </div>
                <div>
                    <x-button :href="route('login')">Iniciar sesión</x-button>
                </div>
            </div>
        @elseif ($estado === \App\Livewire\Invitaciones\AceptarInvitacion::REQUIERE_SESION)
            <div class="flex flex-col gap-6">
                <div id="estado-invitacion" role="alert" tabindex="-1" class="rounded-control border border-aviso bg-aviso-fondo p-3 text-tabla text-aviso focus:outline-none">
                    <p>Esta invitación es para una cuenta que ya existe.</p>
                    <p>Iniciar sesión con esa cuenta y volver a abrir el enlace de la invitación.</p>
                </div>
                <div>
                    <x-button :href="route('login')">Iniciar sesión</x-button>
                </div>
            </div>
        @elseif ($estado === \App\Livewire\Invitaciones\AceptarInvitacion::OTRA_CUENTA)
            <div class="flex flex-col gap-6">
                <div id="estado-invitacion" role="alert" tabindex="-1" class="rounded-control border border-aviso bg-aviso-fondo p-3 text-tabla text-aviso focus:outline-none">
                    <p>La sesión iniciada es de otra cuenta.</p>
                    <p>Cerrar sesión y volver a abrir el enlace de la invitación.</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button type="submit">Cerrar sesión</x-button>
                </form>
            </div>
        @else
            <div id="estado-invitacion" role="alert" tabindex="-1" class="rounded-control border border-aviso bg-aviso-fondo p-3 text-tabla text-aviso focus:outline-none">
                <p>La invitación no es válida o ya venció.</p>
                <p>Pedir una nueva invitación al Administrador del establecimiento.</p>
            </div>
        @endif
    </x-card>
</div>

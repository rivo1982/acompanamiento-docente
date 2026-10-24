{{-- @host-owned Copied from Vinkured resources/views/livewire/login.blade.php — do not modify here; replace with the host class at integration. --}}
<div>
    <x-page-header titulo="Iniciar sesión" descripcion="Ingresar con el correo y la contraseña de VINKURED." />

@php
    $mensajeAlerta = $errors->first('credenciales') ?: ($errors->first('bloqueo') ?: session('aviso'));
@endphp

    <x-card>
        {{-- Iniciar sesión requiere JavaScript. method="post" evita que las credenciales viajen en la URL
             si Livewire no carga; sin JS el envío responde 405 y no inicia sesión. --}}
        <form method="post" wire:submit="iniciarSesion" wire:loading.attr="aria-busy" wire:target="iniciarSesion" class="flex flex-col gap-6" novalidate>
            {{-- Peligro para credenciales; Aviso para bloqueo y para el aviso de acceso denegado (flash). --}}
            @if ($mensajeAlerta)
                <div
                    id="alerta-login"
                    role="alert"
                    tabindex="-1"
                    @class([
                        'rounded-control border p-3 text-tabla focus:outline-none',
                        'border-peligro bg-peligro-fondo text-peligro' => $errors->has('credenciales'),
                        'border-aviso bg-aviso-fondo text-aviso' => ! $errors->has('credenciales'),
                    ])
                >{{ $mensajeAlerta }}</div>
            @endif

            <x-campo
                id="correo"
                name="correo"
                type="email"
                etiqueta="Correo"
                :obligatorio="true"
                :error="$errors->first('correo')"
                wire:model="correo"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
            />

            <x-campo
                id="contrasena"
                name="password"
                type="password"
                etiqueta="Contraseña"
                :obligatorio="true"
                :error="$errors->first('contrasena')"
                wire:model="contrasena"
                autocomplete="current-password"
            />

            <div>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="iniciarSesion">
                    <span wire:loading.remove wire:target="iniciarSesion">Iniciar sesión</span>
                    <span wire:loading wire:target="iniciarSesion">Iniciando sesión…</span>
                </x-button>
            </div>
        </form>
    </x-card>
</div>

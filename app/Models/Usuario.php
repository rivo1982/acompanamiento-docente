<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** @host-owned Copied from Vinkured app/Models/Usuario.php — do not modify here; replace with the host class at integration. */
#[Fillable(['correo', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    /**
     * @return HasMany<Membresia, $this>
     */
    public function membresias(): HasMany
    {
        return $this->hasMany(Membresia::class);
    }

    /**
     * Membresías que dan acceso a la aplicación (activas y con establecimiento).
     *
     * @return HasMany<Membresia, $this>
     */
    public function membresiasConAcceso(): HasMany
    {
        return $this->membresias()->conAcceso();
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'es_soporte_vinkured' => 'boolean',
        ];
    }
}

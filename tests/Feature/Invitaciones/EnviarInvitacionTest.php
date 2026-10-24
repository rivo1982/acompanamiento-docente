<?php

use App\Actions\ReenviarInvitacion;
use App\Exceptions\EnvioDeInvitacionFallido;
use App\Exceptions\InvitacionImposible;
use App\Jobs\EnviarInvitacion;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FuncionesGenericasSeeder::class);
    $this->a = newEstablecimiento('1000', 'Colegio A');
    $this->b = newEstablecimiento('2000', 'Colegio B');
});

it('nunca guarda el token en texto plano: ni en la membresía ni en la cola', function () {
    Mail::fake();
    config(['queue.default' => 'database']);
    $membresia = invitar(administradorDe($this->a));

    $carga = DB::table('jobs')->value('payload');
    expect(DB::table('jobs')->count())->toBe(1)
        ->and($carga)->not->toContain('ana@ejemplo.cl')
        ->and($carga)->not->toContain($membresia->invitacion_hash);
    Mail::assertNothingSent();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    $token = tokenDeLaUltimaInvitacion();
    $fila = DB::table('membresias')->find($membresia->id);
    expect(json_encode($fila))->not->toContain($token)
        ->and($fila->invitacion_hash)->toBe(hash('sha256', $token))
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('envía un correo de texto que trata de usted, con el establecimiento, el vencimiento y el enlace', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));

    $membresia = invitar(administradorDe($this->a));

    $correo = ultimaInvitacion();
    $token = tokenDeLaUltimaInvitacion();
    expect($token)->toMatch('/^[A-Za-z0-9_-]{43}$/')
        ->and($correo->enlace)->toBe(url('/invitaciones/'.$token))
        ->and($correo->enlace)->not->toContain($membresia->invitacion_hash)
        ->and(hash('sha256', $token))->toBe($membresia->invitacion_hash)
        ->and($correo->envelope()->subject)->toBe('Invitación a VINKURED de Colegio A')
        ->and($correo->content()->text)->toBe('mail.invitacion')
        ->and($correo->content()->view)->toBeNull();

    $correo->assertSeeInText('usted')
        ->assertSeeInText('Colegio A')
        ->assertSeeInText('31-10-2026 12:00')
        ->assertSeeInText($correo->enlace)
        ->assertDontSeeInText($membresia->invitacion_hash);
});

it('encola un trabajo único por membresía que lleva solo ids', function () {
    Queue::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);

    app(ReenviarInvitacion::class)->reenviar($administrador, $membresia->id);

    Queue::assertPushed(EnviarInvitacion::class, 1);
    $trabajo = Queue::pushed(EnviarInvitacion::class)->first();
    $datos = collect((new ReflectionClass($trabajo))->getConstructor()->getParameters())->map->getName()->all();

    expect($datos)->toBe(['establecimientoId', 'membresiaId'])
        ->and($trabajo->establecimientoId)->toBe($this->a)
        ->and($trabajo->membresiaId)->toBe($membresia->id)
        ->and($trabajo->uniqueId())->toBe("{$this->a}:{$membresia->id}")
        ->and($trabajo)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($trabajo)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($trabajo->tries)->toBe(3)
        ->and($trabajo->backoff())->toBe([60, 300])
        ->and(serialize($trabajo))->not->toContain('ana@ejemplo.cl');
});

it('no encola nada si la transacción de la invitación falla', function () {
    Queue::fake();
    $administrador = administradorDe($this->a);
    DB::table('funciones')->where('clave', 'docente')->delete();

    expect(fn () => invitar($administrador))->toThrow(LogicException::class);
    Queue::assertNothingPushed();
    expect(DB::table('membresias')->where('estado', 'invitada')->count())->toBe(0);
});

it('reemplaza el error del transporte sin la dirección, para reintentar', function () {
    Queue::fake();
    $membresia = invitar(administradorDe($this->a));
    Mail::shouldReceive('to')->andThrow(new RuntimeException('No se pudo enviar a ana@ejemplo.cl'));

    try {
        app()->call([new EnviarInvitacion($this->a, $membresia->id), 'handle']);
        $this->fail('Se esperaba EnvioDeInvitacionFallido.');
    } catch (EnvioDeInvitacionFallido $e) {
        expect($e->getMessage())->not->toContain('ana@ejemplo.cl')
            ->and($e->getMessage())->toContain(RuntimeException::class)
            ->and($e->getPrevious())->toBeNull();
    }
});

it('no envía una invitación de otro establecimiento', function () {
    Queue::fake();
    Mail::fake();
    $membresia = invitar(administradorDe($this->a));

    expect(fn () => app()->call([new EnviarInvitacion($this->b, $membresia->id), 'handle']))
        ->toThrow(InvitacionImposible::class);
    Mail::assertNothingSent();
    expect($membresia->fresh()->invitacion_hash)->toBe($membresia->invitacion_hash);
});

it('registra el fallo definitivo solo con ids', function () {
    Log::spy();

    (new EnviarInvitacion(5, 9))->failed(new RuntimeException('ana@ejemplo.cl'));

    Log::shouldHaveReceived('error')->once()->with('El envío de una invitación falló definitivamente.', [
        'establecimiento_id' => 5,
        'membresia_id' => 9,
        'excepcion' => RuntimeException::class,
    ]);
});

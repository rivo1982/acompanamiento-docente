<?php

use App\Actions\ReenviarInvitacion;
use App\Exceptions\EnvioDeInvitacionFallido;
use App\Exceptions\InvitacionImposible;
use App\Jobs\EnviarInvitacion;
use App\Mail\InvitacionAVinkured;
use App\Models\Membresia;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

it('encola un trabajo con ids y un sello opaco de la versión de la invitación, sin datos personales', function () {
    Queue::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);

    app(ReenviarInvitacion::class)->reenviar($administrador, $membresia->id);

    Queue::assertPushed(EnviarInvitacion::class, 2);
    $trabajo = Queue::pushed(EnviarInvitacion::class)->last();
    $datos = collect((new ReflectionClass($trabajo))->getConstructor()->getParameters())->map->getName()->all();
    $hashVigente = $membresia->fresh()->invitacion_hash;

    expect($datos)->toBe(['establecimientoId', 'membresiaId', 'sello'])
        ->and($trabajo->establecimientoId)->toBe($this->a)
        ->and($trabajo->membresiaId)->toBe($membresia->id)
        ->and($trabajo->sello)->toBe(EnviarInvitacion::selloDe($hashVigente))
        ->and($trabajo->sello)->not->toBe($hashVigente)
        ->and($trabajo)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($trabajo)->not->toBeInstanceOf(ShouldBeUnique::class)
        ->and($trabajo->tries)->toBe(3)
        ->and($trabajo->backoff())->toBe([60, 300])
        ->and(serialize($trabajo))->not->toContain('ana@ejemplo.cl')
        ->and(serialize($trabajo))->not->toContain($hashVigente);
});

it('un trabajo reemplazado por un reenvío no envía nada y el último envía el enlace vigente', function () {
    Queue::fake();
    Mail::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);
    app(ReenviarInvitacion::class)->reenviar($administrador, $membresia->id);
    [$primero, $segundo] = Queue::pushed(EnviarInvitacion::class)->all();

    app()->call([$primero, 'handle']);
    Mail::assertNothingSent();

    app()->call([$segundo, 'handle']);
    Mail::assertSentCount(1);
    expect($membresia->fresh()->invitacion_hash)->toBe(hash('sha256', tokenDeLaUltimaInvitacion()));
});

it('un reenvío en medio de un envío deja vigente el último enlace enviado', function () {
    Queue::fake();
    Mail::fake();
    $administrador = administradorDe($this->a);
    $membresia = invitar($administrador);
    $enCurso = Queue::pushed(EnviarInvitacion::class)->first();

    // The resend lands after the in-flight job rotated the token and before its mail leaves. In production the
    // resend waits for the job's row lock (held until the mail is sent); here it runs on the same connection.
    $reenviado = false;
    Event::listen('eloquent.saved: '.Membresia::class, function () use (&$reenviado, $administrador, $membresia): void {
        if (! $reenviado) {
            $reenviado = true;
            app(ReenviarInvitacion::class)->reenviar($administrador, $membresia->id);
        }
    });

    app()->call([$enCurso, 'handle']);
    $pendientes = Queue::pushed(EnviarInvitacion::class)->slice(1);
    expect($reenviado)->toBeTrue()->and($pendientes)->toHaveCount(1);
    app()->call([$pendientes->first(), 'handle']);

    Mail::assertSentCount(2);
    expect($membresia->fresh()->invitacion_hash)->toBe(hash('sha256', tokenDeLaUltimaInvitacion()));
});

it('un trabajo repetido después de un envío exitoso no vuelve a enviar ni rota el enlace', function () {
    Queue::fake();
    Mail::fake();
    $membresia = invitar(administradorDe($this->a));
    $trabajo = Queue::pushed(EnviarInvitacion::class)->first();

    app()->call([$trabajo, 'handle']);
    $hashEnviado = $membresia->fresh()->invitacion_hash;
    app()->call([$trabajo, 'handle']);

    Mail::assertSentCount(1);
    expect($membresia->fresh()->invitacion_hash)->toBe($hashEnviado)
        ->and($hashEnviado)->toBe(hash('sha256', tokenDeLaUltimaInvitacion()));
});

it('si el correo falla no rota el enlace guardado y el reintento lo envía', function () {
    Queue::fake();
    $membresia = invitar(administradorDe($this->a));
    $hashAntes = $membresia->fresh()->invitacion_hash;
    $trabajo = Queue::pushed(EnviarInvitacion::class)->first();
    $intentos = 0;
    $enviada = null;
    Mail::shouldReceive('to')->andReturnUsing(function () use (&$intentos, &$enviada) {
        if (++$intentos === 1) {
            throw new RuntimeException('caído');
        }

        return Mockery::mock()->shouldReceive('send')->andReturnUsing(function (InvitacionAVinkured $correo) use (&$enviada): void {
            $enviada = $correo;
        })->getMock();
    });

    expect(fn () => app()->call([$trabajo, 'handle']))->toThrow(EnvioDeInvitacionFallido::class)
        ->and($membresia->fresh()->invitacion_hash)->toBe($hashAntes);

    app()->call([$trabajo, 'handle']);

    $token = basename((string) parse_url($enviada->enlace, PHP_URL_PATH));
    expect($intentos)->toBe(2)
        ->and($membresia->fresh()->invitacion_hash)->toBe(hash('sha256', $token));
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
        app()->call([Queue::pushed(EnviarInvitacion::class)->first(), 'handle']);
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

    $trabajo = Queue::pushed(EnviarInvitacion::class)->first();

    expect(fn () => app()->call([new EnviarInvitacion($this->b, $membresia->id, $trabajo->sello), 'handle']))
        ->toThrow(InvitacionImposible::class);
    Mail::assertNothingSent();
    expect($membresia->fresh()->invitacion_hash)->toBe($membresia->invitacion_hash);
});

it('registra el fallo definitivo solo con ids', function () {
    Log::spy();

    (new EnviarInvitacion(5, 9, str_repeat('a', 64)))->failed(new RuntimeException('ana@ejemplo.cl'));

    Log::shouldHaveReceived('error')->once()->with('El envío de una invitación falló definitivamente.', [
        'establecimiento_id' => 5,
        'membresia_id' => 9,
        'excepcion' => RuntimeException::class,
    ]);
});

/** Simulates a job queued before `sello` existed: the serialized payload simply lacks the property. */
function trabajoSinSello(EnviarInvitacion $trabajo): EnviarInvitacion
{
    $serializado = preg_replace('/s:5:"sello";s:64:"[0-9a-f]{64}";/', '', serialize($trabajo), 1, $reemplazos);
    expect($reemplazos)->toBe(1);
    $serializado = preg_replace_callback(
        '/^O:(\d+):"([^"]+)":(\d+):/',
        fn (array $m): string => 'O:'.$m[1].':"'.$m[2].'":'.((int) $m[3] - 1).':',
        $serializado,
    );

    return unserialize($serializado);
}

it('un trabajo encolado antes de existir el sello no envía ni rota el enlace y registra solo ids', function () {
    Queue::fake();
    Mail::fake();
    Log::spy();
    $membresia = invitar(administradorDe($this->a));
    $antiguo = trabajoSinSello(Queue::pushed(EnviarInvitacion::class)->first());

    app()->call([$antiguo, 'handle']);

    Mail::assertNothingSent();
    expect($membresia->fresh()->invitacion_hash)->toBe($membresia->invitacion_hash);
    Log::shouldHaveReceived('warning')->once()->with('Se descartó un envío de invitación encolado sin sello; reenvíela.', [
        'establecimiento_id' => $this->a,
        'membresia_id' => $membresia->id,
    ]);
});

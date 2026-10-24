<?php

/*
 * UI foundation copied from Vinkured (@host-owned). Token and font cases are ported from Vinkured
 * tests/Feature/Ui/TokensYFuentesTest.php and CssCompiladoTest.php; the build runs into a temporary
 * directory so public/build is never touched.
 */

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Process\Process;

beforeEach(fn () => $this->withoutVite());

/** Built CSS of resources/css/app.css, compiled once per process into a temporary directory. */
function cssCompiladoDelModulo(): string
{
    static $css = null;

    if ($css !== null) {
        return $css;
    }

    $destino = sys_get_temp_dir().'/acompanamiento-vite-'.bin2hex(random_bytes(8));
    $proceso = new Process(['npx', '--no-install', 'vite', 'build', '--outDir', $destino, '--emptyOutDir'], base_path(), null, null, 180);
    $proceso->run();

    try {
        if (! $proceso->isSuccessful() || ! is_file($destino.'/manifest.json')) {
            throw new RuntimeException("Vite build failed in {$destino}:\n".$proceso->getErrorOutput().$proceso->getOutput());
        }

        $entrada = json_decode((string) file_get_contents($destino.'/manifest.json'), true)['resources/css/app.css']['file'];

        return $css = (string) file_get_contents($destino.'/'.$entrada);
    } finally {
        (new Process(['rm', '-rf', $destino]))->run();
    }
}

/**
 * Host files copied verbatim from Vinkured, keyed by repository-relative path.
 *
 * @return list<string>
 */
function archivosCopiadosDeVinkured(): array
{
    return [
        'CONTEXT.md', 'docs/diseno.md', 'resources/css/app.css', 'resources/js/app.js', 'vite.config.js',
        'app/Livewire/Login.php', 'app/Livewire/SeleccionarEstablecimiento.php', 'app/Livewire/SelectorDeEstablecimiento.php',
        'resources/views/livewire/login.blade.php', 'resources/views/livewire/seleccionar-establecimiento.blade.php',
        'resources/views/livewire/selector-de-establecimiento.blade.php',
        'resources/views/components/layouts/app.blade.php', 'resources/views/components/layouts/simple.blade.php',
        ...array_map(fn (string $c): string => "resources/views/components/{$c}.blade.php",
            ['badge', 'button', 'campo', 'card', 'modal', 'page-header', 'paginacion', 'tabla']),
    ];
}

it('tags every copied host file with its Vinkured origin', function (string $ruta) {
    expect(file_get_contents(base_path($ruta)))
        ->toContain("@host-owned Copied from Vinkured {$ruta} — do not modify here; replace with the host class at integration.");
})->with(archivosCopiadosDeVinkured());

it('defines the brief tokens with their exact hex values in the source and the built CSS', function (string $token, string $hex) {
    expect(file_get_contents(resource_path('css/app.css')))->toContain("--color-{$token}: {$hex};")
        ->and(cssCompiladoDelModulo())->toContain("--color-{$token}:{$hex}");
})->with([
    ['fondo', '#f8fafc'], ['superficie-atenuada', '#f1f5f9'], ['texto', '#0f172a'], ['acento', '#0f5c63'],
    ['borde-campo', '#64748b'], ['peligro', '#b91c1c'], ['aviso', '#92400e'], ['exito', '#166534'],
]);

it('self-hosts Inter in the built CSS without external font hosts', function () {
    $css = cssCompiladoDelModulo();

    expect($css)->toContain('@font-face')->toContain('Inter Variable')->toContain('.woff2')
        ->and($css)->not->toMatch('~url\(\s*["\']?(https?:)?//~i')
        ->and(strtolower(file_get_contents(base_path('vite.config.js'))))->not->toContain('fonts');
});

it('renders the simple layout in Chilean Spanish with skip link, main landmark and Livewire assets', function () {
    $html = Blade::render('<x-layouts.simple titulo="Prueba"><p>Contenido</p></x-layouts.simple>');

    expect($html)
        ->toContain('<html lang="es-CL">')
        ->toContain('<title>Prueba - VINKURED</title>')
        ->toContain('href="#contenido"')
        ->toContain('<main id="contenido"')
        ->toContain('<p>Contenido</p>')
        ->toContain('livewire');
});

it('renders the host app layout for an active user once its host helpers are present', function () {
    $establecimiento = newEstablecimiento('2000', 'Colegio Ficticio');
    [$usuario] = usuarioConRoles($establecimiento);
    activar($usuario, $establecimiento);

    $html = Blade::render('<x-layouts.app titulo="Prueba"><p>Contenido</p></x-layouts.app>');

    expect($html)
        ->toContain('<html lang="es-CL">')
        ->toContain('<main id="contenido"')
        ->toContain('<p>Contenido</p>')
        ->toContain(route('panel'));
})->uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

<?php

use App\Support\DiasHabiles;

/*
 * The business-day classes are copies of Vinkured's (@host-owned): tagged, and bound like Vinkured's
 * AppServiceProvider (scoped: one memoized calendar per request or job).
 */

it('tags the business-day copies as host-owned', function (string $ruta) {
    expect(file_get_contents(base_path($ruta)))
        ->toContain("@host-owned Copied from Vinkured {$ruta} — do not modify here; replace with the host class at integration.");
})->with(['app/Support/DiasHabiles.php', 'app/Support/EstadoDePlazo.php', 'app/Models/DiaNoHabil.php']);

it('binds DiasHabiles as scoped, like Vinkured', function () {
    $primera = app(DiasHabiles::class);

    expect(app(DiasHabiles::class))->toBe($primera);

    app()->forgetScopedInstances();

    expect(app(DiasHabiles::class))->not->toBe($primera);
});

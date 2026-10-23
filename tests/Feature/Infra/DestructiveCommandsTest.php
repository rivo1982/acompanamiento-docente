<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;

afterEach(function () {
    DB::prohibitDestructiveCommands(false);
});

it('prohibits destructive database commands outside the testing environment', function (string $command) {
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();

    // The prohibition check runs before any confirmation or database access;
    // the unknown connection is a second safety net should it ever not.
    $this->artisan($command, ['--database' => 'prohibited_probe'])
        ->expectsOutputToContain('prohibited')
        ->assertFailed();
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe']);

<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run on Tests\TestCase, which refuses to refresh any database
| other than the isolated acompanamiento_test schema. RefreshDatabase is not
| applied globally: each test file opts in explicitly.
|
*/

pest()->extend(TestCase::class)->in('Feature');

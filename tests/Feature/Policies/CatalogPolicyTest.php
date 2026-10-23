<?php

namespace Tests\Feature\Policies;

use App\Models\Indicator;
use App\Models\RubricTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CatalogPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_view_the_catalog(): void
    {
        $template = RubricTemplate::factory()->create();
        $indicator = Indicator::factory()->create();

        foreach ([User::factory()->teacher()->create(), User::factory()->organizationAdmin()->create()] as $user) {
            $this->assertTrue($user->can('viewAny', RubricTemplate::class));
            $this->assertTrue($user->can('view', $template));
            $this->assertTrue($user->can('viewAny', Indicator::class));
            $this->assertTrue($user->can('view', $indicator));
        }
    }

    public function test_guest_cannot_view_the_catalog(): void
    {
        $template = RubricTemplate::factory()->create();
        $indicator = Indicator::factory()->create();

        $this->assertFalse(Gate::forUser(null)->allows('view', $template));
        $this->assertFalse(Gate::forUser(null)->allows('view', $indicator));
    }
}

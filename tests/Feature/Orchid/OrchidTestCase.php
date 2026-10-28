<?php

namespace Tests\Feature\Orchid;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class OrchidTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, bool>
     */
    protected function allPermissions(): array
    {
        return [
            'platform.index' => true,
            'platform.systems.roles' => true,
            'platform.systems.users' => true,
            'platform.systems.attachment' => true,
            'platform.marketplace.categories' => true,
            'platform.marketplace.sites' => true,
            'platform.marketplace.conversations' => true,
            'platform.marketplace.messages' => true,
            'platform.marketplace.payments' => true,
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    protected function admin(array $permissions = []): User
    {
        return User::factory()->create([
            'permissions' => array_merge(
                ['platform.index' => true],
                $permissions === [] ? $this->allPermissions() : $permissions,
            ),
        ]);
    }
}

<?php

namespace Tests\Concerns;

use App\Models\User;

trait ActsAsAdmin
{
    /** Signs in through the separate admin guard with a completed second step. */
    protected function asAdmin(User $user): static
    {
        // actingAs(..., 'admin') makes `admin` the default guard for the rest of the test; each later admin request
        // sets it itself, so put the default back first to keep customer-side requests in the same test honest.
        $this->app['auth']->shouldUse('web');

        return $this->actingAs($user, 'admin')->withSession([
            'admin_2fa' => ['user_id' => $user->id, 'at' => now()->timestamp],
            'admin_last_activity' => now()->timestamp,
        ]);
    }
}

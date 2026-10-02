<?php

namespace App\Services;

use App\Models\User;

class PatientContextFactory
{
    public function fromAuthenticatedUser(bool $lockClient = false): PatientContext
    {
        abort_unless(auth()->check(), 401);
        $user = User::query()->select(['id', 'tenant_id', 'role', 'active'])->find(auth()->id());
        abort_unless($user && $user->active && $user->role === 'patient' && $user->tenant_id, 403);
        abort_unless($user->tenant()->select('tenants.id')->whereKey($user->tenant_id)->exists(), 403);
        $query = $user->client()->select(['id', 'tenant_id', 'user_id', 'anonymized_at'])
            ->where('tenant_id', $user->tenant_id)->whereNull('anonymized_at');
        if ($lockClient) {
            $query->lockForUpdate();
        }
        $client = $query->first();
        abort_unless($client && $client->user_id === $user->id && $client->tenant_id === $user->tenant_id, 403);

        return new PatientContext($user->id, $user->tenant_id, $client->id);
    }
}

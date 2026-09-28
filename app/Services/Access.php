<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;

class Access
{
    public static function professional(): void
    {
        abort_unless(auth()->user()->isProfessional(), 403);
    }

    public static function publisher(): void
    {
        abort_unless(auth()->user()->canPublish(), 403);
    }

    public static function client(Client $client): void
    {
        abort_unless($client->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless(auth()->user()->isProfessional() || (auth()->user()->role === 'patient' && $client->user_id === auth()->id()), 403);
    }

    public static function assessment(Assessment $assessment): void
    {
        self::client($assessment->client);
    }

    public static function document(Document $document): void
    {
        $u = auth()->user();
        abort_unless($document->tenant_id === $u->tenant_id, 404);
        if ($u->isProfessional()) {
            return;
        }
        abort_unless($document->shared, 403);
        if ($u->role === 'patient') {
            abort_unless($document->client_id && $document->client_id === $u->client?->id, 403);
        } elseif ($u->role === 'entreprise') {
            abort_unless(! $document->client_id && $document->organization_id && $document->organization_id === $u->organization_id, 403);
        } else {
            abort(403);
        }
    }

    public static function audit(string $action, $entity): void
    {
        AuditLog::create(['tenant_id' => auth()->user()->tenant_id, 'user_id' => auth()->id(), 'action' => $action, 'entity_type' => class_basename($entity), 'entity_id' => $entity->id]);
    }
}

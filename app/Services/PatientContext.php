<?php

namespace App\Services;

final readonly class PatientContext
{
    public function __construct(public int $userId, public int $tenantId, public int $clientId) {}

    public function owns(int $tenantId, int $userId, int $clientId): bool
    {
        return $this->tenantId === $tenantId && $this->userId === $userId && $this->clientId === $clientId;
    }
}

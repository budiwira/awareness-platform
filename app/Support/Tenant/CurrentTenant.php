<?php

namespace App\Support\Tenant;

class CurrentTenant
{
    private ?string $tenantId = null;

    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function isSet(): bool
    {
        return $this->tenantId !== null;
    }
}
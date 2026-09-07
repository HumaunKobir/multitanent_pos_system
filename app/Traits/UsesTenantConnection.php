<?php

namespace App\Traits;

trait UsesTenantConnection
{
    public function getConnectionName(): ?string
    {
        if (! config('tenancy.enabled')) {
            return $this->connection;
        }

        return config('tenancy.tenant_connection', 'tenant');
    }
}

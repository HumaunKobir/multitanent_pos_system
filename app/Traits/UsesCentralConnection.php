<?php

namespace App\Traits;

trait UsesCentralConnection
{
    public function getConnectionName(): ?string
    {
        if (! config('tenancy.enabled')) {
            return $this->connection;
        }

        return config('tenancy.central_connection');
    }
}

<?php

namespace Portable\EloquentZoho\TokenStorage;

use Illuminate\Support\Facades\Cache as FacadesCache;
use Portable\EloquentZoho\Contracts\TokenStorageInterface;

class Cache implements TokenStorageInterface
{
    public function get(): ?string
    {
        $result = (string)FacadesCache::get('zoho_token');

        return $result;
    }

    public function set(string $token): void
    {
        FacadesCache::forever('zoho_token', $token);
    }
}

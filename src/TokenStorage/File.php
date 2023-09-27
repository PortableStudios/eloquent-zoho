<?php

namespace Portable\EloquentZoho\TokenStorage;

use Illuminate\Support\Facades\Storage;
use Portable\EloquentZoho\Contracts\TokenStorageInterface;

class File implements TokenStorageInterface
{
    public function get(): ?string
    {
        $result = Storage::get(config('eloquent-zoho.token_file'));

        return $result;
    }

    public function set(string $token): void
    {
        Storage::put(config('eloquent-zoho.token_file'), $token);
    }
}

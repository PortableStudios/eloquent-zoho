<?php

namespace Portable\EloquentZoho;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TokenStorage
{
    public static function get(): string
    {
        return match (config('eloquent-zoho.token_driver')) {
            'cache' => Cache::get('zoho_token'),
            'file' => Storage::get(config('eloquent-zoho.token_file')),
            default => throw new \Exception('Invalid token driver')
        };
    }

    public static function set(string $token): void
    {
        match (config('eloquent-zoho.token_driver')) {
            'cache' => Cache::forever('zoho_token', $token),
            'file' => Storage::put(config('eloquent-zoho.token_file'), $token),
            default => throw new \Exception('Invalid token driver')
        };
    }
}

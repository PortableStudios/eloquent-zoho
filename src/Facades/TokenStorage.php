<?php

namespace Portable\EloquentZoho\Facades;

class TokenStorage extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return 'eloquent-zoho.token-storage';
    }
}

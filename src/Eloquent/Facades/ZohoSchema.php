<?php

namespace Portable\EloquentZoho\Eloquent\Facades;

use Illuminate\Support\Facades\Facade;

class ZohoSchema extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'zoho.builder';
    }
}

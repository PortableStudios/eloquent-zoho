<?php

namespace Portable\EloquentZoho\Eloquent\Facades;

use Illuminate\Support\Facades\Facade;
use Portable\EloquentZoho\Eloquent\Schema\Builder;

/**
 * @mixin Builder
 */
class ZohoSchema extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zoho.builder';
    }
}

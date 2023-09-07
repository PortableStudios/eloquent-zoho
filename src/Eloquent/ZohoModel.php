<?php

namespace Portable\EloquentZoho\Eloquent;

use Illuminate\Database\Eloquent\Model;

abstract class ZohoModel extends Model
{
    protected $connection = 'zoho';

    protected static $unguarded = true;

    public $incrementing = false;

    abstract public static function arrayFromLocal(Model $local): array;

    /**
     * Get the current connection name for the model.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        return $this->connection ?? config('zoho.connection', 'zoho');
    }
}

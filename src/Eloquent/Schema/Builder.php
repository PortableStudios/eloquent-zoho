<?php

namespace Portable\EloquentZoho\Eloquent\Schema;

use Closure;

class Builder extends \Illuminate\Database\Schema\Builder
{
    /**
     * The database connection instance.
     *
     * @var \App\Support\ZohoEloquent\Connection
     */
    protected $connection;

    /**
     * @inheritdoc
     */
    public function hasTable($table)
    {
        return $this->connection->hasTable($table);
    }

    protected function createBlueprint($table, Closure $callback = null)
    {
        return new Blueprint($table, $callback);
    }
}

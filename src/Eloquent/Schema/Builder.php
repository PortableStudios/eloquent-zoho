<?php

namespace Portable\EloquentZoho\Eloquent\Schema;

use Closure;

class Builder extends \Illuminate\Database\Schema\Builder
{
    /**
     * The database connection instance.
     *
     * @var \Portable\EloquentZoho\Eloquent\Connection
     */
    protected $connection;

    /**
     * {@inheritdoc}
     */
    public function hasTable($table)
    {
        return $this->connection->hasTable($table);
    }

    protected function createBlueprint($table, Closure $callback = null)
    {
        return new Blueprint($table, $callback);
    }

    public function generateAuthToken(string $username, string $password): ?string
    {
        return $this->connection->generateAuthToken($username, $password);
    }
}

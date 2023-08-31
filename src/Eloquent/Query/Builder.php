<?php

namespace Portable\EloquentZoho\Eloquent\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    /**
     * The database connection instance.
     *
     * @var \App\Support\ZohoEloquent\Connection
     */
    public $connection;

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return bool
     */
    public function updateOrInsert(array $attributes, array $values = [])
    {
        if (! $this->where($attributes)->count()) {
            return $this->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return (bool) $this->update($values);
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param  array  $values
     * @param  array|string  $uniqueBy
     * @param  array|null  $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        return $this->connection->zohoUpsert(
            $this->from,
            $values,
            $uniqueBy
        );
    }

    /**
     * Update records in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        return $this->connection->zohoUpdate($this->from, $values, $this->grammar->compileWheres($this));
    }

    /**
     * Insert a new record into the database.
     */
    public function insert(array $values)
    {
        return (bool) $this->connection->zohoInsert($this->from, $values);
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect()
    {
        $results = $this->connection->zohoSelect(
            $this->from,
            $this->grammar->compileWheres($this)
        );

        if (!$this->aggregate) {
            return $results;
        }

        // We do all aggregate functions in PHP because Zoho doesn't support them
        $function = $this->aggregate['function'];
        $column = $this->aggregate['columns'][0];
        $alias = 'aggregate';

        $results[0][$alias] = match ($function) {
            'count' => count($results),
            'sum' => array_sum(array_column($results, $column)),
            'avg' => array_sum(array_column($results, $column)) / count($results),
            'min' => min(array_column($results, $column)),
            'max' => max(array_column($results, $column)),
            default => throw new \Exception('Unsupported aggregate function: ' . $function),
        };

        return $results;
    }

    /**
     * Delete records from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (! is_null($id)) {
            $this->where($this->from.'.id', '=', $id);
        }

        $this->applyBeforeQueryCallbacks();

        return $this->connection->zohoDelete(
            $this->from,
            $this->grammar->compileWheres($this)
        );
    }

    /**
     * Determine if the operator is a bitwise operator.
     *
     * @param  string  $operator
     * @return bool
     */
    protected function isBitwiseOperator($operator)
    {
        return in_array(strtolower($operator), $this->bitwiseOperators, true);
    }
}

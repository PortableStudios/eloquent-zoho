<?php

namespace Portable\EloquentZoho\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    /**
     * The base query builder instance.
     *
     * @var \Portable\EloquentZoho\Eloquent\Query\Builder
     */
    protected $query;

    /**
     * The database connection instance.
     *
     * @var \Portable\EloquentZoho\Eloquent\Connection
     */
    public $connection;

    /**
     * Insert new records or update the existing ones.
     *
     * @param  array|string  $uniqueBy
     * @param  array|null  $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        return $this->getConnection()->zohoUpsert(
            $this->model->getTable(),
            $values,
            $uniqueBy
        );
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        $values = array_merge($attributes, $values);

        $this->upsert($values, $this->model->getKeyName());

        return $this->where($attributes)->first() ?: $this->create($values);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $values, array $options = [])
    {
        return $this->toBase()->update($this->addUpdatedAtColumn($values));
    }

    /**
     * Add the "updated at" column to an array of values.
     * TODO Remove if https://github.com/laravel/framework/commit/6484744326531829341e1ff886cc9b628b20d73e
     * wiil be reverted
     * Issue in laravel frawework https://github.com/laravel/framework/issues/27791.
     *
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (! $this->model->usesTimestamps() || $this->model->getUpdatedAtColumn() === null) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();
        $values = array_merge(
            [$column => $this->model->freshTimestampString()],
            $values
        );

        return $values;
    }

    /**
     * @return \Portable\EloquentZoho\Eloquent\Connection
     */
    public function getConnection()
    {
        return $this->query->getConnection();
    }
}

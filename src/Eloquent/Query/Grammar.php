<?php

namespace Portable\EloquentZoho\Eloquent\Query;

use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;

class Grammar extends BaseGrammar
{
    /**
     * The components that make up a Zoho criteria clause.
     */
    public function compileWheres(Builder $query): string
    {
        $sql = '';
        foreach ($query->wheres as $where) {
            $sql .= $this->parseWhere($query, $where);
        }
        $sql = $this->stripLastBoolean($sql);

        return $sql;
    }

    /**
     * Parses the where clause and returns the Zoho approved criteria string
     */
    protected function parseWhere(Builder $query, array $where): string
    {
        $fromTable = $query->from;

        switch ($where['type']) {
            case 'Basic':
                $column = str_replace($fromTable . '.', '', $where['column']);

                return '("'
                    . $column
                    . '" ' . $where['operator'] . ' '
                    . $this->quoteString($where['value']) . ') '
                    . $where['boolean'];
            case 'Nested':
                $subQuery = $this->compileWheres($where['query']);

                return '(' . $subQuery . ') ' . $where['boolean'];
            case 'In':
                return '("' . $where['column']
                    . '" IN (' . $this->quoteString($where['values'])
                    . ')) ' . $where['boolean'];
            default:
                throw new Exception('Unknown where type: ' . $where['type']);
        }
    }

    /**
     * Quote the given string literal.
     *
     * @param  string|array  $value
     * @return string
     */
    public function quoteString($value)
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }

        if (is_string($value)) {
            // Weird escape of slashes for Zoho
            $value = str_replace('\\', '\\\\\\\\\\', $value);
        }

        return "'$value'";
    }

    /**
     * Strips the last connector off the SQL (e.g 'and','or')
     */
    protected function stripLastBoolean(string $sql): string
    {
        return substr($sql, 0, strrpos($sql, ')') + 1);
    }
}

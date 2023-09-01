<?php

namespace Portable\EloquentZoho\Eloquent\Schema;

use Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;
use Illuminate\Database\Schema\ColumnDefinition;

class Grammar extends BaseGrammar
{
    public function buildColumns(Blueprint $blueprint): array
    {
        $columns = [];
        foreach ($blueprint->getColumns() as $column) {
            $columns[] = [
                'COLUMNNAME' => $column['name'],
                'MANDATORY' => $column['nullable'] ? 'No' : 'Yes',
                'DEFAULT' => $column['default'],
                'DESCRIPTION' => $column['comment'],
                'DATATYPE' => $this->typeString($column),
            ];
        }

        return $columns;
    }

    protected function typeString(ColumnDefinition $column): string
    {
        if ($column['autoIncrement']) {
            return 'AUTO_NUMBER';
        }

        switch ($column['type']) {
            case 'bigInteger':
                return 'NUMBER';
            case 'email':
                return 'EMAIL';
            case 'string':
                return 'PLAIN';
            case 'boolean':
                return 'BOOLEAN';
            case 'timestamp':
            case 'dateTime':
                return 'DATE';
            default:
                throw new \Exception("Data type not supported: {$column['type']}");
        }
        /*                PLAIN
        MULTI_LINE
        EMAIL
        NUMBER
        POSITIVE_NUMBER
        DECIMAL_NUMBER
        CURRENCY
        PERCENT
        DATE
        BOOLEAN
        URL
        AUTO_NUMBER */
    }
}

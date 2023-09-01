<?php

namespace Portable\EloquentZoho\Eloquent\Schema;

use Exception;
use Portable\EloquentZoho\Eloquent\Connection;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;
use Illuminate\Support\Str;

class Blueprint extends SchemaBlueprint
{
    /**
     * Execute the blueprint against the database.
     *
     * @return void
     */
    public function build(
        \Illuminate\Database\Connection $connection,
        \Illuminate\Database\Schema\Grammars\Grammar $grammar
    ) {
        foreach ($this->commands as $command) {
            $methodName = 'zoho' . Str::ucFirst($command['name']);
            if (method_exists($this, $methodName)) {
                $this->$methodName($connection, $grammar);
            } else {
                throw new \Exception("Command {$command['name']} not supported");
            }
        }
    }

    protected function zohoDropIfExists(Connection $connection, Grammar $grammar): void
    {
        $result = $connection->zohoDeleteTable($this->table);
        if (! $result->successful()) {
            $result = json_decode($result->body());
            if (Str::match('/View (.+) is not present in/', $result->response->error->message) == $this->table) {
                return;
            }
            throw new Exception($result->body());
        }
    }

    protected function zohoCreate(Connection $connection, Grammar $grammar): void
    {
        $zohoTableDesign = [
            'TABLENAME' => $this->table,
            'TABLEDESCRIPTION' => '',
            'FOLDERNAME' => $connection->getFolderName(),
            'COLUMNS' => $grammar->buildColumns($this),
        ];

        $result = $connection->zohoCreateTable($zohoTableDesign);
        if (! $result->successful()) {
            throw new Exception($result->body());
        }
    }
}

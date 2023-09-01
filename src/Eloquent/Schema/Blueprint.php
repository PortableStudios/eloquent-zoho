<?php

namespace Portable\EloquentZoho\Eloquent\Schema;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Str;

class Blueprint extends SchemaBlueprint
{
    /**
     * Execute the blueprint against the database.
     *
     * @param  App\Support\ZohoEloquent\Connection  $connection
     * @param  App\Support\ZohoEloquent\Schema\Grammars\Grammar  $grammar
     * @return void
     */
    public function build(Connection $connection, Grammar $grammar)
    {
        foreach ($this->commands as $command) {
            $methodName = 'zoho'.Str::ucFirst($command->name);
            if (method_exists($this, $methodName)) {
                $this->$methodName($connection, $grammar);
            } else {
                throw new \Exception("Command {$command->name} not supported");
            }
        }
    }

    protected function zohoExists(Connection $connection, Grammar $grammar)
    {
        $result = $connection->zohoGetTable($this->table);
        if (! $result->successful()) {
            throw new Exception($result->body());
        }
    }

    protected function zohoDropIfExists(Connection $connection, Grammar $grammar)
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

    protected function zohoCreate(Connection $connection, Grammar $grammar)
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

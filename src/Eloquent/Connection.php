<?php

namespace Portable\EloquentZoho\Eloquent;

use Illuminate\Database\Connection as DatabaseConnection;
use Illuminate\Support\Str;
use Portable\EloquentZoho\Eloquent\Query\Builder;
use Portable\EloquentZoho\Eloquent\Query\Grammar;
use Portable\EloquentZoho\ZohoClient;

class Connection extends DatabaseConnection
{
    /**
     * @var ZohoClient
     */
    protected $connection;

    protected $folderName;

    public function __construct(protected array $zohoConfig)
    {
        $requiredKeys = ['api_url', 'api_email', 'auth_token', 'workspace_name', 'folder_name'];
        foreach ($requiredKeys as $key) {
            if (! isset($zohoConfig[$key])) {
                throw new \Exception("Missing required key '$key' in zoho config");
            }
        }

        $this->connection = new ZohoClient(
            $zohoConfig['api_url'],
            $zohoConfig['api_email'],
            $zohoConfig['auth_token'],
            $zohoConfig['workspace_name'],
        );
        $this->folderName = $zohoConfig['folder_name'];
        $this->useDefaultPostProcessor();
        $this->useDefaultQueryGrammar();
        $this->useDefaultSchemaGrammar();
    }

    public function generateAuthToken(string $username, string $password): string
    {
        return $this->connection->generateAuthToken($username, $password);
    }

    public function getFolderName(): string
    {
        return $this->folderName;
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Grammar();
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return new Schema\Grammar();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }

    public function hasTable(string $table): bool
    {
        try {
            $this->connection->exportTable($table, 'badcolumnname = 1');

            // If for some insane reason, the table exists and has a column called badcolumname
            // then we'll get a successful response and we know the table exists
            return true;
        } catch (\Exception $e) {
            if (Str::match('/View (.+) is not present/', $e->getMessage()) == $table) {
                return false;
            } elseif (Str::contains($e->getMessage(), "Unknown column 'badcolumnname'")) {
                return true;
            }
            throw $e;
        }
    }

    public function zohoSelect(string $fromTable, string $query): array
    {
        $data = $this->connection->exportTable($fromTable, $query);

        $rows = [];
        foreach ($data['response']['result']['rows'] as $row) {
            $rows[] = array_combine($data['response']['result']['column_order'], $row);
        }

        return $rows;
    }

    public function zohoInsert(string $toTable, array $data): int
    {
        $result = $this->connection->addTableRow($toTable, $data);
        $json = json_decode(str_replace("\\'", "'", $result->body()), true);
        if (! $result->successful()) {
            $msg = $json['response']['error']['message'];
            throw new \Exception('Zoho insert failed: '.$msg);
        }

        return count($json['response']['result']['rows']);
    }

    public function zohoUpdate(string $fromTable, array $data, string $where): int
    {
        $result = $this->connection->updateTableRow($fromTable, $data, $where);
        $json = json_decode(str_replace("\\'", "'", $result->body()), true);
        if (! $result->successful()) {
            $msg = $json['response']['error']['message'];
            throw new \Exception('Zoho insert failed: '.$msg);
        }

        return $json['response']['result']['updatedRows'];
    }

    public function zohoUpsert(string $toTable, array $data, array|string $key): int
    {
        if (! is_array($key)) {
            $key = [$key];
        }

        return $this->connection->importUpsert($toTable, $data, $key);
    }

    public function zohoDelete($fromTable, $where): int
    {
        // For reasons that make absolutely no sense to me, backslash
        // escaping is inconsistent depending on operation.  For example,
        // exporting data requires 5 backslashes to escape, but deleting only requires 2.
        // I have no idea why, but this is what works.
        $where = str_replace('\\\\\\\\\\', '\\\\', $where);

        $result = $this->connection->deleteTableRow($fromTable, $where);
        $json = json_decode(str_replace("\\'", "'", $result->body()), true);
        if (! $result->successful()) {
            $msg = $json['response']['error']['message'];
            throw new \Exception('Zoho delete failed: '.$msg);
        }

        return $json['response']['result']['deletedrows'];
    }

    public function zohoCreateTable($tableDefinition)
    {
        return $this->connection->createTable($tableDefinition);
    }

    public function zohoDeleteTable($tableName)
    {
        return $this->connection->deleteTable($tableName);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        return new Builder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }
}

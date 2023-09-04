<?php

namespace Portable\EloquentZoho\Eloquent;

use Illuminate\Database\Connection as DatabaseConnection;
use Illuminate\Support\Str;
use Portable\EloquentZoho\Eloquent\Query\Builder;
use Portable\EloquentZoho\Eloquent\Query\Grammar;
use Portable\EloquentZoho\Exceptions\ConfigurationException;
use Portable\EloquentZoho\ZohoClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Portable\EloquentZoho\Exceptions\NotConnectedException;

class Connection extends DatabaseConnection
{
    /**
     * @var ZohoClient
     */
    protected ZohoClient $client;

    protected string $folderName;

    public function __construct(protected array $zConfig)
    {
        $requiredKeys = ['api_url', 'api_email', 'username','password', 'database', 'folder_name'];
        foreach ($requiredKeys as $key) {
            if (! isset($zConfig[$key])) {
                throw new ConfigurationException("Missing required key '$key' in zoho config");
            }
        }

        $this->client = new ZohoClient(
            $zConfig['api_url'],
            $zConfig['api_email'],
            $zConfig['database'],
            $zConfig['auth_token'] ?? null,
        );

        // If the client is configured, but not connected, we need
        // to generate an auth token.
        if ($this->client->configured() && !$this->client->connected()) {
            // Do we have a cached token?
            $token = Cache::get('zoho_token') ?: $this->generateAuthToken($zConfig['username'], $zConfig['password']);
            Cache::forever('zoho_token', $token);
            $this->client->setAuthToken($token);
        }

        if (! $this->client->connected()) {
            throw new NotConnectedException('Zoho client is not connected');
        }

        $this->folderName = $zConfig['folder_name'];
        $this->useDefaultPostProcessor();
        $this->useDefaultQueryGrammar();
        $this->useDefaultSchemaGrammar();
    }

    public function setAuthToken(?string $token): void
    {
        $this->client->setAuthToken($token);
    }

    public function generateAuthToken(string $username, string $password): ?string
    {
        return $this->client->generateAuthToken($username, $password);
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
            $this->client->exportTable($table, 'badcolumnname = 1');

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
        $data = $this->client->exportTable($fromTable, $query);

        $rows = [];
        foreach ($data['response']['result']['rows'] as $row) {
            $rows[] = array_combine($data['response']['result']['column_order'], $row);
        }

        return $rows;
    }

    public function zohoInsert(string $toTable, array $data): int
    {
        $result = $this->client->addTableRow($toTable, $data);
        $json = json_decode(str_replace("\\'", "'", $result->body()), true);
        if (! $result->successful()) {
            $msg = $json['response']['error']['message'];
            throw new \Exception('Zoho insert failed: ' . $msg);
        }

        return count($json['response']['result']['rows']);
    }

    public function zohoUpdate(string $fromTable, array $data, string $where): int
    {
        $result = $this->client->updateTableRow($fromTable, $data, $where);
        $json = json_decode(str_replace("\\'", "'", $result->body()), true);
        if (! $result->successful()) {
            $msg = $json['response']['error']['message'];
            throw new \Exception('Zoho insert failed: ' . $msg);
        }

        return $json['response']['result']['updatedRows'];
    }

    public function zohoUpsert(string $toTable, array $data, array|string $key): int
    {
        if (! is_array($key)) {
            $key = [$key];
        }

        return $this->client->importUpsert($toTable, $data, $key);
    }

    public function zohoDelete(string $fromTable, string $where): int
    {
        // For reasons that make absolutely no sense to me, backslash
        // escaping is inconsistent depending on operation.  For example,
        // exporting data requires 5 backslashes to escape, but deleting only requires 2.
        // I have no idea why, but this is what works.
        $where = str_replace('\\\\\\\\\\', '\\\\', $where);

        $result = $this->client->deleteTableRow($fromTable, $where);
        $json = json_decode(str_replace("\\'", "'", $result->body()), true);
        if (! $result->successful()) {
            $msg = $json['response']['error']['message'];
            throw new \Exception('Zoho delete failed: ' . $msg);
        }

        return $json['response']['result']['deletedrows'];
    }

    public function zohoCreateTable(array $tableDefinition): Response
    {
        return $this->client->createTable($tableDefinition);
    }

    public function zohoDeleteTable(string $tableName): Response
    {
        return $this->client->deleteTable($tableName);
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

<?php

namespace Portable\EloquentZoho\Eloquent;

use Illuminate\Database\Connection as DatabaseConnection;
use Illuminate\Support\Str;
use Portable\EloquentZoho\Eloquent\Query\Builder;
use Portable\EloquentZoho\Eloquent\Query\Grammar;
use Portable\EloquentZoho\Exceptions\ConfigurationException;
use Portable\EloquentZoho\ZohoClient;
use Illuminate\Http\Client\Response;
use Portable\EloquentZoho\Exceptions\NotConnectedException;
use Portable\EloquentZoho\TokenStorage;

class Connection extends DatabaseConnection
{
    /**
     * @var ZohoClient
     */
    protected ?ZohoClient $client = null;

    protected string $folderName;

    public function __construct(protected array $zConfig)
    {
        $requiredKeys = ['host', 'port', 'username','password', 'database', 'prefix','email'];
        foreach ($requiredKeys as $key) {
            if (! isset($zConfig[$key])) {
                throw new ConfigurationException("Missing required key '$key' in zoho config");
            }
        }

        $this->folderName = $zConfig['prefix'];
        $this->useDefaultPostProcessor();
        $this->useDefaultQueryGrammar();
        $this->useDefaultSchemaGrammar();
    }

    public function setAuthToken(?string $token): void
    {
        $this->getClient()->setAuthToken($token);
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
            $this->getClient()->exportTable($table, 'badcolumnname = 1');

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
        $data = $this->getClient()->exportTable($fromTable, $query);

        $rows = [];
        foreach ($data['response']['result']['rows'] as $row) {
            $rows[] = array_combine($data['response']['result']['column_order'], $row);
        }

        return $rows;
    }

    public function zohoInsert(string $toTable, array $data): int
    {
        $result = $this->getClient()->addTableRow($toTable, $data);
        $json = json_decode(str_replace("\\'", "'", $result->body()), true);
        if (! $result->successful()) {
            $msg = $json['response']['error']['message'];
            throw new \Exception('Zoho insert failed: ' . $msg);
        }

        return count($json['response']['result']['rows']);
    }

    public function zohoUpdate(string $fromTable, array $data, string $where): int
    {
        $result = $this->getClient()->updateTableRow($fromTable, $data, $where);
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

        return $this->getClient()->importUpsert($toTable, $data, $key);
    }

    public function zohoDelete(string $fromTable, string $where): int
    {
        // For reasons that make absolutely no sense to me, backslash
        // escaping is inconsistent depending on operation.  For example,
        // exporting data requires 5 backslashes to escape, but deleting only requires 2.
        // I have no idea why, but this is what works.
        $where = str_replace('\\\\\\\\\\', '\\\\', $where);

        $result = $this->getClient()->deleteTableRow($fromTable, $where);
        $json = json_decode(str_replace("\\'", "'", $result->body()), true);
        if (! $result->successful()) {
            $msg = $json['response']['error']['message'];
            throw new \Exception('Zoho delete failed: ' . $msg);
        }

        return $json['response']['result']['deletedrows'];
    }

    public function zohoCreateTable(array $tableDefinition): Response
    {
        return $this->getClient()->createTable($tableDefinition);
    }

    public function zohoDeleteTable(string $tableName): Response
    {
        return $this->getClient()->deleteTable($tableName);
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

    protected function getClient(): ZohoClient
    {
        if ($this->client) {
            return $this->client;
        }

        $this->client = new ZohoClient(
            $this->zConfig['host'],
            $this->zConfig['port'],
            $this->zConfig['username'],
            $this->zConfig['database'],
            $this->zConfig['auth_token'] ?? null,
        );

        // If the client is configured, but not connected, we need
        // to generate an auth token.
        if ($this->client->configured() && !$this->client->connected()) {
            // Do we have a cached token?
            $token = TokenStorage::get();
            if (!$token) {
                $token = $this->client->generateAuthToken($this->zConfig['email'], $this->zConfig['password']);
                TokenStorage::set($token);
            }
            $this->client->setAuthToken($token);
        }

        if (! $this->client->connected()) {
            throw new NotConnectedException('Zoho client is not connected');
        }

        return $this->client;
    }
}

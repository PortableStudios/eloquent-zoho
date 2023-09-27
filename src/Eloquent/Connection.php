<?php

namespace Portable\EloquentZoho\Eloquent;

use Illuminate\Database\Connection as DatabaseConnection;
use Illuminate\Support\Str;
use Portable\EloquentZoho\Eloquent\Query\Builder;
use Portable\EloquentZoho\Eloquent\Query\Grammar;
use Portable\EloquentZoho\Exceptions\ConfigurationException;
use Portable\EloquentZoho\ZohoClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Portable\EloquentZoho\Exceptions\NotConnectedException;
use Portable\EloquentZoho\TokenStorage;

class Connection extends DatabaseConnection
{
    /**
     * @var ZohoClient
     */
    protected ?ZohoClient $client = null;

    protected Collection $views;
    protected Collection $folders;
    protected array $schemas;

    protected string $folderName;

    public function __construct(protected array $zConfig)
    {
        $requiredKeys = ['host', 'port', 'username','password', 'database', 'prefix','email'];
        foreach ($requiredKeys as $key) {
            if (! isset($zConfig[$key])) {
                throw new ConfigurationException("Missing required key '$key' in zoho config");
            }
        }

        $this->views = collect();
        $this->folders = collect();
        $this->schemas = [];

        $this->folderName = $zConfig['prefix'];
        $this->useDefaultPostProcessor();
        $this->useDefaultQueryGrammar();
        $this->useDefaultSchemaGrammar();
        $this->config = $zConfig;
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
        $tableSchema = $this->getSchema($fromTable);
        $dateColumns = $tableSchema['columnList']
                        ->where('dataTypeName', 'Date')
                        ->pluck('dateFormat', 'columnName')
                        ->toArray();
        $numberColumns = $tableSchema['columnList']
                        ->whereIn('dataTypeName', ['Auto Number','Number'])
                        ->pluck('columnName')->toArray();

        $response = $this->getClient()->exportTable($fromTable, $query)['response'];
        $data = $response['result']['rows'];


        $rows = [];
        foreach ($data as $row) {
            $row = array_combine($response['result']['column_order'], $row);
            foreach ($dateColumns as $dateColumn => $dateFormat) {
                if (isset($row[$dateColumn]) && strlen($row[$dateColumn])) {
                    $row[$dateColumn] = Carbon::createFromFormat($dateFormat, $row[$dateColumn])->format('Y-m-d H:i:s');
                }
            }

            foreach ($numberColumns as $numberColumn) {
                if (isset($row[$numberColumn]) && strlen($row[$numberColumn])) {
                    $row[$numberColumn] = (int) Str::replace(',', '', $row[$numberColumn]);
                }
            }

            $rows[] = $row;
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

        foreach ($data as $rowIndex => $row) {
            foreach ($row as $field => $value) {
                $row[$field] = $value instanceof \Stringable ? (string) $value : $value;
            }
            $data[$rowIndex] = $row;
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
            $token = app('eloquent-zoho.token-storage')->get();
            if (!$token) {
                $token = $this->client->generateAuthToken($this->zConfig['email'], $this->zConfig['password']);
                app('eloquent-zoho.token-storage')->set($token);
            }
            $this->client->setAuthToken($token);
        }

        if (! $this->client->connected()) {
            throw new NotConnectedException('Zoho client is not connected');
        }

        return $this->client;
    }

    public function getFolderId(): ?string
    {
        return $this->getFolders()->where('folderName', $this->folderName)->first();
    }

    public function getFolders(): Collection
    {
        if (count($this->folders) == 0) {
            $response = $this->getClient()->getFolderList();
            if ($response->successful()) {
                /** @var array<int, array> $folders */
                $folders = $response->json()['response']['result'];
                $this->folders = collect($folders);
            }
        }
        return $this->folders;
    }

    public function getViews(): Collection
    {
        if (count($this->views) == 0) {
            $response = $this->getClient()->getViewList();
            if ($response->successful()) {
                /** @var array<int, array> $views */
                $views = $response->json()['response']['result'];
                $this->views = collect($views);
            }
        }
        return $this->views;
    }

    public function getSchema(string $name): array
    {
        if (isset($this->schemas[$name])) {
            return $this->schemas[$name];
        } else {
            $response = $this->getClient()->getViewInfo($name);
            if ($response->successful()) {
                $schema = $response->json()['response']['result']['viewInfo'];
                foreach ($schema['columnList'] as $key => $item) {
                    if (isset($item['dateFormat'])) {
                        $item['dateFormat'] = $this->parseZohoDateFormat($item['dateFormat']);
                    }
                    $schema['columnList'][$key] = $item;
                }

                /** @var array<int, array> $schemaList */
                $schemaList = $schema['columnList'];
                $schema['columnList'] = collect($schemaList);

                $this->schemas[$name] = $schema;
            } else {
                throw new \Exception("Unable to get schema");
            }
        }
        return $schema;
    }

    protected function parseZohoDateFormat(string $format): string
    {
        $format = str_replace('dd', 'd', $format);
        $format = str_replace('MMM', 'M', $format);
        $format = str_replace('yyyy', 'Y', $format);

        $format = str_replace('HH', 'H', $format);
        $format = str_replace('mm', 'i', $format);
        $format = str_replace('ss', 's', $format);

        return $format;
    }
}

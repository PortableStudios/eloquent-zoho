<?php

namespace Portable\EloquentZoho;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Portable\EloquentZoho\Events\ZohoCallCompleted;
use Portable\EloquentZoho\Exceptions\ConfigurationException;
use Portable\EloquentZoho\Exceptions\NotConnectedException;
use Portable\EloquentZoho\Exceptions\TokenGenerationException;

class ZohoClient
{
    public const DATE_FORMAT = 'yyyy-MM-dd HH:mm:ss';
    protected string $baseUrl = '';

    public function __construct(
        protected string $apiHost,
        protected string $apiPort,
        protected string $apiEmail,
        protected string $workspaceName,
        protected ?string $authToken = null,
    ) {
        $this->baseUrl = implode('/', [
            (intval($this->apiPort) === 443 ? 'https:/' : 'http:/'),
            $this->apiHost
        ]);
    }

    /*
    * Determine if the service has enough configuration data to run correctly
    */
    public function configured(): bool
    {
        return $this->apiHost
            && $this->apiPort
            && $this->apiEmail
            && $this->workspaceName;
    }

    /**
     * Determine if the service can be connected.
     * Since this simply relies on a handful of configuration values, we'll assume that connection is possible
     * if the configuration is set.
     */
    public function connected(): bool
    {
        return $this->apiHost
            && $this->apiPort
            && $this->apiEmail
            && $this->authToken;
    }

    public function setAuthToken(?string $token): void
    {
        $this->authToken = $token;
    }

    /**
     * Generates the authentication token to be used in subsequent requests to the API.
     *
     * The Auth API is a separate from the Data API.
     */
    public function generateAuthToken(string $userEmail, string $userPassword): ?string
    {
        if (!$this->configured()) {
            throw new ConfigurationException('Cannot connect to Zoho Analytics with current configuration.'
                . ' Check URL and credentials.');
        }

        $response = Http::get(
            $this->baseUrl . '/iam/apiauthtoken/nb/create?SCOPE=ZROP/reportsapi',
            [
                'EMAIL_ID' => $userEmail,
                'PASSWORD' => $userPassword,
            ]
        );

        if ($response->successful()) {
            if (preg_match('#AUTHTOKEN=([a-f0-9]+)\b#', $response->body(), $matches)) {
                return $matches[1];
            } else {
                throw new TokenGenerationException('Unable to generate auth token');
            }
        }
        throw new TokenGenerationException('Unable to generate auth token');
    }

    /**
     * Deals with logging errors and other information after making a Data API request.
     */
    public function handleResponse(Response $response): ?Response
    {
        ZohoCallCompleted::dispatch($response);

        return $response;
    }

    /**
     * Sends POST requests to the Data API.
     *
     * @param  string  $url The endpoint for the request at the configured base URL ('zoho.api_url')
     * @param  array|null  $data The data being sent (default is [])
     */
    public function post(string $url, ?array $data = []): ?Response
    {
        if (! $this->connected()) {
            throw new NotConnectedException();
        }

        $data = array_merge($data, [
            'ZOHO_API_VERSION' => '1.0',
            'ZOHO_OUTPUT_FORMAT' => 'JSON',
            'ZOHO_ERROR_FORMAT' => 'JSON',
            'authtoken' => $this->authToken,
        ]);

        return $this->handleResponse(Http::asForm()->post(
            $this->buildUrl($url),
            $data
        ));
    }

    protected function buildUrl(string $url): string
    {
        $fullUrl = $this->baseUrl
        . '/api'
        . '/' . $this->apiEmail
        . '/' . $this->workspaceName;
        $fullUrl .= substr($url, 0, 1) == '/' ? $url : '/' . $url;

        return $fullUrl;
    }

    public function importUpsert(string $url, array $data, array $keys, bool $isTransaction = true): int
    {
        $result = $this->importAction($url, $data, 'UPDATEADD', $keys, $isTransaction);
        $data = $result->body();
        $json = json_decode($data);

        if (! $result->successful()) {
            throw new \Exception($json->response->error->message);
        }

        return $json->response->result->importSummary->successRowCount;
    }

    /**
     * Imports an array of data into the Zoho Analytics table provided in the url.
     */
    public function import(string $url, array $data, bool $isTransaction = false): ?Response
    {
        return $this->importAction($url, $data, 'APPEND', [], $isTransaction);
    }

    /**
     * Imports an array of data into the Zoho Analytics table provided in the url.
     */
    protected function importAction(
        string $url,
        array $data,
        string $action,
        array $keys = [],
        bool $isTransaction = false
    ): ?Response {
        if (! $this->connected()) {
            throw new NotConnectedException();
        }

        $postData = [
            'ZOHO_IMPORT_TYPE' => $action,
            'ZOHO_IMPORT_FILETYPE' => 'JSON',
            'ZOHO_AUTO_IDENTIFY' => 'TRUE',
            'ZOHO_ON_IMPORT_ERROR' => $isTransaction ? 'ABORT' : 'SKIPROW',
            'ZOHO_MATCHING_COLUMNS' => implode(',', $keys),
            'ZOHO_CREATE_TABLE' => 'FALSE',   // the documentation says this is optional. it is wrong.
            'ZOHO_DATE_FORMAT' => self::DATE_FORMAT,
        ];

        $response = $this->handleResponse(
            Http::attach('ZOHO_FILE', json_encode($data), 'file')
                ->asMultipart()
                ->post(
                    $this->buildUrl($url)
                        . '?ZOHO_ACTION=IMPORT'   // the documentation is misleading, and these still need to be
                        . '&ZOHO_API_VERSION=1.0' // query parameters and not part of the post body
                        . '&ZOHO_OUTPUT_FORMAT=JSON'
                        . '&ZOHO_ERROR_FORMAT=JSON'
                        . '&authtoken=' . $this->authToken,
                    $postData,
                )
        );

        if (is_null($response)) {
            Log::error('Zoho Analytics returned null');
        }

        return $response;
    }

    /**
     * Retrieves the data from a given table
     */
    public function exportTable(string $tableName, string $query = null): array
    {
        $response = $this->post($tableName . '?ZOHO_ACTION=export', [
            'ZOHO_SHOW_HIDDENCOLS' => 'true',
            'ZOHO_DATE_FORMAT' => self::DATE_FORMAT,
            'ZOHO_CRITERIA' => $query,
        ]);

        $data = $response->body();
        // Zoho Analytics returns invalid JSON if criteria were used,
        // so strip them out before decoding
        $data = str_replace("\\'", "'", $data);
        $json = json_decode($data, true);

        if (! $response->successful()) {
            $message = '';
            if (! $json) {
                if (preg_match("/message\"\:(\"[^\n].+)/", $data, $matches)) {
                    $message = $matches[1];
                }
            } else {
                $message = $json['response']['error']['message'];
            }
            throw new Exception('Error Processing Request: ' . $message, 1);
        }

        return $json ?? [];
    }

    public function deleteTableRow(string $tableName, string $where): Response
    {
        $response = $this->post($tableName . '?ZOHO_ACTION=DELETE', [
            'ZOHO_DATE_FORMAT' => self::DATE_FORMAT,
            'ZOHO_CRITERIA' => $where,
        ]);

        return $response;
    }

    /**
     * Adds a row to the given Zoho table
     */
    public function addTableRow(string $tableName, array $data): Response
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = json_encode($value, JSON_FORCE_OBJECT);
            }
        }

        $data = array_merge($data, [
            'ZOHO_DATE_FORMAT' => self::DATE_FORMAT,
        ]);
        $response = $this->post($tableName . '?ZOHO_ACTION=ADDROW', $data);

        return $response;
    }

    /**
     * Updates a row in the given Zoho table
     */
    public function updateTableRow(string $tableName, array $data, string $where): Response
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = json_encode($value, JSON_FORCE_OBJECT);
            }
        }

        $data = array_merge($data, [
            'ZOHO_DATE_FORMAT' => self::DATE_FORMAT,
            'ZOHO_CRITERIA' => $where,
        ]);
        $response = $this->post($tableName . '?ZOHO_ACTION=UPDATE', $data);

        return $response;
    }

    public function createTable(array $tableDefinition): Response
    {
        $response = $this->post('', [
            'ZOHO_ACTION' => 'CREATETABLE',
            'ZOHO_TABLE_DESIGN' => json_encode($tableDefinition),
        ]);

        return $response;
    }

    public function deleteTable(string $tableName): Response
    {
        $response = $this->post('', ['ZOHO_ACTION' => 'DELETEVIEW', 'ZOHO_VIEW' => $tableName]);

        return $response;
    }
}

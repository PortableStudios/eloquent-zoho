<?php

namespace Portable\EloquentZoho\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Portable\EloquentZoho\ZohoClient;
use Illuminate\Support\Facades\Http;

class ZohoClientTest extends TestCase
{
    public function testInvalidConfiguration(): void
    {
        $client = new ZohoClient('', '', '', '');
        $this->assertFalse($client->configured());
        $this->assertFalse($client->connected());
    }

    public function testConfiguredNoConnection(): void
    {
        $client = new ZohoClient('host', 'post', 'email', 'workspace');
        $this->assertTrue($client->configured());
        $this->assertFalse($client->connected());
    }

    public function testConfiguredConnected(): void
    {
        $client = new ZohoClient('host', 'post', 'email', 'workspace', 'token');
        $this->assertTrue($client->configured());
        $this->assertTrue($client->connected());
    }

    public function testGenerateToken(): void
    {
        $testToken = "1323a6d00916764b4a89e59bc01a7db9";
        $testString = "#\n#Wed Sep 27 13:46:17 AEST 2023\nAUTHTOKEN=" . $testToken . "\nRESULT=TRUE";
        Http::fake([
            '*' => Http::response($testString)
        ]);

        $client = new ZohoClient('host', 'post', 'email', 'workspace', 'token');
        $token = $client->generateAuthToken('username', 'password');
        $this->assertEquals($token, $testToken);
    }
}

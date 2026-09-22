<?php

namespace Tests\Feature\Sap;

use Tests\TestCase;

class SapSqlConnectionConfigTest extends TestCase
{
    public function test_sap_sql_connection_uses_top_level_encrypt_and_trust_server_certificate_keys(): void
    {
        $connection = config('database.connections.sap_sql');

        $this->assertArrayHasKey('encrypt', $connection);
        $this->assertArrayHasKey('trust_server_certificate', $connection);
        $this->assertArrayNotHasKey('options', $connection);
    }
}

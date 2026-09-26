<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class TrustedProxyHttpsTest extends TestCase
{
    public function test_login_request_honors_forwarded_https_from_trusted_proxy(): void
    {
        $response = $this->call('GET', '/login', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
        ]);

        $response->assertOk();

        $request = $this->app['request'];
        $this->assertTrue($request->secure());
        $this->assertSame('https', $request->getScheme());
        $this->assertStringStartsWith('https://', url('/'));
    }

    public function test_login_request_without_forwarded_proto_remains_http(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        $request = $this->app['request'];
        $this->assertFalse($request->secure());
        $this->assertSame('http', $request->getScheme());
        $this->assertStringStartsWith('http://', url('/'));
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class HttpsAccessTest extends TestCase
{
    public function test_trusted_reverse_proxy_preserves_https_redirects_and_secure_session_cookie(): void
    {
        config(['trustedproxy.proxies' => ['10.20.0.10'], 'session.secure' => null]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.10'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://app.example.com/admin');

        $response->assertRedirect('https://app.example.com/login');
        $cookies = $response->headers->getCookies();
        $session = collect($cookies)->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($session);
        $this->assertTrue($session->isSecure());
    }

    public function test_lan_clients_cannot_spoof_proxy_scheme_or_host(): void
    {
        config(['trustedproxy.proxies' => ['10.20.0.10']]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.20'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example'])
            ->get('http://192.168.10.111:8000/admin')
            ->assertRedirect('http://192.168.10.111:8000/login');
    }
}

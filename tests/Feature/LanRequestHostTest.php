<?php

namespace Tests\Feature;

use Tests\TestCase;

class LanRequestHostTest extends TestCase
{
    public function test_generated_urls_follow_the_lan_host_not_loopback_or_wildcard(): void
    {
        $this->get('http://192.168.1.9:8000/up')->assertOk();

        $this->assertSame('http://192.168.1.9:8000/login', url('/login'));
        $this->assertSame('http://192.168.1.9:8000/manifest.json', asset('manifest.json'));
        $this->assertStringNotContainsString('0.0.0.0', url('/'));
        $this->assertStringNotContainsString('127.0.0.1', url('/login'));
    }

    public function test_generated_urls_still_work_on_localhost(): void
    {
        $this->get('http://127.0.0.1:8000/up')->assertOk();

        $this->assertSame('http://127.0.0.1:8000/login', url('/login'));
    }
}

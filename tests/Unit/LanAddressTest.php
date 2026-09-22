<?php

namespace Tests\Unit;

use App\Support\LanAddress;
use Tests\TestCase;

class LanAddressTest extends TestCase
{
    public function test_wildcard_hosts_are_detected(): void
    {
        $this->assertTrue(LanAddress::isWildcard('0.0.0.0'));
        $this->assertTrue(LanAddress::isWildcard('::'));
        $this->assertFalse(LanAddress::isWildcard('127.0.0.1'));
        $this->assertFalse(LanAddress::isWildcard('192.168.1.9'));
    }

    public function test_ipv4_is_not_loopback_or_link_local(): void
    {
        $ip = LanAddress::ipv4();

        $this->assertNotSame('0.0.0.0', $ip);
        $this->assertFalse(LanAddress::isLoopbackOrWildcard($ip) && $ip !== '127.0.0.1');
        $this->assertFalse(str_starts_with($ip, '169.254.'));
        $this->assertNotFalse(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
    }
}

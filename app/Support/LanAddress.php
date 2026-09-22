<?php

namespace App\Support;

class LanAddress
{
    /**
     * The computer's reachable IPv4 address on the local network.
     */
    public static function ipv4(): string
    {
        foreach (self::candidates() as $ip) {
            return $ip;
        }

        return '127.0.0.1';
    }

    /**
     * Whether this host should be treated as a loopback or wildcard bind address.
     */
    public static function isLoopbackOrWildcard(?string $host): bool
    {
        $host = strtolower(trim((string) $host, '[]'));

        return in_array($host, ['127.0.0.1', 'localhost', '::1', '0.0.0.0', '::', ''], true);
    }

    /**
     * Whether the server is bound on every interface (not a specific IP).
     */
    public static function isWildcard(?string $host): bool
    {
        $host = strtolower(trim((string) $host, '[]'));

        return in_array($host, ['0.0.0.0', '::'], true);
    }

    /**
     * @return list<string>
     */
    public static function candidates(): array
    {
        $found = [];

        foreach ([self::fromUdpSocket(), ...self::fromInterfaces()] as $ip) {
            if ($ip && self::isUsable($ip) && ! in_array($ip, $found, true)) {
                $found[] = $ip;
            }
        }

        usort($found, function (string $a, string $b): int {
            return self::rank($a) <=> self::rank($b);
        });

        return $found;
    }

    private static function fromUdpSocket(): ?string
    {
        try {
            $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 0.4);

            if (! is_resource($socket)) {
                return null;
            }

            $name = stream_socket_get_name($socket, false);
            fclose($socket);

            if (! is_string($name)) {
                return null;
            }

            $ip = explode(':', $name)[0] ?? null;

            return is_string($ip) ? $ip : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function fromInterfaces(): array
    {
        $ips = [];

        foreach (@net_get_interfaces() ?: [] as $interface) {
            foreach ($interface['unicast'] ?? [] as $address) {
                $ip = $address['address'] ?? null;

                if (is_string($ip)) {
                    $ips[] = $ip;
                }
            }
        }

        return $ips;
    }

    private static function isUsable(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (self::isLoopbackOrWildcard($ip) || str_starts_with($ip, '169.254.')) {
            return false;
        }

        return true;
    }

    private static function rank(string $ip): int
    {
        if (str_starts_with($ip, '192.168.')) {
            return 0;
        }

        if (str_starts_with($ip, '10.')) {
            return 1;
        }

        if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip) === 1) {
            return 2;
        }

        return 3;
    }
}

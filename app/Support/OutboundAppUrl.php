<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Absolute application root for links that leave the browser (email, SMS).
 * Loopback APP_URL / request hosts are rewritten to a LAN address when the
 * local server is bound for network access, so verification links open off-PC.
 */
class OutboundAppUrl
{
    public static function root(?Request $request = null): string
    {
        // PHPUnit stays on APP_URL unless a test explicitly sets app.public_url.
        if (app()->environment('testing') && blank(config('app.public_url'))) {
            return rtrim((string) config('app.url'), '/');
        }

        $request ??= app()->bound('request') ? request() : null;

        if ($request instanceof Request) {
            $host = strtolower($request->getHost());
            if (! LanAddress::isLoopbackOrWildcard($host)) {
                return rtrim($request->root(), '/');
            }
        }

        $configured = rtrim((string) config('app.url'), '/');
        $public = rtrim((string) (config('app.public_url') ?: ''), '/');

        if ($public !== '') {
            return $public;
        }

        $configuredHost = strtolower((string) (parse_url($configured, PHP_URL_HOST) ?: ''));
        if ($configuredHost !== '' && ! LanAddress::isLoopbackOrWildcard($configuredHost)) {
            return $configured;
        }

        $lan = LanAddress::ipv4();
        if (LanAddress::isLoopbackOrWildcard($lan)) {
            return $configured !== '' ? $configured : 'http://127.0.0.1:8000';
        }

        $scheme = parse_url($configured, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($configured, PHP_URL_PORT);
        if (! $port && $request instanceof Request) {
            $port = $request->getPort();
        }
        $port = $port ?: 8000;

        if (($scheme === 'http' && (int) $port === 80) || ($scheme === 'https' && (int) $port === 443)) {
            return "{$scheme}://{$lan}";
        }

        return "{$scheme}://{$lan}:{$port}";
    }

    /**
     * Run a callback while absolute URLs use the outbound root (e.g. signed mail links).
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withRoot(callable $callback): mixed
    {
        $generator = app('url');
        $generator->forceRootUrl(self::root());

        try {
            return $callback();
        } finally {
            // Restore Host-header-based URL generation for in-app requests.
            $generator->forceRootUrl(null);
        }
    }
}

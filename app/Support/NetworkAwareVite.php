<?php

namespace App\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Request;

class NetworkAwareVite extends Vite
{
    /**
     * Point the Vite dev server at the host the browser used, not 0.0.0.0 / localhost.
     */
    protected function hotAsset($asset)
    {
        $root = rtrim((string) file_get_contents($this->hotFile()), '/');
        $root = $this->rewriteDevServerHost($root);

        return $root.'/'.$asset;
    }

    private function rewriteDevServerHost(string $root): string
    {
        $requestHost = Request::getHost();

        if ($requestHost === '' || ! $this->appIsServingHttp()) {
            return $root;
        }

        $devHost = parse_url($root, PHP_URL_HOST);
        $devPort = parse_url($root, PHP_URL_PORT) ?: 5173;
        $scheme = parse_url($root, PHP_URL_SCHEME) ?: Request::getScheme();

        if (! is_string($devHost)) {
            return $root;
        }

        if (! LanAddress::isLoopbackOrWildcard($devHost) && $devHost === $requestHost) {
            return $root;
        }

        if (LanAddress::isLoopbackOrWildcard($requestHost)) {
            return $root;
        }

        return "{$scheme}://{$requestHost}:{$devPort}";
    }

    private function appIsServingHttp(): bool
    {
        return ! app()->runningInConsole() || app()->runningUnitTests();
    }
}

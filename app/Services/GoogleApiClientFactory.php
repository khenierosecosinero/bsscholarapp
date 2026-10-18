<?php

namespace App\Services;

use Google\Client;
use GuzzleHttp\Client as GuzzleClient;
use RuntimeException;

class GoogleApiClientFactory
{
    public function make(): Client
    {
        $clientId = trim((string) config('services.google.client_id'));
        $clientSecret = trim((string) config('services.google.client_secret'));
        // Use the configured URI exactly — do not strip trailing slashes or rewrite the host.
        // Google Cloud Console matching is character-exact against createAuthUrl()'s redirect_uri.
        $redirect = trim((string) config('services.google.redirect'));

        if ($clientId === '' || $clientSecret === '' || $redirect === '') {
            throw new RuntimeException(
                'Google Drive OAuth is not configured. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in the environment.'
            );
        }

        if (! str_starts_with($redirect, 'http://') && ! str_starts_with($redirect, 'https://')) {
            throw new RuntimeException('GOOGLE_REDIRECT_URI must be an absolute http(s) URL.');
        }

        $client = new Client();
        $client->setHttpClient(new GuzzleClient([
            'timeout' => 30,
            'verify' => $this->certificateBundle(),
        ]));
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirect);

        return $client;
    }

    public function certificateBundle(): string
    {
        $path = base_path('certs/cacert.pem');

        if (! is_file($path)) {
            throw new RuntimeException('CA certificate bundle is missing at certs/cacert.pem.');
        }

        return $path;
    }
}

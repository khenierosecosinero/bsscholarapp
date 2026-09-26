<?php

namespace App\Services;

use Google\Client;
use GuzzleHttp\Client as GuzzleClient;
use RuntimeException;

class GoogleApiClientFactory
{
    public function make(): Client
    {
        $client = new Client();
        $client->setHttpClient(new GuzzleClient([
            'timeout' => 30,
            'verify' => $this->certificateBundle(),
        ]));
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));

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

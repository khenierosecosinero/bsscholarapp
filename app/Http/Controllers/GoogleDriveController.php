<?php

namespace App\Http\Controllers;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleDriveController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        $client = new Client();

        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));

        $client->addScope(Drive::DRIVE_FILE);

        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return redirect()->away($client->createAuthUrl());
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect('/')
                ->with('error', 'Google authorization was cancelled.');
        }

        if (!$request->has('code')) {
            return redirect('/')
                ->with('error', 'Google did not return an authorization code.');
        }

        $client = new Client();

        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));

        $token = $client->fetchAccessTokenWithAuthCode(
            $request->string('code')->toString()
        );

        if (isset($token['error'])) {
            return redirect('/')
                ->with('error', 'Google authorization failed.');
        }

        session([
            'google_drive_token' => $token,
        ]);

        return redirect('/')
            ->with('success', 'Google Drive connected successfully.');
    }
}
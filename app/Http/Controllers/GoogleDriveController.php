<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveFolder;
use App\Models\User;
use App\Services\GoogleApiClientFactory;
use App\Services\GoogleDriveService;
use Google\Service\Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GoogleDriveController extends Controller
{
    private const OAUTH_STATE_SESSION_KEY = 'google_drive_oauth_state';

    private const OAUTH_STATE_TTL_SECONDS = 600;

    public function redirectToGoogle(Request $request, GoogleApiClientFactory $googleClients): RedirectResponse
    {
        // If Google Cloud Console lists /auth/google (without /callback) as the redirect URI,
        // the authorization response lands here. Forward it to the real callback handler.
        if ($request->filled('code') || $request->filled('error') || $request->filled('state')) {
            return $this->handleGoogleCallback($request, $googleClients);
        }

        $this->assertDriveManager();

        try {
            $client = $googleClients->make();
        } catch (Throwable $e) {
            return redirect($this->afterGoogleRedirect())
                ->with('error', $e->getMessage());
        }

        $state = $this->makeOAuthState((int) auth()->id());
        session([self::OAUTH_STATE_SESSION_KEY => $state]);

        $client->setScopes([Drive::DRIVE_FILE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setState($state);

        $authUrl = $client->createAuthUrl();

        Log::info('Google Drive OAuth authorization started.', [
            'user_id' => auth()->id(),
            'redirect_uri' => config('services.google.redirect'),
            'has_state' => true,
        ]);

        return redirect()->away($authUrl);
    }

    public function handleGoogleCallback(Request $request, GoogleApiClientFactory $googleClients): RedirectResponse
    {
        $queryKeys = array_values(array_filter(array_keys($request->query()), fn ($key) => is_string($key)));
        $authCode = $this->oauthAuthorizationCode($request);
        $refererHost = $this->oauthRefererHost($request);
        $pendingState = is_string(session(self::OAUTH_STATE_SESSION_KEY))
            && session(self::OAUTH_STATE_SESSION_KEY) !== '';

        Log::info('Google Drive OAuth callback received.', [
            'query_keys' => $queryKeys,
            'has_code' => $authCode !== null,
            'has_error' => $request->filled('error'),
            'has_state' => $request->filled('state'),
            'has_query_string' => $request->getQueryString() !== null && $request->getQueryString() !== '',
            'referer_host' => $refererHost,
            'pending_oauth_state' => $pendingState,
            'authenticated' => auth()->check(),
        ]);

        if ($request->filled('error')) {
            $googleError = $request->string('error')->toString();
            $description = $request->string('error_description')->toString();

            Log::warning('Google Drive OAuth provider returned an error.', [
                'error' => $googleError,
                'error_description' => $description !== '' ? $description : null,
            ]);

            session()->forget(self::OAUTH_STATE_SESSION_KEY);

            return redirect($this->afterGoogleRedirect())
                ->with('error', $this->oauthProviderErrorMessage($googleError, $description));
        }

        if ($authCode === null) {
            Log::warning('Google Drive OAuth callback missing authorization code.', [
                'query_keys' => $queryKeys,
                'referer_host' => $refererHost,
                'pending_oauth_state' => $pendingState,
                'likely_direct_visit' => $refererHost === null || ! str_contains((string) $refererHost, 'google'),
            ]);

            session()->forget(self::OAUTH_STATE_SESSION_KEY);

            // Bare /auth/google/callback (no code/error) is almost never a Google redirect.
            // Google always returns ?code=…&state=… or ?error=…. Visiting the URI from Settings
            // or a linkified flash message must not look like a failed Google response.
            $message = $pendingState || ($refererHost !== null && str_contains($refererHost, 'google'))
                ? 'Google authorization did not finish. Open Settings and choose Connect Google Drive, then allow access on the Google screen. Do not open the redirect URI yourself.'
                : 'Open Settings and choose Connect Google Drive to authorize. Do not open the callback or redirect URI in the browser address bar.';

            return redirect($this->afterGoogleRedirect())
                ->with('error', $message);
        }

        $stateCheck = $this->validateOAuthState($request->string('state')->toString());
        if ($stateCheck['ok'] !== true) {
            Log::warning('Google Drive OAuth state validation failed.', [
                'reason' => $stateCheck['reason'],
            ]);

            session()->forget(self::OAUTH_STATE_SESSION_KEY);

            return redirect($this->afterGoogleRedirect())
                ->with('error', 'Google authorization could not be verified (invalid or expired state). Please connect Google Drive again from Settings.');
        }

        $connectedBy = $stateCheck['user_id'] ?? auth()->id();

        try {
            $client = $googleClients->make();
            $token = $client->fetchAccessTokenWithAuthCode($authCode);
        } catch (Throwable $e) {
            Log::warning('Google Drive OAuth token exchange failed.', [
                'message' => $e->getMessage(),
            ]);

            session()->forget(self::OAUTH_STATE_SESSION_KEY);

            return redirect($this->afterGoogleRedirect())
                ->with('error', 'Google authorization failed while exchanging the authorization code. Check the OAuth client, redirect URI, and that this Google account is allowed to use the app.');
        }

        if (! is_array($token) || isset($token['error']) || empty($token['access_token'])) {
            Log::warning('Google Drive OAuth returned an invalid token payload.', [
                'has_error' => is_array($token) && isset($token['error']),
                'error' => is_array($token) ? ($token['error'] ?? null) : null,
            ]);

            session()->forget(self::OAUTH_STATE_SESSION_KEY);

            return redirect($this->afterGoogleRedirect())
                ->with('error', 'Google authorization failed. The token response was incomplete.');
        }

        if (empty($token['refresh_token'])) {
            Log::warning('Google Drive OAuth connected without a refresh token.');
        }

        app(GoogleDriveService::class)->storeToken($token, $connectedBy ? (int) $connectedBy : null);
        session()->forget(self::OAUTH_STATE_SESSION_KEY);

        if (! auth()->check() && $connectedBy) {
            $user = User::query()->find($connectedBy);
            if ($user && (
                $user->isAdmin()
                || ($user->isScholarStaff() && $user->status === User::STATUS_APPROVED)
            )) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        }

        try {
            app(GoogleDriveService::class)->ensureOrganizationFolders();
        } catch (Throwable $e) {
            Log::warning('Could not create BSSA organization folders after connecting Google Drive.', [
                'message' => $e->getMessage(),
            ]);
        }

        Log::info('Google Drive OAuth connected successfully.', [
            'connected_by' => $connectedBy,
            'has_refresh_token' => ! empty($token['refresh_token']),
        ]);

        return redirect($this->afterGoogleRedirect())
            ->with('success', 'Google Drive connected successfully.');
    }

    private function afterGoogleRedirect(): string
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return route('admin.settings');
        }

        if ($user?->isScholarStaff()) {
            return route('staff.settings');
        }

        return auth()->check()
            ? route('google.drive.test')
            : route('login');
    }

    public function showTest(GoogleDriveService $googleDrive): View
    {
        $this->assertDriveManager();

        $connected = $googleDrive->isConnected(true);
        $organizationReady = false;

        if ($connected) {
            try {
                $googleDrive->ensureOrganizationFolders();
                $organizationReady = GoogleDriveFolder::query()
                    ->where('folder_key', GoogleDriveService::ORG_ROOT_FOLDER_KEY)
                    ->exists()
                    && GoogleDriveFolder::query()
                        ->where('folder_key', GoogleDriveService::DOCUMENTS_ROOT_FOLDER_KEY)
                        ->exists()
                    && GoogleDriveFolder::query()
                        ->where('folder_key', GoogleDriveService::ATTENDANCE_ROOT_FOLDER_KEY)
                        ->exists();
            } catch (Throwable $e) {
                Log::warning('Could not create BSSA organization folders from the Drive test page.', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return view('google-drive.test', [
            'connected' => $connected,
            'attendanceReady' => $organizationReady,
            'organizationReady' => $organizationReady,
        ]);
    }

    public function upload(Request $request, GoogleDriveService $googleDrive)
    {
        $this->assertDriveManager();

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
            ],
        ]);

        try {
            $googleDrive->upload(
                $request->file('file')
            );

            return back()->with(
                'success',
                'File uploaded to Google Drive successfully.'
            );
        } catch (Throwable $e) {
            return back()->with(
                'error',
                'Google Drive upload failed: '.$e->getMessage()
            );
        }
    }

    private function assertDriveManager(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && (
                $user->isAdmin()
                || ($user->isScholarStaff() && $user->status === User::STATUS_APPROVED)
            ),
            403
        );
    }

    private function makeOAuthState(int $userId): string
    {
        $payload = [
            'uid' => $userId,
            'nonce' => Str::random(40),
            'exp' => time() + self::OAUTH_STATE_TTL_SECONDS,
        ];

        $body = $this->urlSafeBase64Encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $body, $this->oauthStateKey());

        return $body.'.'.$signature;
    }

    /**
     * @return array{ok: bool, reason?: string, user_id?: int}
     */
    private function validateOAuthState(string $state): array
    {
        if ($state === '') {
            return ['ok' => false, 'reason' => 'missing'];
        }

        $sessionState = session(self::OAUTH_STATE_SESSION_KEY);
        if (! is_string($sessionState) || $sessionState === '' || ! hash_equals($sessionState, $state)) {
            // Fall back to signed-state verification when the session cookie was not restored.
            return $this->validateSignedOAuthState($state);
        }

        return $this->validateSignedOAuthState($state);
    }

    /**
     * @return array{ok: bool, reason?: string, user_id?: int}
     */
    private function validateSignedOAuthState(string $state): array
    {
        $parts = explode('.', $state, 2);
        if (count($parts) !== 2) {
            return ['ok' => false, 'reason' => 'malformed'];
        }

        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, $this->oauthStateKey());
        if (! hash_equals($expected, $signature)) {
            return ['ok' => false, 'reason' => 'signature'];
        }

        try {
            $json = $this->urlSafeBase64Decode($body);
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['ok' => false, 'reason' => 'payload'];
        }

        if (! is_array($payload)) {
            return ['ok' => false, 'reason' => 'payload'];
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < time()) {
            return ['ok' => false, 'reason' => 'expired'];
        }

        $userId = (int) ($payload['uid'] ?? 0);
        if ($userId < 1) {
            return ['ok' => false, 'reason' => 'user'];
        }

        return ['ok' => true, 'user_id' => $userId];
    }

    private function oauthStateKey(): string
    {
        return (string) config('app.key');
    }

    private function urlSafeBase64Encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function urlSafeBase64Decode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid OAuth state encoding.');
        }

        return $decoded;
    }

    private function oauthProviderErrorMessage(string $error, string $description): string
    {
        return match ($error) {
            'access_denied' => 'Google authorization was denied. Connect Google Drive again and allow access.',
            'invalid_request', 'unauthorized_client', 'unsupported_response_type' => 'Google rejected the authorization request. Confirm GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and the Authorized redirect URI shown in Settings (Google Cloud Console must match it exactly).',
            'redirect_uri_mismatch' => 'Google rejected the redirect URI. Copy the Authorized redirect URI from Settings into Google Cloud Console exactly (including http:// and the port).',
            default => $description !== ''
                ? 'Google authorization failed: '.$description
                : 'Google authorization was cancelled or failed ('.$error.').',
        };
    }

    private function oauthAuthorizationCode(Request $request): ?string
    {
        $code = $request->input('code', $request->query('code'));
        if (! is_string($code)) {
            return null;
        }

        $code = trim($code);

        return $code !== '' ? $code : null;
    }

    private function oauthRefererHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}

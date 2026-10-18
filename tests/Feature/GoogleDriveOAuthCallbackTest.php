<?php

namespace Tests\Feature;

use App\Models\GoogleDriveConnection;
use App\Models\User;
use App\Services\GoogleApiClientFactory;
use App\Services\GoogleDriveService;
use Google\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleDriveOAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_without_code_shows_a_clear_error(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('google.callback'))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('error');

        $this->assertStringContainsString(
            'Connect Google Drive',
            session('error')
        );
        $this->assertStringNotContainsString(
            'http://127.0.0.1:8000/auth/google/callback',
            (string) session('error')
        );
        $this->assertSame(0, GoogleDriveConnection::query()->count());
    }

    public function test_bare_callback_with_pending_state_asks_to_finish_google_consent(): void
    {
        $admin = $this->makeAdmin();
        $state = $this->makeValidState($admin->id);

        $this->actingAs($admin)
            ->withSession(['google_drive_oauth_state' => $state])
            ->get(route('google.callback'))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('did not finish', (string) session('error'));
        $this->assertSame(0, GoogleDriveConnection::query()->count());
    }

    public function test_callback_with_google_error_shows_provider_message(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('google.callback', [
                'error' => 'access_denied',
                'error_description' => 'Access denied by user',
            ]))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('denied', strtolower((string) session('error')));
    }

    public function test_callback_with_invalid_state_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['google_drive_oauth_state' => 'valid-session-state'])
            ->get(route('google.callback', [
                'code' => 'fake-code',
                'state' => 'tampered-state',
            ]))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('state', strtolower((string) session('error')));
        $this->assertSame(0, GoogleDriveConnection::query()->count());
    }

    public function test_successful_callback_stores_encrypted_token(): void
    {
        $admin = $this->makeAdmin();
        $state = $this->makeValidState($admin->id);

        $googleClient = Mockery::mock(Client::class);
        $googleClient->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('auth-code-123')
            ->andReturn([
                'access_token' => 'access-token-value',
                'refresh_token' => 'refresh-token-value',
                'expires_in' => 3600,
                'created' => time(),
            ]);

        $this->mock(GoogleApiClientFactory::class, function ($factory) use ($googleClient) {
            $factory->shouldReceive('make')->andReturn($googleClient);
        });

        $this->mock(GoogleDriveService::class, function ($drive) {
            $drive->shouldReceive('storeToken')
                ->once()
                ->withArgs(function (array $token, $connectedBy) {
                    return ($token['access_token'] ?? null) === 'access-token-value'
                        && ($token['refresh_token'] ?? null) === 'refresh-token-value'
                        && (int) $connectedBy > 0;
                });
            $drive->shouldReceive('ensureOrganizationFolders')->once();
        });

        $this->actingAs($admin)
            ->withSession(['google_drive_oauth_state' => $state])
            ->get(route('google.callback', [
                'code' => 'auth-code-123',
                'state' => $state,
            ]))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success');
    }

    public function test_authorization_start_forwards_callback_payload_on_auth_google_route(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('google.redirect', [
                'error' => 'access_denied',
            ]))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('denied', strtolower((string) session('error')));
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'OAuth Admin',
            'scholar_id' => 'ADMIN-OAUTH-001',
            'email' => 'oauth-admin-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeValidState(int $userId): string
    {
        $payload = [
            'uid' => $userId,
            'nonce' => 'test-nonce-'.uniqid(),
            'exp' => time() + 600,
        ];
        $body = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $body, (string) config('app.key'));

        return $body.'.'.$signature;
    }
}

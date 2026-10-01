<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveFolder;
use App\Models\User;
use App\Services\AttendanceDriveStorageService;
use App\Services\GoogleApiClientFactory;
use App\Services\GoogleDriveService;
use Google\Service\Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class GoogleDriveController extends Controller
{
    public function redirectToGoogle(GoogleApiClientFactory $googleClients): RedirectResponse
    {
        $this->assertDriveManager();

        $client = $googleClients->make();

        $client->addScope(Drive::DRIVE_FILE);

        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return redirect()->away($client->createAuthUrl());
    }

    public function handleGoogleCallback(Request $request, GoogleApiClientFactory $googleClients): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect($this->afterGoogleRedirect())
                ->with('error', 'Google authorization was cancelled.');
        }

        if (! $request->has('code')) {
            return redirect($this->afterGoogleRedirect())
                ->with('error', 'Google did not return an authorization code.');
        }

        $client = $googleClients->make();

        try {
            $token = $client->fetchAccessTokenWithAuthCode(
                $request->string('code')->toString()
            );
        } catch (Throwable $e) {
            return redirect($this->afterGoogleRedirect())
                ->with('error', 'Google authorization failed: '.$e->getMessage());
        }

        if (isset($token['error'])) {
            return redirect($this->afterGoogleRedirect())
                ->with('error', 'Google authorization failed.');
        }

        app(GoogleDriveService::class)->storeToken($token, auth()->id());

        try {
            app(AttendanceDriveStorageService::class)->syncConfiguredYearFolders();
        } catch (Throwable $e) {
            Log::warning('Could not create BSSA Attendance year folders after connecting Google Drive.', [
                'message' => $e->getMessage(),
            ]);
        }

        return redirect($this->afterGoogleRedirect())
            ->with('success', 'Google Drive connected successfully.');
    }

    private function afterGoogleRedirect(): string
    {
        return auth()->check()
            ? route('google.drive.test')
            : route('login');
    }

    public function showTest(GoogleDriveService $googleDrive): View
    {
        $this->assertDriveManager();

        $connected = $googleDrive->isConnected();
        $attendanceReady = false;

        if ($connected) {
            try {
                app(AttendanceDriveStorageService::class)->syncConfiguredYearFolders();
                $attendanceReady = GoogleDriveFolder::query()
                    ->where('folder_key', GoogleDriveService::ATTENDANCE_ROOT_FOLDER_KEY)
                    ->exists();
            } catch (Throwable $e) {
                Log::warning('Could not create BSSA Attendance folders from the Drive test page.', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return view('google-drive.test', [
            'connected' => $connected,
            'attendanceReady' => $attendanceReady,
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
}

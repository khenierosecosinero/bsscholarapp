<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountService
{
    public function __construct(private ScholarService $scholar) {}

    /**
     * Initialize a newly registered account with linked records.
     * User data is retained permanently unless the account is explicitly deleted.
     */
    public function provisionNewAccount(User $user): void
    {
        $this->scholar->ensureUserDocuments($user);

        if ($user->notification_preferences === null) {
            $user->notification_preferences = $user->defaultNotificationPreferences();
            $user->save();
        }

        $this->scholar->logActivity($user, 'account', 'Account created');

        $programName = $user->scholarshipProgram?->name ?? 'your assigned scholarship program';

        if ($user->isPendingApproval()) {
            $this->scholar->notify(
                $user,
                'Account Pending Approval',
                "Your account has been created and linked to {$programName}. Scholar Staff will review your registration before full access is granted.",
                'system'
            );

            return;
        }

        $this->scholar->notify(
            $user,
            'Welcome to BSSA',
            "Your scholar account has been created and linked to {$programName}. Your profile, documents, and activity records are saved permanently. You may log in at any time, even after a long period of inactivity.",
            'system'
        );
    }

    /**
     * Initialize a newly registered scholar staff account.
     */
    public function provisionNewStaffAccount(User $user): void
    {
        if ($user->notification_preferences === null) {
            $user->notification_preferences = $user->defaultNotificationPreferences();
            $user->save();
        }

        $programName = $user->scholarshipProgram?->name ?? 'your assigned scholarship program';

        $this->scholar->logActivity($user, 'account', 'Scholar staff account created');
        $this->scholar->notify(
            $user,
            'Scholar Staff Account Created',
            "Your account is now linked to {$programName}. You may log in at any time to manage scholars in your assigned location, even after a long period of inactivity.",
            'system'
        );
    }

    /**
     * Permanently delete the user and all associated database records and files.
     */
    public function permanentlyDelete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->deleteStoredFiles($user);
            $user->delete();
        });
    }

    private function deleteStoredFiles(User $user): void
    {
        $disk = Storage::disk('public');

        foreach ($user->documents()->whereNotNull('file_path')->get() as $document) {
            if ($disk->exists($document->file_path)) {
                $disk->delete($document->file_path);
            }
        }

        $documentDirectory = "documents/{$user->id}";
        if ($disk->exists($documentDirectory)) {
            $disk->deleteDirectory($documentDirectory);
        }

        if ($user->avatar_path && $disk->exists($user->avatar_path)) {
            $disk->delete($user->avatar_path);
        }
    }
}

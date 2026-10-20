<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\ScholarshipClub;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountService
{
    public function __construct(
        private ScholarService $scholar,
        private UserSequenceService $userSequence,
    ) {}

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

        $programLabel = $user->scholarshipProgram?->programLabel() ?? 'your assigned scholarship program';

        if ($user->isPendingApproval()) {
            $this->scholar->notify(
                $user,
                'Account Pending Approval',
                "Your account has been created and linked to {$programLabel}. Scholar Staff will review your registration before full access is granted.",
                'system'
            );
            app(WorkflowMailService::class)->notifyReviewersOfScholar($user);

            return;
        }

        $this->scholar->notify(
            $user,
            'Welcome to BSSA',
            "Your scholar account has been created and linked to {$programLabel}. Your profile, documents, and activity records are saved permanently. You may log in at any time, even after a long period of inactivity.",
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

        $programLabel = $user->scholarshipProgram?->programLabel() ?? 'your assigned scholarship program';

        $this->scholar->logActivity($user, 'account', 'Scholar staff account created');

        if ($user->isStaffPendingApproval()) {
            $this->scholar->notify(
                $user,
                'Scholar Staff Account Pending Approval',
                "Your account has been created and linked to {$programLabel}. An administrator must approve your registration before you can log in or access the Scholar Staff section.",
                'system'
            );
            app(WorkflowMailService::class)->notifyAdministratorsOfStaff($user);

            return;
        }

        $this->scholar->notify(
            $user,
            'Scholar Staff Account Created',
            "Your account is now linked to {$programLabel}. You may log in at any time to manage scholars in your assigned location, even after a long period of inactivity.",
            'system'
        );
    }

    /**
     * Remove leftover rejected scholar rows that still reserve an email or scholar ID.
     * Active/pending accounts are never touched.
     */
    public function purgeRejectedScholarForRegistration(?string $email, ?string $scholarId): void
    {
        $email = $email ? strtolower(trim($email)) : '';
        $scholarId = $scholarId ? trim($scholarId) : '';

        if ($email === '' && $scholarId === '') {
            return;
        }

        $query = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_REJECTED)
            ->where(function ($builder) use ($email, $scholarId) {
                if ($email !== '') {
                    $builder->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                }
                if ($scholarId !== '') {
                    $builder->orWhere('scholar_id', $scholarId);
                }
            });

        foreach ($query->get() as $rejected) {
            $this->permanentlyDelete($rejected);
        }
    }

    /**
     * Permanently delete the user and all associated database records and files.
     * Unique identifiers (email, scholar_id) are released for re-registration.
     */
    public function permanentlyDelete(User $user): void
    {
        if ($user->isPermanentAdmin()) {
            throw ValidationException::withMessages([
                'account' => 'The designated administrator account is permanent and cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($user) {
            DB::table('users')->lockForUpdate()->get(['id']);

            $isStaff = $user->isScholarStaff();
            $deletedUserId = (int) $user->id;
            $ownedClubId = null;
            $email = $user->email ? strtolower(trim((string) $user->email)) : null;
            $scholarId = $user->scholar_id;

            if ($isStaff) {
                $ownedClubId = $user->scholarship_club_id
                    ? (int) $user->scholarship_club_id
                    : ScholarshipClub::query()->where('created_by', $user->id)->value('id');
                $ownedClubId = $ownedClubId ? (int) $ownedClubId : null;
            }

            $this->deleteStoredFiles($user);
            $this->deleteOwnedDatabaseRecords($user);

            if ($email) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }

            if ($user->isPermanentAdmin() || ! $user->delete()) {
                throw ValidationException::withMessages([
                    'account' => 'The designated administrator account is permanent and cannot be deleted.',
                ]);
            }

            // Ensure the row is gone even if a model event prevented Eloquent delete silently.
            DB::table('users')->where('id', $deletedUserId)->delete();
            if ($email) {
                DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->delete();
            }
            if (filled($scholarId)) {
                DB::table('users')->where('scholar_id', $scholarId)->delete();
            }

            if ($isStaff) {
                ScholarshipClub::releaseAfterStaffPermanentDeletion($ownedClubId, $deletedUserId);
            }

            ScholarshipClub::purgeOrphanedClubs();

            $this->userSequence->compact();
        });

        $this->userSequence->resetAutoIncrement();
    }

    public function storeAvatar(User $user, UploadedFile $file): void
    {
        $disk = Storage::disk('public');
        $oldPath = $user->avatar_path;
        $path = $file->store('avatars/'.$user->id, 'public');

        $user->forceFill(['avatar_path' => $path])->save();

        if ($oldPath && $oldPath !== $path && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }
    }

    /**
     * Remove every DB row that belongs to this user (or only references them)
     * so unique identifiers and FK constraints never block re-registration.
     */
    private function deleteOwnedDatabaseRecords(User $user): void
    {
        $userId = (int) $user->id;

        $this->nullifyUserReferences($userId);

        $ownedDeletes = [
            ['event_registrations', 'user_id'],
            ['attendances', 'user_id'],
            ['documents', 'user_id'],
            ['user_activities', 'user_id'],
            ['scholar_notifications', 'user_id'],
            ['announcement_reads', 'user_id'],
            ['scholar_suspensions', 'user_id'],
            ['admin_backup_emails', 'user_id'],
            ['attendance_session_logs', 'staff_id'],
            ['sessions', 'user_id'],
        ];

        foreach ($ownedDeletes as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->where($column, $userId)->delete();
        }
    }

    private function nullifyUserReferences(int $userId): void
    {
        $nullableRefs = [
            ['documents', 'reviewed_by'],
            ['events', 'attendance_opened_by'],
            ['events', 'attendance_closed_by'],
            ['academic_settings', 'updated_by'],
            ['google_drive_connections', 'connected_by'],
            ['scholarship_clubs', 'created_by'],
            ['scholarship_club_schools', 'created_by'],
            ['scholar_suspensions', 'issued_by'],
            ['scholar_suspensions', 'lifted_by'],
        ];

        foreach ($nullableRefs as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->where($column, $userId)->update([$column => null]);
        }
    }

    private function deleteStoredFiles(User $user): void
    {
        $disk = Storage::disk('public');
        $userId = (int) $user->id;

        foreach ($user->documents()->get() as $document) {
            $this->deleteDocumentFiles($document);
        }

        foreach (Attendance::query()->where('user_id', $userId)->get() as $attendance) {
            $this->deleteAttendanceFiles($attendance);
        }

        $documentDirectory = "documents/{$userId}";
        if ($disk->exists($documentDirectory)) {
            $disk->deleteDirectory($documentDirectory);
        }

        $photoDirectory = "attendance_photos/{$userId}";
        if ($disk->exists($photoDirectory)) {
            $disk->deleteDirectory($photoDirectory);
        }

        $avatarDirectory = "avatars/{$userId}";
        if ($disk->exists($avatarDirectory)) {
            $disk->deleteDirectory($avatarDirectory);
        } elseif ($user->avatar_path && $disk->exists($user->avatar_path)) {
            $disk->delete($user->avatar_path);
        }
    }

    private function deleteDocumentFiles(Document $document): void
    {
        try {
            if (app()->bound(DocumentStorageService::class)) {
                app(DocumentStorageService::class)->deleteStoredFile($document);

                return;
            }
        } catch (Throwable $e) {
            Log::warning('Document cleanup during account deletion failed.', [
                'document_id' => $document->id,
                'message' => $e->getMessage(),
            ]);
        }

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }
    }

    private function deleteAttendanceFiles(Attendance $attendance): void
    {
        if ($attendance->photo_path && Storage::disk('public')->exists($attendance->photo_path)) {
            Storage::disk('public')->delete($attendance->photo_path);
        }
    }
}

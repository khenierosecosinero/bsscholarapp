<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AttendanceDriveStorageService
{
    public function __construct(
        private GoogleDriveService $drive,
        private AcademicSettingsService $academic,
    ) {}

    public function storeApprovedPhoto(Attendance $attendance): void
    {
        $attendance->loadMissing(['user', 'event']);

        if ($attendance->status !== Attendance::STATUS_APPROVED) {
            return;
        }

        $scholar = $attendance->user;
        $event = $attendance->event;

        if (! $scholar?->isScholar() || ! $event || ! $attendance->hasPhoto()) {
            return;
        }

        if (filled($attendance->google_drive_file_id)) {
            return;
        }

        if (! $this->drive->isConnected()) {
            Log::info('Skipped BSSA Attendance Drive upload because Google Drive is not connected.', [
                'attendance_id' => $attendance->id,
            ]);

            return;
        }

        $path = $attendance->photo_path;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            Log::warning('Approved attendance has no local photo to upload to Drive.', [
                'attendance_id' => $attendance->id,
            ]);

            return;
        }

        $contents = Storage::disk('public')->get($path);
        if ($contents === null || $contents === '') {
            Log::warning('Approved attendance photo could not be read for Drive upload.', [
                'attendance_id' => $attendance->id,
            ]);

            return;
        }

        $year = $this->yearLabel($attendance);
        $eventTitle = $this->drive->sanitizeDriveName((string) $event->title) ?: 'Event';
        $folderId = $this->drive->getOrCreateAttendanceEventFolder(
            $year,
            (string) $scholar->scholar_id,
            (string) $scholar->full_name,
            (int) $event->id,
            $eventTitle
        );
        $fileName = $this->photoFileName($scholar, $eventTitle, $attendance);

        $existing = $this->drive->findChildFile($fileName, $folderId);
        if ($existing) {
            $attendance->update([
                'google_drive_folder_id' => $folderId,
                'google_drive_file_id' => $existing->getId(),
                'google_drive_web_link' => $existing->getWebViewLink(),
            ]);

            return;
        }

        try {
            $uploaded = $this->drive->uploadRaw(
                $contents,
                $folderId,
                $fileName,
                Storage::disk('public')->mimeType($path) ?: 'image/jpeg'
            );
        } catch (RuntimeException $e) {
            Log::warning('BSSA Attendance photo upload failed.', [
                'attendance_id' => $attendance->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $attendance->update([
            'google_drive_folder_id' => $folderId,
            'google_drive_file_id' => $uploaded->getId(),
            'google_drive_web_link' => $uploaded->getWebViewLink(),
        ]);
    }

    public function removeStoredPhoto(Attendance $attendance): void
    {
        if (! filled($attendance->google_drive_file_id)) {
            return;
        }

        $this->drive->deleteFileIfPresent($attendance->google_drive_file_id);

        $attendance->update([
            'google_drive_file_id' => null,
            'google_drive_web_link' => null,
        ]);
    }

    public function syncConfiguredYearFolders(): void
    {
        if (! $this->drive->isConnected()) {
            return;
        }

        try {
            $this->drive->getOrCreateAttendanceRootFolder();

            foreach ($this->academic->managedYears() as $year) {
                $this->drive->getOrCreateAttendanceYearFolder($year->periodLabel());
            }
        } catch (Throwable $e) {
            Log::warning('Could not sync BSSA Attendance academic year folders.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function yearLabel(Attendance $attendance): string
    {
        $start = (int) ($attendance->academic_year_start ?? 0);
        $end = (int) ($attendance->academic_year_end ?? 0);

        if ($start >= 2000 && $end === $start + 1) {
            return $start.'–'.$end;
        }

        return $this->academic->current()->periodLabel();
    }

    private function photoFileName(User $scholar, string $eventTitle, Attendance $attendance): string
    {
        $extension = strtolower(pathinfo((string) $attendance->photo_original_name, PATHINFO_EXTENSION) ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        $base = $this->drive->sanitizeDriveName(
            trim((string) $scholar->scholar_id).' - '.trim((string) $scholar->full_name).' - '.$eventTitle
        );

        return $base.'.'.$extension;
    }
}

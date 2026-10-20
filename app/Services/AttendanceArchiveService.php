<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttendanceArchiveService
{
    public function __construct(
        private LocalArchive $archive,
        private AcademicSettingsService $academic,
    ) {}

    public function storePhoto(Attendance $attendance, UploadedFile $file): string
    {
        $attendance->loadMissing(['user', 'event']);
        $directory = $this->directoryFor($attendance);

        return $this->archive->storeUpload($file, $directory);
    }

    public function storeApprovedPhoto(Attendance $attendance): void
    {
        $attendance->loadMissing(['user', 'event']);
        $scholar = $attendance->user;
        $event = $attendance->event;

        if ($attendance->status !== Attendance::STATUS_APPROVED || ! $scholar?->isScholar() || ! $event || ! $attendance->hasPhoto()) {
            return;
        }

        $current = (string) $attendance->photo_path;
        if ($current !== '' && str_starts_with($current, 'bssa/attendance/')) {
            return;
        }

        if ($current === '' || ! Storage::disk('public')->exists($current)) {
            Log::warning('Approved attendance has no local photo to archive.', [
                'attendance_id' => $attendance->id,
            ]);

            return;
        }

        [$start, $end] = $this->years($attendance);
        $copied = $this->archive->copyInto(
            $current,
            $this->archive->attendanceDirectory($scholar, $event, $start, $end),
            (string) ($attendance->photo_original_name ?: basename($current))
        );

        if ($copied === null) {
            Log::warning('Approved attendance photo could not be copied into local storage.', [
                'attendance_id' => $attendance->id,
            ]);

            return;
        }

        $attendance->update(['photo_path' => $copied]);
    }

    public function removeStoredPhoto(Attendance $attendance): void
    {
        // The participation photo stays on local storage so the record can still be reviewed.
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function years(Attendance $attendance): array
    {
        $start = (int) ($attendance->academic_year_start ?? 0);
        $end = (int) ($attendance->academic_year_end ?? 0);

        if ($start >= 2000 && $end === $start + 1) {
            return [$start, $end];
        }

        $current = $this->academic->current();

        return [(int) $current->year_start, (int) $current->year_end];
    }

    private function directoryFor(Attendance $attendance): string
    {
        $scholar = $attendance->user;
        $event = $attendance->event;

        if (! $scholar instanceof User || ! $event) {
            return 'attendance_photos/'.$attendance->user_id;
        }

        [$start, $end] = $this->years($attendance);

        return $this->archive->attendanceDirectory($scholar, $event, $start, $end);
    }
}

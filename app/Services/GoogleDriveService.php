<?php

namespace App\Services;

use App\Models\GoogleDriveConnection;
use App\Models\GoogleDriveFolder;
use App\Models\User;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GoogleDriveService
{
    public const ROOT_FOLDER_NAME = 'BSSA Scholar Documents';

    public const ROOT_FOLDER_KEY = 'root';

    public const ATTENDANCE_ROOT_FOLDER_NAME = 'BSSA Attendance';

    public const ATTENDANCE_ROOT_FOLDER_KEY = 'attendance_root';

    public function __construct(
        private GoogleApiClientFactory $googleClients,
        private AcademicSettingsService $academic,
    ) {}

    public function isConnected(): bool
    {
        try {
            $this->token();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function storeToken(array $token, ?int $connectedBy = null): void
    {
        $connection = GoogleDriveConnection::query()->first() ?? new GoogleDriveConnection;
        $connection->token = $token;
        $connection->connected_by = $connectedBy;
        $connection->save();

        session(['google_drive_token' => $token]);
    }

    public function academicYearLabel(?User $user = null): string
    {
        $setting = $user ? $this->academic->forUser($user) : $this->academic->current();

        return $setting->year_start.'-'.$setting->year_end;
    }

    public function studentFolderName(string $scholarCode, string $fullName): string
    {
        return trim($scholarCode).' - '.trim($fullName);
    }

    public function getOrCreateRootFolder(): string
    {
        $folderId = $this->rememberedFolder(self::ROOT_FOLDER_KEY, self::ROOT_FOLDER_NAME, null);

        $this->ensureAttendanceFoldersOnce();

        return $folderId;
    }

    public function getOrCreateAcademicYearFolder(string $academicYear): string
    {
        $year = trim($academicYear);

        if ($year === '') {
            throw new RuntimeException('Academic year is required for the Google Drive folder.');
        }

        return $this->rememberedFolder('year:'.$year, $year, $this->getOrCreateRootFolder());
    }

    public function getOrCreateStudentFolder(string $academicYear, string $scholarCode, string $fullName, ?User $scholar = null): string
    {
        $code = trim($scholarCode);
        $name = trim($fullName);

        if ($code === '') {
            throw new RuntimeException('Scholar Code is required for the Google Drive folder.');
        }

        $expected = $this->studentFolderName($code, $name);
        $yearFolderId = $this->getOrCreateAcademicYearFolder($academicYear);

        if ($scholar?->google_drive_folder_id) {
            $existing = $this->folderById($scholar->google_drive_folder_id);
            if ($existing) {
                if ($existing->getName() !== $expected) {
                    $this->renameFolder($existing->getId(), $expected);
                }

                return $existing->getId();
            }
        }

        $match = $this->findStudentFolder($yearFolderId, $code, $expected);

        if ($match) {
            if ($match->getName() !== $expected) {
                $this->renameFolder($match->getId(), $expected);
            }

            $this->rememberStudentFolder($scholar, $match->getId());

            return $match->getId();
        }

        $folderId = $this->createFolder($expected, $yearFolderId);
        $this->rememberStudentFolder($scholar, $folderId);

        return $folderId;
    }

    public function ensureScholarFolder(User $scholar): string
    {
        return $this->getOrCreateStudentFolder(
            $this->academicYearLabel($scholar),
            (string) $scholar->scholar_id,
            (string) $scholar->full_name,
            $scholar
        );
    }

    public function getOrCreateAttendanceRootFolder(): string
    {
        return $this->rememberedFolder(
            self::ATTENDANCE_ROOT_FOLDER_KEY,
            self::ATTENDANCE_ROOT_FOLDER_NAME,
            null
        );
    }

    public function getOrCreateAttendanceYearFolder(string $academicYear): string
    {
        $year = $this->sanitizeDriveName($academicYear);

        if ($year === '') {
            throw new RuntimeException('Academic year is required for the BSSA Attendance folder.');
        }

        return $this->rememberedFolder(
            'attendance_year:'.$year,
            $year,
            $this->getOrCreateAttendanceRootFolder()
        );
    }

    public function getOrCreateAttendanceScholarFolder(string $academicYear, string $scholarCode, string $fullName): string
    {
        $code = trim($scholarCode);
        $name = trim($fullName);

        if ($code === '') {
            throw new RuntimeException('Scholar Code is required for the BSSA Attendance folder.');
        }

        $expected = $this->sanitizeDriveName($this->studentFolderName($code, $name));
        $yearFolderId = $this->getOrCreateAttendanceYearFolder($academicYear);
        $key = 'attendance_scholar:'.$this->sanitizeDriveName($academicYear).':'.$code;

        return $this->rememberedNamedFolder($key, $expected, $yearFolderId);
    }

    public function getOrCreateAttendanceEventFolder(
        string $academicYear,
        string $scholarCode,
        string $fullName,
        int $eventId,
        string $eventTitle,
    ): string {
        $title = $this->sanitizeDriveName($eventTitle) ?: 'Event';
        $scholarFolderId = $this->getOrCreateAttendanceScholarFolder($academicYear, $scholarCode, $fullName);
        $yearKey = $this->sanitizeDriveName($academicYear);
        $key = 'attendance_event:'.$yearKey.':'.trim($scholarCode).':'.$eventId;

        return $this->rememberedNamedFolder(
            $key,
            $title,
            $scholarFolderId,
            'attendance_event:'.$yearKey.':'.trim($scholarCode).':'
        );
    }

    public function sanitizeDriveName(string $name): string
    {
        $clean = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $name) ?? $name;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

        return mb_substr($clean, 0, 180);
    }

    public function findChildFile(string $name, string $parentId): ?DriveFile
    {
        $query = sprintf(
            "mimeType != 'application/vnd.google-apps.folder' and name = '%s' and trashed = false and '%s' in parents",
            $this->escapeQuery($name),
            $this->escapeQuery($parentId)
        );

        try {
            $result = $this->drive()->files->listFiles([
                'q' => $query,
                'pageSize' => 10,
                'fields' => 'files(id,name,webViewLink)',
                'spaces' => 'drive',
            ]);
        } catch (Throwable $e) {
            Log::warning('Google Drive file search failed.', ['message' => $e->getMessage()]);

            return null;
        }

        $files = $result->getFiles() ?? [];

        return $files[0] ?? null;
    }

    public function uploadRaw(string $contents, string $parentFolderId, string $name, string $mime): DriveFile
    {
        $drive = $this->drive();

        $driveFile = new DriveFile([
            'name' => $name,
            'parents' => [$parentFolderId],
        ]);

        try {
            return $drive->files->create($driveFile, [
                'data' => $contents,
                'mimeType' => $mime ?: 'application/octet-stream',
                'uploadType' => 'multipart',
                'fields' => 'id,name,mimeType,size,webViewLink',
            ]);
        } catch (Throwable $e) {
            Log::error('Google Drive upload failed.', ['message' => $e->getMessage()]);
            throw new RuntimeException('Google Drive upload failed.');
        }
    }

    public function deleteFileIfPresent(?string $fileId): void
    {
        if (! filled($fileId)) {
            return;
        }

        try {
            $this->deleteFile($fileId);
        } catch (Throwable $e) {
            Log::warning('Google Drive attendance file delete failed.', [
                'file_id' => $fileId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function syncStudentFolderName(User $scholar): void
    {
        if (! $scholar->isScholar() || ! $this->isConnected()) {
            return;
        }

        if (! $scholar->google_drive_folder_id && ! filled($scholar->scholar_id)) {
            return;
        }

        $this->ensureScholarFolder($scholar->fresh());
    }

    public function uploadFile(UploadedFile $file, string $parentFolderId, ?string $name = null): DriveFile
    {
        $drive = $this->drive();
        $displayName = $name ?: $file->getClientOriginalName();

        $driveFile = new DriveFile([
            'name' => $displayName,
            'parents' => [$parentFolderId],
        ]);

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        try {
            return $drive->files->create($driveFile, [
                'data' => $contents,
                'mimeType' => $file->getMimeType() ?: 'application/octet-stream',
                'uploadType' => 'multipart',
                'fields' => 'id,name,mimeType,size,webViewLink',
            ]);
        } catch (Throwable $e) {
            Log::error('Google Drive upload failed.', ['message' => $e->getMessage()]);
            throw new RuntimeException('Google Drive upload failed.');
        }
    }

    public function deleteFile(string $fileId): void
    {
        try {
            $this->drive()->files->delete($fileId);
        } catch (Throwable $e) {
            Log::warning('Google Drive delete failed.', [
                'file_id' => $fileId,
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Google Drive delete failed.');
        }
    }

    public function getFile(string $fileId): DriveFile
    {
        try {
            return $this->drive()->files->get($fileId, [
                'fields' => 'id,name,mimeType,size,webViewLink',
            ]);
        } catch (Throwable $e) {
            Log::warning('Google Drive file lookup failed.', [
                'file_id' => $fileId,
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Google Drive file was not found.');
        }
    }

    /**
     * @return array{contents: string, mime: string, name: string}
     */
    public function downloadFile(string $fileId): array
    {
        $meta = $this->getFile($fileId);

        try {
            $response = $this->drive()->files->get($fileId, ['alt' => 'media']);
            $contents = $response->getBody()->getContents();
        } catch (Throwable $e) {
            Log::warning('Google Drive download failed.', [
                'file_id' => $fileId,
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Google Drive download failed.');
        }

        return [
            'contents' => $contents,
            'mime' => $meta->getMimeType() ?: 'application/octet-stream',
            'name' => $meta->getName() ?: 'document',
        ];
    }

    private function ensureAttendanceFoldersOnce(): void
    {
        if (GoogleDriveFolder::query()->where('folder_key', self::ATTENDANCE_ROOT_FOLDER_KEY)->exists()) {
            return;
        }

        try {
            app(AttendanceDriveStorageService::class)->syncConfiguredYearFolders();
        } catch (Throwable $e) {
            Log::warning('Could not create BSSA Attendance folders beside Scholar Documents.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function rememberedFolder(string $key, string $name, ?string $parentId): string
    {
        $stored = GoogleDriveFolder::query()->where('folder_key', $key)->first();

        if ($stored && $this->folderById($stored->folder_id)) {
            return $stored->folder_id;
        }

        $found = $this->findFolderByName($name, $parentId);
        $folderId = $found?->getId() ?: $this->createFolder($name, $parentId);

        GoogleDriveFolder::query()->updateOrCreate(
            ['folder_key' => $key],
            ['folder_id' => $folderId, 'name' => $name]
        );

        return $folderId;
    }

    private function rememberedNamedFolder(string $key, string $name, string $parentId, ?string $siblingKeyPrefix = null): string
    {
        $stored = GoogleDriveFolder::query()->where('folder_key', $key)->first();

        if ($stored && $this->folderById($stored->folder_id)) {
            if ($stored->name !== $name) {
                $this->renameFolder($stored->folder_id, $name);
                $stored->update(['name' => $name]);
            }

            return $stored->folder_id;
        }

        $found = $this->findFolderByName($name, $parentId);
        if ($found && $siblingKeyPrefix) {
            $claimedBySibling = GoogleDriveFolder::query()
                ->where('folder_key', 'like', $siblingKeyPrefix.'%')
                ->where('folder_key', '!=', $key)
                ->where('folder_id', $found->getId())
                ->exists();

            if ($claimedBySibling) {
                $found = null;
            }
        }

        $folderId = $found?->getId() ?: $this->createFolder($name, $parentId);

        GoogleDriveFolder::query()->updateOrCreate(
            ['folder_key' => $key],
            ['folder_id' => $folderId, 'name' => $name]
        );

        return $folderId;
    }

    private function findStudentFolder(string $yearFolderId, string $scholarCode, string $expectedName): ?DriveFile
    {
        $exact = $this->findFolderByName($expectedName, $yearFolderId);
        if ($exact) {
            return $exact;
        }

        $prefix = $scholarCode.' - ';

        foreach ($this->childFolders($yearFolderId) as $folder) {
            $name = (string) $folder->getName();
            if ($name === $scholarCode || str_starts_with($name, $prefix)) {
                return $folder;
            }
        }

        return null;
    }

    private function findFolderByName(string $name, ?string $parentId): ?DriveFile
    {
        $query = sprintf(
            "mimeType = 'application/vnd.google-apps.folder' and name = '%s' and trashed = false",
            $this->escapeQuery($name)
        );

        if ($parentId) {
            $query .= sprintf(" and '%s' in parents", $this->escapeQuery($parentId));
        }

        try {
            $result = $this->drive()->files->listFiles([
                'q' => $query,
                'pageSize' => 10,
                'fields' => 'files(id,name)',
                'spaces' => 'drive',
            ]);
        } catch (Throwable $e) {
            Log::warning('Google Drive folder search failed.', ['message' => $e->getMessage()]);
            throw new RuntimeException('Google Drive folder search failed.');
        }

        $files = $result->getFiles() ?? [];

        return $files[0] ?? null;
    }

    /**
     * @return list<DriveFile>
     */
    private function childFolders(string $parentId): array
    {
        $query = sprintf(
            "mimeType = 'application/vnd.google-apps.folder' and '%s' in parents and trashed = false",
            $this->escapeQuery($parentId)
        );

        try {
            $result = $this->drive()->files->listFiles([
                'q' => $query,
                'pageSize' => 100,
                'fields' => 'files(id,name)',
                'spaces' => 'drive',
            ]);
        } catch (Throwable $e) {
            Log::warning('Google Drive folder list failed.', ['message' => $e->getMessage()]);

            return [];
        }

        return $result->getFiles() ?? [];
    }

    private function folderById(string $folderId): ?DriveFile
    {
        try {
            return $this->drive()->files->get($folderId, [
                'fields' => 'id,name,trashed,mimeType',
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function createFolder(string $name, ?string $parentId): string
    {
        $meta = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($parentId) {
            $meta->setParents([$parentId]);
        }

        try {
            $created = $this->drive()->files->create($meta, [
                'fields' => 'id,name',
            ]);
        } catch (Throwable $e) {
            Log::error('Google Drive folder create failed.', [
                'name' => $name,
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Google Drive folder could not be created.');
        }

        return (string) $created->getId();
    }

    private function renameFolder(string $folderId, string $name): void
    {
        try {
            $this->drive()->files->update($folderId, new DriveFile(['name' => $name]), [
                'fields' => 'id,name',
            ]);
        } catch (Throwable $e) {
            Log::warning('Google Drive folder rename failed.', [
                'folder_id' => $folderId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function rememberStudentFolder(?User $scholar, string $folderId): void
    {
        if (! $scholar) {
            return;
        }

        $scholar->forceFill(['google_drive_folder_id' => $folderId])->save();
    }

    private function drive(): Drive
    {
        return new Drive($this->client());
    }

    private function client(): Client
    {
        $client = $this->googleClients->make();
        $client->setAccessToken($this->token());

        if ($client->isAccessTokenExpired()) {
            $refresh = $client->getRefreshToken();
            if (! $refresh) {
                throw new RuntimeException('Google Drive access expired. Reconnect Google Drive.');
            }

            try {
                $refreshed = $client->fetchAccessTokenWithRefreshToken($refresh);
            } catch (Throwable $e) {
                Log::warning('Google Drive token refresh failed.', ['message' => $e->getMessage()]);
                throw new RuntimeException('Google Drive access expired. Reconnect Google Drive.');
            }

            if (isset($refreshed['error'])) {
                throw new RuntimeException('Google Drive access expired. Reconnect Google Drive.');
            }

            $this->storeToken($client->getAccessToken(), GoogleDriveConnection::query()->value('connected_by'));
        }

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function token(): array
    {
        $sessionToken = session('google_drive_token');
        if (is_array($sessionToken) && $sessionToken !== []) {
            return $sessionToken;
        }

        $stored = GoogleDriveConnection::query()->first()?->token;
        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        throw new RuntimeException('Google Drive is not connected.');
    }

    private function escapeQuery(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}

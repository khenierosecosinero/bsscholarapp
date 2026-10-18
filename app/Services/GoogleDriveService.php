<?php

namespace App\Services;

use App\Models\GoogleDriveConnection;
use App\Models\GoogleDriveFolder;
use App\Models\ScholarshipProgram;
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
    public const ORG_ROOT_FOLDER_NAME = 'BSSA';

    public const ORG_ROOT_FOLDER_KEY = 'bssa_root';

    public const DOCUMENTS_ROOT_FOLDER_NAME = 'DOCUMENTS';

    public const DOCUMENTS_ROOT_FOLDER_KEY = 'documents_root';

    /** @deprecated Use DOCUMENTS_ROOT_FOLDER_KEY; kept for older cached rows. */
    public const ROOT_FOLDER_KEY = self::DOCUMENTS_ROOT_FOLDER_KEY;

    /** @deprecated Use DOCUMENTS_ROOT_FOLDER_NAME. */
    public const ROOT_FOLDER_NAME = self::DOCUMENTS_ROOT_FOLDER_NAME;

    public const ATTENDANCE_ROOT_FOLDER_NAME = 'ATTENDANCE';

    public const ATTENDANCE_ROOT_FOLDER_KEY = 'attendance_root';

    public function __construct(
        private GoogleApiClientFactory $googleClients,
        private AcademicSettingsService $academic,
    ) {}

    public function isConnected(bool $verifyRemote = false): bool
    {
        try {
            $this->token();
        } catch (Throwable) {
            return false;
        }

        if (! $verifyRemote) {
            return true;
        }

        try {
            $this->client();

            return true;
        } catch (Throwable $e) {
            Log::warning('Google Drive token is present but remote verification failed.', [
                'message' => $e->getMessage(),
            ]);

            // Keep the stored connection when verification fails transiently.
            // Only token()/client() refresh failures with invalid_grant clear it.
            return $this->hasStoredUsableToken();
        }
    }

    public function clearConnection(): void
    {
        GoogleDriveConnection::query()->delete();
        session()->forget('google_drive_token');
    }

    public function storeToken(array $token, ?int $connectedBy = null): void
    {
        $token = $this->mergePreservedRefreshToken($token);

        $connection = GoogleDriveConnection::query()->first() ?? new GoogleDriveConnection;
        $connection->token = $token;
        if ($connectedBy !== null) {
            $connection->connected_by = $connectedBy;
        } elseif (! $connection->exists) {
            $connection->connected_by = null;
        }
        $connection->save();

        session(['google_drive_token' => $token]);
    }

    public function academicYearLabel(?User $user = null): string
    {
        $setting = $user ? $this->academic->forUser($user) : $this->academic->current();

        return $this->driveYearFolderName((int) $setting->year_start, (int) $setting->year_end);
    }

    public function driveYearFolderName(int $yearStart, ?int $yearEnd = null): string
    {
        $end = $yearEnd && $yearEnd === $yearStart + 1 ? $yearEnd : $yearStart + 1;

        return 'AY '.$yearStart.'–'.$end;
    }

    public function normalizeYearFolderLabel(string $academicYear): string
    {
        $clean = $this->sanitizeDriveName($academicYear);
        if ($clean === '') {
            return '';
        }

        if (preg_match('/(\d{4})\s*[–-]\s*(\d{4})/u', $clean, $matches) === 1) {
            return $this->driveYearFolderName((int) $matches[1], (int) $matches[2]);
        }

        return str_starts_with($clean, 'AY ') ? $clean : 'AY '.$clean;
    }

    public function studentFolderName(string $scholarCode, string $fullName): string
    {
        return trim($scholarCode).' – '.trim($fullName);
    }

    /**
     * @return array{province: string, municipality: string}
     */
    public function locationForScholar(User $scholar): array
    {
        $province = trim((string) ($scholar->provinceName() ?: $scholar->scholarshipClubProvince() ?: ''));
        $municipality = trim((string) ($scholar->municipalityName() ?: $scholar->scholarshipClubCity() ?: ''));

        foreach (ScholarshipProgram::PICKER_PROVINCES as $known) {
            if (strcasecmp($province, $known) === 0) {
                $province = $known;
                break;
            }
        }

        if ($province === '') {
            $province = 'Unassigned Province';
        }

        if ($municipality === '') {
            $municipality = 'Unassigned Municipality';
        }

        return [
            'province' => $this->sanitizeDriveName($province) ?: 'Unassigned Province',
            'municipality' => $this->sanitizeDriveName($municipality) ?: 'Unassigned Municipality',
        ];
    }

    public function getOrCreateOrgRootFolder(): string
    {
        return $this->rememberedFolder(self::ORG_ROOT_FOLDER_KEY, self::ORG_ROOT_FOLDER_NAME, null);
    }

    public function getOrCreateDocumentsRootFolder(): string
    {
        return $this->rememberedFolder(
            self::DOCUMENTS_ROOT_FOLDER_KEY,
            self::DOCUMENTS_ROOT_FOLDER_NAME,
            $this->getOrCreateOrgRootFolder()
        );
    }

    /** @deprecated Prefer getOrCreateDocumentsRootFolder(). */
    public function getOrCreateRootFolder(): string
    {
        $folderId = $this->getOrCreateDocumentsRootFolder();
        $this->ensureOrganizationFoldersOnce();

        return $folderId;
    }

    public function getOrCreateAttendanceRootFolder(): string
    {
        return $this->rememberedFolder(
            self::ATTENDANCE_ROOT_FOLDER_KEY,
            self::ATTENDANCE_ROOT_FOLDER_NAME,
            $this->getOrCreateOrgRootFolder()
        );
    }

    public function ensureOrganizationFolders(): void
    {
        $this->getOrCreateOrgRootFolder();
        $this->getOrCreateDocumentsRootFolder();
        $this->getOrCreateAttendanceRootFolder();

        foreach ($this->academic->yearOptions() as $yearStart => $label) {
            $yearLabel = $this->driveYearFolderName((int) $yearStart);
            $this->getOrCreateDocumentsYearFolder($yearLabel);
            $this->getOrCreateAttendanceYearFolder($yearLabel);

            foreach (ScholarshipProgram::PICKER_PROVINCES as $province) {
                $this->getOrCreateDocumentsProvinceFolder($yearLabel, $province);
                $this->getOrCreateAttendanceProvinceFolder($yearLabel, $province);
            }
        }
    }

    public function getOrCreateDocumentsYearFolder(string $academicYear): string
    {
        $year = $this->normalizeYearFolderLabel($academicYear);
        if ($year === '') {
            throw new RuntimeException('Academic year is required for the DOCUMENTS folder.');
        }

        return $this->rememberedFolder(
            'documents_year:'.$year,
            $year,
            $this->getOrCreateDocumentsRootFolder()
        );
    }

    public function getOrCreateDocumentsProvinceFolder(string $academicYear, string $province): string
    {
        $year = $this->normalizeYearFolderLabel($academicYear);
        $name = $this->sanitizeDriveName($province) ?: 'Unassigned Province';

        return $this->rememberedFolder(
            'documents_province:'.$year.':'.$name,
            $name,
            $this->getOrCreateDocumentsYearFolder($year)
        );
    }

    public function getOrCreateDocumentsCityFolder(string $academicYear, string $province, string $municipality): string
    {
        $year = $this->normalizeYearFolderLabel($academicYear);
        $provinceName = $this->sanitizeDriveName($province) ?: 'Unassigned Province';
        $cityName = $this->sanitizeDriveName($municipality) ?: 'Unassigned Municipality';

        return $this->rememberedFolder(
            'documents_city:'.$year.':'.$provinceName.':'.$cityName,
            $cityName,
            $this->getOrCreateDocumentsProvinceFolder($year, $provinceName)
        );
    }

    public function getOrCreateStudentFolder(string $academicYear, string $scholarCode, string $fullName, ?User $scholar = null): string
    {
        if (! $scholar) {
            throw new RuntimeException('A scholar account is required for the Google Drive folder.');
        }

        return $this->ensureScholarFolder($scholar, $academicYear);
    }

    public function ensureScholarFolder(User $scholar, ?string $academicYear = null): string
    {
        $code = trim((string) $scholar->scholar_id);
        $name = trim((string) $scholar->full_name);

        if ($code === '') {
            throw new RuntimeException('Scholar Code is required for the Google Drive folder.');
        }

        $year = $this->normalizeYearFolderLabel($academicYear ?: $this->academicYearLabel($scholar));
        $location = $this->locationForScholar($scholar);
        $expected = $this->sanitizeDriveName($this->studentFolderName($code, $name));
        $cityFolderId = $this->getOrCreateDocumentsCityFolder(
            $year,
            $location['province'],
            $location['municipality']
        );
        $key = 'documents_scholar:'.$year.':'.$location['province'].':'.$location['municipality'].':'.$code;

        $folderId = $this->rememberedNamedFolder($key, $expected, $cityFolderId);
        $this->rememberStudentFolder($scholar, $folderId);

        return $folderId;
    }

    public function getOrCreateAttendanceYearFolder(string $academicYear): string
    {
        $year = $this->normalizeYearFolderLabel($academicYear);
        if ($year === '') {
            throw new RuntimeException('Academic year is required for the ATTENDANCE folder.');
        }

        return $this->rememberedFolder(
            'attendance_year:'.$year,
            $year,
            $this->getOrCreateAttendanceRootFolder()
        );
    }

    public function getOrCreateAttendanceProvinceFolder(string $academicYear, string $province): string
    {
        $year = $this->normalizeYearFolderLabel($academicYear);
        $name = $this->sanitizeDriveName($province) ?: 'Unassigned Province';

        return $this->rememberedFolder(
            'attendance_province:'.$year.':'.$name,
            $name,
            $this->getOrCreateAttendanceYearFolder($year)
        );
    }

    public function getOrCreateAttendanceCityFolder(string $academicYear, string $province, string $municipality): string
    {
        $year = $this->normalizeYearFolderLabel($academicYear);
        $provinceName = $this->sanitizeDriveName($province) ?: 'Unassigned Province';
        $cityName = $this->sanitizeDriveName($municipality) ?: 'Unassigned Municipality';

        return $this->rememberedFolder(
            'attendance_city:'.$year.':'.$provinceName.':'.$cityName,
            $cityName,
            $this->getOrCreateAttendanceProvinceFolder($year, $provinceName)
        );
    }

    public function getOrCreateAttendanceScholarFolder(
        string $academicYear,
        string $scholarCode,
        string $fullName,
        ?User $scholar = null,
    ): string {
        $code = trim($scholarCode);
        $name = trim($fullName);

        if ($code === '') {
            throw new RuntimeException('Scholar Code is required for the ATTENDANCE folder.');
        }

        if (! $scholar) {
            throw new RuntimeException('A scholar account is required for the ATTENDANCE folder.');
        }

        $year = $this->normalizeYearFolderLabel($academicYear);
        $location = $this->locationForScholar($scholar);
        $expected = $this->sanitizeDriveName($this->studentFolderName($code, $name));
        $cityFolderId = $this->getOrCreateAttendanceCityFolder(
            $year,
            $location['province'],
            $location['municipality']
        );
        $key = 'attendance_scholar:'.$year.':'.$location['province'].':'.$location['municipality'].':'.$code;

        return $this->rememberedNamedFolder($key, $expected, $cityFolderId);
    }

    public function getOrCreateAttendanceEventFolder(
        string $academicYear,
        string $scholarCode,
        string $fullName,
        int $eventId,
        string $eventTitle,
        ?User $scholar = null,
    ): string {
        if (! $scholar) {
            throw new RuntimeException('A scholar account is required for the ATTENDANCE event folder.');
        }

        $year = $this->normalizeYearFolderLabel($academicYear);
        $location = $this->locationForScholar($scholar);
        $title = $this->sanitizeDriveName($eventTitle) ?: 'Event';
        $scholarFolderId = $this->getOrCreateAttendanceScholarFolder(
            $year,
            $scholarCode,
            $fullName,
            $scholar
        );
        $key = 'attendance_event:'.$year.':'.$location['province'].':'.$location['municipality'].':'
            .trim($scholarCode).':'.$eventId;

        return $this->rememberedNamedFolder(
            $key,
            $title,
            $scholarFolderId,
            'attendance_event:'.$year.':'.$location['province'].':'.$location['municipality'].':'
                .trim($scholarCode).':'
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

    public function upload(UploadedFile $file): DriveFile
    {
        $folderId = $this->getOrCreateDocumentsRootFolder();

        return $this->uploadFile($file, $folderId);
    }

    public function uploadFile(UploadedFile $file, string $parentFolderId, ?string $name = null): DriveFile
    {
        $drive = $this->drive();
        $displayName = $name ?: $this->originalUploadName($file);

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

    public function originalUploadName(UploadedFile $file): string
    {
        $original = trim((string) $file->getClientOriginalName());
        $sanitized = $this->sanitizeDriveName($original);

        if ($sanitized !== '') {
            return $sanitized;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        return 'upload.'.$extension;
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

    private function ensureOrganizationFoldersOnce(): void
    {
        if (GoogleDriveFolder::query()->where('folder_key', self::ATTENDANCE_ROOT_FOLDER_KEY)->exists()
            && GoogleDriveFolder::query()->where('folder_key', self::DOCUMENTS_ROOT_FOLDER_KEY)->exists()) {
            return;
        }

        try {
            $this->ensureOrganizationFolders();
        } catch (Throwable $e) {
            Log::warning('Could not create BSSA organization folders.', [
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

    private function folderById(string $folderId): ?DriveFile
    {
        try {
            $folder = $this->drive()->files->get($folderId, [
                'fields' => 'id,name,trashed,mimeType',
            ]);

            if ($folder->getTrashed()) {
                return null;
            }

            return $folder;
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
        if (! $scholar || ! $scholar->exists) {
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
        $token = $this->token();
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refresh = $client->getRefreshToken() ?: ($token['refresh_token'] ?? null);
            if (! filled($refresh)) {
                $this->clearConnection();
                throw new RuntimeException('Google Drive access expired. Reconnect Google Drive.');
            }

            try {
                $refreshed = $client->fetchAccessTokenWithRefreshToken($refresh);
            } catch (Throwable $e) {
                Log::warning('Google Drive token refresh failed.', ['message' => $e->getMessage()]);
                // Network/API blips must not wipe a still-valid refresh token.
                throw new RuntimeException('Google Drive is temporarily unavailable. Try again in a moment.');
            }

            if (isset($refreshed['error'])) {
                $error = (string) $refreshed['error'];
                Log::warning('Google Drive token refresh returned an error.', [
                    'error' => $error,
                ]);

                if (in_array($error, ['invalid_grant', 'invalid_token', 'unauthorized_client'], true)) {
                    $this->clearConnection();
                    throw new RuntimeException('Google Drive access expired. Reconnect Google Drive.');
                }

                throw new RuntimeException('Google Drive is temporarily unavailable. Try again in a moment.');
            }

            $updated = $client->getAccessToken();
            if (! is_array($updated)) {
                $updated = is_array($refreshed) ? $refreshed : [];
            }
            if (empty($updated['refresh_token'])) {
                $updated['refresh_token'] = $refresh;
            }

            $this->storeToken(
                $updated,
                GoogleDriveConnection::query()->value('connected_by')
            );
        }

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function token(): array
    {
        // Org token in the database is the source of truth for all roles.
        $stored = GoogleDriveConnection::query()->first()?->token;
        if ($this->isUsableToken($stored)) {
            session(['google_drive_token' => $stored]);

            return $stored;
        }

        $sessionToken = session('google_drive_token');
        if ($this->isUsableToken($sessionToken)) {
            // Recover a session-only token into durable storage.
            $this->storeToken($sessionToken, auth()->id());

            return $sessionToken;
        }

        throw new RuntimeException('Google Drive is not connected.');
    }

    private function isUsableToken(mixed $token): bool
    {
        return is_array($token)
            && $token !== []
            && (
                filled($token['access_token'] ?? null)
                || filled($token['refresh_token'] ?? null)
            );
    }

    private function hasStoredUsableToken(): bool
    {
        return $this->isUsableToken(GoogleDriveConnection::query()->first()?->token)
            || $this->isUsableToken(session('google_drive_token'));
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    private function mergePreservedRefreshToken(array $token): array
    {
        if (filled($token['refresh_token'] ?? null)) {
            return $token;
        }

        $existing = GoogleDriveConnection::query()->first()?->token;
        if (is_array($existing) && filled($existing['refresh_token'] ?? null)) {
            $token['refresh_token'] = $existing['refresh_token'];
        }

        $sessionToken = session('google_drive_token');
        if (! filled($token['refresh_token'] ?? null)
            && is_array($sessionToken)
            && filled($sessionToken['refresh_token'] ?? null)
        ) {
            $token['refresh_token'] = $sessionToken['refresh_token'];
        }

        return $token;
    }

    private function escapeQuery(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}

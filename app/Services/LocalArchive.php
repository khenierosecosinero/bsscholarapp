<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalArchive
{
    public function yearFolder(int $yearStart, ?int $yearEnd = null): string
    {
        $end = $yearEnd && $yearEnd === $yearStart + 1 ? $yearEnd : $yearStart + 1;

        return 'AY '.$yearStart.'–'.$end;
    }

    /**
     * @return array{province: string, municipality: string}
     */
    public function locationFor(User $scholar): array
    {
        $province = trim((string) ($scholar->provinceName() ?: $scholar->scholarshipClubProvince() ?: ''));
        $municipality = trim((string) ($scholar->municipalityName() ?: $scholar->scholarshipClubCity() ?: ''));

        foreach (ScholarshipProgram::PICKER_PROVINCES as $known) {
            if (strcasecmp($province, $known) === 0) {
                $province = $known;
                break;
            }
        }

        return [
            'province' => $this->segment($province, 'Unassigned Province'),
            'municipality' => $this->segment($municipality, 'Unassigned Municipality'),
        ];
    }

    public function scholarFolder(User $scholar): string
    {
        $code = trim((string) $scholar->scholar_id);
        $name = trim((string) $scholar->full_name);

        return $this->segment($code.' – '.$name, 'Scholar');
    }

    public function documentDirectory(User $scholar, int $yearStart, int $yearEnd): string
    {
        $location = $this->locationFor($scholar);

        return $this->join([
            'bssa',
            'documents',
            $this->yearFolder($yearStart, $yearEnd),
            $location['province'],
            $location['municipality'],
            $this->scholarFolder($scholar),
        ]);
    }

    public function attendanceDirectory(User $scholar, Event $event, int $yearStart, int $yearEnd): string
    {
        $location = $this->locationFor($scholar);

        return $this->join([
            'bssa',
            'attendance',
            $this->yearFolder($yearStart, $yearEnd),
            $location['province'],
            $location['municipality'],
            $this->scholarFolder($scholar),
            $this->segment((string) $event->title, 'Event'),
        ]);
    }

    public function eventImageDirectory(ScholarshipProgram $program, string $title, int $yearStart, int $yearEnd): string
    {
        return $this->join([
            'bssa',
            'events',
            $this->yearFolder($yearStart, $yearEnd),
            $this->segment((string) ($program->province_name ?: ''), 'Unassigned Province'),
            $this->segment((string) ($program->location_name ?: ''), 'Unassigned Municipality'),
            $this->segment($title, 'Event'),
        ]);
    }

    public function storeUpload(UploadedFile $file, string $directory): string
    {
        return $file->storeAs($directory, $this->uniqueName($file->getClientOriginalName(), $file->extension()), 'public');
    }

    /**
     * Copy a file that is already on the public disk into the archive.
     * The original path is left in place.
     */
    public function copyInto(string $sourcePath, string $directory, string $originalName): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $target = $directory.'/'.$this->uniqueName($originalName, pathinfo($originalName, PATHINFO_EXTENSION) ?: pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (! $disk->copy($sourcePath, $target)) {
            return null;
        }

        return $target;
    }

    public function segment(string $name, string $fallback): string
    {
        $clean = preg_replace('/[\\\\\\/:*?"<>|]+/u', ' ', $name) ?? $name;
        $clean = str_replace('..', ' ', $clean);
        $clean = trim((string) (preg_replace('/\s+/u', ' ', $clean) ?? $clean));
        $clean = trim($clean, ". \t");
        $clean = mb_substr($clean, 0, 120);

        return $clean !== '' ? $clean : $fallback;
    }

    private function uniqueName(string $original, string $extension = ''): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $ext = strtolower($extension !== '' ? $extension : (pathinfo($original, PATHINFO_EXTENSION) ?: 'bin'));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $safeBase = Str::slug($this->segment($base, 'file'), '-');
        if ($safeBase === '') {
            $safeBase = 'file';
        }

        return $safeBase.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)).'.'.$ext;
    }

    /**
     * @param  list<string>  $parts
     */
    private function join(array $parts): string
    {
        return implode('/', $parts);
    }
}

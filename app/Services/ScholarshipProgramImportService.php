<?php

namespace App\Services;

use App\Models\ScholarshipProgram;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ScholarshipProgramImportService
{
    public function importFromPsgcFile(?string $path = null): int
    {
        $path ??= database_path('data/psgc-locations.json');

        if (! File::exists($path)) {
            throw new RuntimeException("PSGC location file not found at {$path}");
        }

        $payload = json_decode(File::get($path), true);

        if (! is_array($payload) || ! isset($payload['data']) || ! is_array($payload['data'])) {
            throw new RuntimeException('Invalid PSGC location file format.');
        }

        $records = [];
        $now = now();

        foreach ($payload['data'] as $item) {
            $level = (int) ($item['level'] ?? 0);

            if (! in_array($level, [2, 3], true)) {
                continue;
            }

            $name = trim((string) ($item['name']['en'] ?? $item['name']['local'] ?? ''));

            if ($name === '') {
                continue;
            }

            $psgcCode = (string) ($item['code']['id'] ?? $item['id'] ?? '');
            $slug = (string) ($item['name']['slug'] ?? ScholarshipProgram::slugForLocation($name.'-'.$psgcCode));
            $provinceName = $level === 3
                ? trim((string) ($item['parent']['name']['en'] ?? $item['parent']['name']['local'] ?? ''))
                : null;
            $regionName = $this->regionName($item);
            $locationType = $level === 2 ? 'province' : 'city_municipality';
            $displayName = $level === 2
                ? "{$name} (Province)"
                : ($provinceName !== '' && $provinceName !== null ? "{$name}, {$provinceName}" : $name);

            $records[] = [
                'psgc_code' => $psgcCode,
                'location_name' => $name,
                'location_type' => $locationType,
                'name' => "{$name} Scholarship Program",
                'display_name' => $displayName,
                'province_name' => $provinceName ?: null,
                'region_name' => $regionName,
                'slug' => $slug,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 250) as $chunk) {
            ScholarshipProgram::upsert(
                $chunk,
                ['psgc_code'],
                [
                    'location_name',
                    'location_type',
                    'name',
                    'display_name',
                    'province_name',
                    'region_name',
                    'slug',
                    'is_active',
                    'updated_at',
                ]
            );
        }

        return count($records);
    }

    private function regionName(array $item): ?string
    {
        foreach ($item['ancestors'] ?? [] as $ancestor) {
            if ((int) ($ancestor['level'] ?? 0) === 1) {
                $name = trim((string) ($ancestor['name']['en'] ?? $ancestor['name']['local'] ?? ''));

                return $name !== '' ? $name : null;
            }
        }

        return null;
    }
}

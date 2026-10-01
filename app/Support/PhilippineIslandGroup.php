<?php

namespace App\Support;

use App\Models\ScholarshipProgram;

final class PhilippineIslandGroup
{
    public const LUZON = 'luzon';

    public const VISAYAS = 'visayas';

    public const MINDANAO = 'mindanao';

    /** @var array<string, string> */
    public const LABELS = [
        self::LUZON => 'Luzon',
        self::VISAYAS => 'Visayas',
        self::MINDANAO => 'Mindanao',
    ];

    /** @var array<string, string> */
    private const PSGC_PREFIX = [
        '01' => self::LUZON,
        '02' => self::LUZON,
        '03' => self::LUZON,
        '04' => self::LUZON,
        '05' => self::LUZON,
        '06' => self::VISAYAS,
        '07' => self::VISAYAS,
        '08' => self::VISAYAS,
        '09' => self::MINDANAO,
        '10' => self::MINDANAO,
        '11' => self::MINDANAO,
        '12' => self::MINDANAO,
        '13' => self::LUZON,
        '14' => self::LUZON,
        '15' => self::MINDANAO,
        '16' => self::MINDANAO,
        '17' => self::LUZON,
        '18' => self::VISAYAS,
    ];

    public static function fromRegionName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'mindanao')
            || str_contains($normalized, 'zamboanga')
            || str_contains($normalized, 'davao')
            || str_contains($normalized, 'soccsksargen')
            || str_contains($normalized, 'caraga')
            || str_contains($normalized, 'bangsamoro')
            || str_contains($normalized, 'barmm')
            || (bool) preg_match('/\barmm\b/', $normalized)
        ) {
            return self::MINDANAO;
        }

        if (str_contains($normalized, 'visayas')
            || str_contains($normalized, 'negros island')
        ) {
            return self::VISAYAS;
        }

        if (str_contains($normalized, 'luzon')
            || str_contains($normalized, 'ilocos')
            || str_contains($normalized, 'cagayan valley')
            || str_contains($normalized, 'calabarzon')
            || str_contains($normalized, 'mimaropa')
            || str_contains($normalized, 'bicol')
            || str_contains($normalized, 'national capital')
            || (bool) preg_match('/\bncr\b/', $normalized)
            || str_contains($normalized, 'cordillera')
            || (bool) preg_match('/\bcar\b/', $normalized)
        ) {
            return self::LUZON;
        }

        return null;
    }

    public static function fromPsgcCode(?string $code): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $code) ?? '';
        if (strlen($digits) < 2) {
            return null;
        }

        return self::PSGC_PREFIX[substr($digits, 0, 2)] ?? null;
    }

    public static function fromProgram(ScholarshipProgram $program): ?string
    {
        return self::fromRegionName($program->region_name)
            ?? self::fromPsgcCode($program->psgc_code);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }
}

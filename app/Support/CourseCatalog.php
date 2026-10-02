<?php

namespace App\Support;

class CourseCatalog
{
    /**
     * Official full course names only. Initials such as BSICT are never listed.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            'Bachelor of Science in Information Technology',
            'Bachelor of Science in Information and Communications Technology',
            'Bachelor of Science in Information Systems',
            'Bachelor of Science in Computer Science',
            'Bachelor of Science in Computer Engineering',
            'Bachelor of Science in Electronics Engineering',
            'Bachelor of Science in Civil Engineering',
            'Bachelor of Science in Electrical Engineering',
            'Bachelor of Science in Mechanical Engineering',
            'Bachelor of Elementary Education',
            'Bachelor of Secondary Education',
            'Bachelor of Technical-Vocational Teacher Education',
            'Bachelor of Physical Education',
            'Bachelor of Science in Nursing',
            'Bachelor of Science in Midwifery',
            'Bachelor of Science in Business Administration',
            'Bachelor of Science in Accountancy',
            'Bachelor of Science in Accounting Information System',
            'Bachelor of Science in Management Accounting',
            'Bachelor of Science in Criminology',
            'Bachelor of Science in Agriculture',
            'Bachelor of Science in Agribusiness',
            'Bachelor of Science in Hospitality Management',
            'Bachelor of Science in Tourism Management',
            'Bachelor of Science in Marine Transportation',
            'Bachelor of Science in Marine Engineering',
            'Bachelor of Arts in Communication',
            'Bachelor of Science in Psychology',
            'Bachelor of Science in Biology',
            'Bachelor of Science in Mathematics',
            'Bachelor of Public Administration',
            'Bachelor of Science in Social Work',
            'Bachelor of Science in Environmental Science',
        ];
    }

    /**
     * Official year-level labels only.
     *
     * @return list<string>
     */
    public static function yearLevels(): array
    {
        return [
            '1st Year',
            '2nd Year',
            '3rd Year',
            '4th Year',
        ];
    }

    /**
     * @return list<string|\Closure>
     */
    public static function courseRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (static::looksLikeAbbreviation(is_string($value) ? $value : null)) {
                    $fail('Enter the complete official course name, not initials such as BSICT, BSCE, or BSIS. Example: Bachelor of Science in Information Technology.');
                }
            },
        ];
    }

    public static function normalize(?string $course): ?string
    {
        $course = trim(preg_replace('/\s+/', ' ', (string) $course) ?? '');

        return $course === '' ? null : $course;
    }

    /**
     * True when the value is initials or a short abbreviation such as BSICT, BSCE, or BSIS.
     */
    public static function looksLikeAbbreviation(?string $course): bool
    {
        $normalized = static::normalize($course);
        if ($normalized === null) {
            return false;
        }

        $compact = strtoupper((string) preg_replace('/[\s.\-]/', '', $normalized));
        $known = [
            'BSICT', 'BSIT', 'BSIS', 'BSCS', 'BSCE', 'BSECE', 'BSEE', 'BSME',
            'BEED', 'BSED', 'BSN', 'BSA', 'BSBA', 'BSCRIM', 'BSHM', 'BSTM',
        ];

        if (in_array($compact, $known, true)) {
            return true;
        }

        if (! str_contains($normalized, ' ') && preg_match('/^[A-Za-z]{2,12}$/', $normalized)) {
            return true;
        }

        return (bool) preg_match('/^(B\.?S\.?|B\.?A\.?|A\.?B\.?)\s+[A-Z]{2,8}$/i', $normalized);
    }
}

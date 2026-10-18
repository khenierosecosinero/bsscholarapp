<?php

namespace App\Support;

class LocationAddress
{
    public static function line(?string $street, ?string $city, ?string $province): string
    {
        $parts = [];

        foreach ([$street, $city, $province] as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(', ', $parts);
    }

    public static function normalizeStreet(?string $street): ?string
    {
        $street = trim((string) $street);

        return $street === '' ? null : $street;
    }
}

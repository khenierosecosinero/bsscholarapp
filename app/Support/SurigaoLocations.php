<?php

namespace App\Support;

class SurigaoLocations
{
    /**
     * Official municipality and city names for one Surigao province.
     *
     * @return list<string>
     */
    public static function municipalities(string $province): array
    {
        $rows = self::catalog()['provinces'][$province]['municipalities'] ?? [];

        return array_values(array_map(
            fn (array $row) => (string) $row['name'],
            $rows
        ));
    }

    /**
     * @return array{provinces: array<string, array{code: string, slug: string, municipalities: list<array{name: string, code: string, slug: string}>}>}
     */
    public static function catalog(): array
    {
        static $catalog = null;

        if ($catalog === null) {
            $catalog = require database_path('data/surigao-locations.php');
        }

        return $catalog;
    }
}

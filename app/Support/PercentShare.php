<?php

namespace App\Support;

class PercentShare
{
    /**
     * Whole-number percentages that sum to 100 when any weight is positive.
     *
     * @param  array<array-key, int|float>  $weights
     * @return array<array-key, int>
     */
    public static function allocate(array $weights): array
    {
        $keys = array_keys($weights);
        $total = array_sum($weights);

        if ($keys === [] || $total <= 0) {
            return array_fill_keys($keys, 0);
        }

        $floors = [];
        $fractions = [];

        foreach ($weights as $key => $weight) {
            $raw = ((float) $weight / $total) * 100;
            $floors[$key] = (int) floor($raw + 1e-9);
            $fractions[$key] = $raw - $floors[$key];
        }

        $remainder = 100 - array_sum($floors);
        arsort($fractions, SORT_NUMERIC);

        foreach (array_keys($fractions) as $key) {
            if ($remainder <= 0) {
                break;
            }

            if ((float) $weights[$key] <= 0) {
                continue;
            }

            $floors[$key]++;
            $remainder--;
        }

        return $floors;
    }
}

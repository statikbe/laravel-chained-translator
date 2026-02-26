<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

// Global namespace - for backward compatibility with external packages
if (!function_exists('array_undot')) {
    /**
     * Expands a list with keys with dots into a hierarchical list.
     * @param array<string, mixed> $dotNotationArray
     * @return array<string, mixed>
     */
    function array_undot(array $dotNotationArray): array
    {
        $array = [];
        /** @var mixed $value */
        foreach ($dotNotationArray as $key => $value) {
            // if there is a space after the dot, this could legitimately be
            // a single key and not nested.
            if (count(explode('. ', $key)) > 1) {
                $array[$key] = $value;
                continue;
            }

            Arr::set($array, $key, $value);
        }

        /** @var array<string, mixed> */
        return $array;
    }
}

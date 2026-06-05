<?php

/**
 * Polyfills for PHP versions below the functions used by this package.
 *
 * The plugin declares support for PHP 7.4 (the WordPress legacy floor), but
 * some helpers rely on functions added in later PHP versions. This file
 * provides minimal polyfills so those calls do not fatal on older runtimes.
 *
 * @since 0.6.1
 *
 * @package WordPress\NanoGptAiProvider
 */

declare(strict_types=1);

if (!function_exists('array_is_list')) {
    /**
     * Polyfill for array_is_list() (added in PHP 8.1).
     *
     * @since 0.6.1
     *
     * @param array<mixed> $array Array to check.
     * @return bool Whether the array keys are 0..n-1 in order.
     */
    function array_is_list(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}

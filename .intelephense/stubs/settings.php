<?php

/**
 * Intelephense stub file for custom helper functions
 * This file is only for IDE autocomplete and type hinting
 */

/**
 * Retrieve cached system settings, optionally for a dot-notated key.
 *
 * @param string|null $key The dot-notated key to retrieve
 * @param mixed $default Default value if key not found
 * @return mixed|array
 */
function settings(?string $key = null, $default = null) {}

/**
 * Inject fake settings for tests without touching DB.
 *
 * @param array<string,mixed> $pairs Key-value pairs to fake
 * @param bool $replace Whether to replace existing fakes
 * @return void
 */
function settings_fake(array $pairs, bool $replace = false): void {}

/**
 * Clear all fake settings.
 *
 * @return void
 */
function settings_fake_clear(): void {}

/**
 * Clear the settings cache.
 *
 * @return void
 */
function settings_forget_cache(): void {}

/**
 * Transform a flat dot-notated array into nested arrays.
 *
 * @param array<string, mixed> $flat
 * @return array<string, mixed>
 */
function settings_nest(array $flat): array {}

/**
 * Flatten nested arrays into dot-notated keys.
 *
 * @param array<string, mixed> $nested
 * @return array<string, mixed>
 */
function settings_flatten(array $nested): array {}

/**
 * Backwards compatible alias for settings cache invalidation.
 *
 * @return void
 */
function settings_flush_cache(): void {}

/**
 * Format date according to locale settings.
 *
 * @param mixed $dt
 * @param string|null $format
 * @return string
 */
function fmt_date($dt, $format = null): string {}

/**
 * Format number according to locale settings.
 *
 * @param mixed $num
 * @param int $decimals
 * @return string
 */
function fmt_number($num, int $decimals = 2): string {}

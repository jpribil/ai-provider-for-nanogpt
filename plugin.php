<?php

/**
 * Plugin Name: AI Provider for NanoGPT
 * Plugin URI: https://nano-gpt.com/
 * Description: nano-gpt.com AI provider for the WordPress AI Client.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 0.7.4
 * Author: Jiri
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-nanogpt
 * Domain Path: /languages
 *
 * @package WordPress\NanoGptAiProvider
 */

declare(strict_types=1);

namespace WordPress\NanoGptAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\NanoGptAiProvider\Admin\SettingsPage;
use WordPress\NanoGptAiProvider\Provider\NanoGptProvider;
use WordPress\NanoGptAiProvider\Settings\Settings;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/polyfill.php';
require_once __DIR__ . '/src/autoload.php';

/**
 * Maximum debug log size in bytes before it is rotated.
 *
 * @since 0.6.1
 *
 * @var int
 */
const DEBUG_LOG_MAX_BYTES = 1048576;

/**
 * Gets the plugin debug log path.
 *
 * The log lives in a dedicated, access-guarded subdirectory of the uploads
 * folder rather than directly in its web-readable root.
 *
 * @since 0.2.2
 *
 * @return string|null Debug log path, or null if unavailable.
 */
function get_debug_log_path(): ?string
{
    if (!function_exists('wp_upload_dir')) {
        return null;
    }

    $uploads = wp_upload_dir(null, false);
    if (!is_array($uploads) || empty($uploads['basedir']) || !is_string($uploads['basedir'])) {
        return null;
    }

    return rtrim($uploads['basedir'], '/\\') . '/nanogpt-ai/debug.log';
}

/**
 * Ensures the debug log directory exists and blocks direct web access to it.
 *
 * @since 0.6.1
 *
 * @param string $dir Debug log directory.
 * @return bool Whether the directory is ready for writing.
 */
function prepare_debug_log_dir(string $dir): bool
{
    if (!is_dir($dir)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($dir);
        } else {
            @mkdir($dir, 0755, true);
        }
    }

    if (!is_dir($dir)) {
        return false;
    }

    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents(
            $htaccess,
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
        );
    }

    $index = $dir . '/index.html';
    if (!file_exists($index)) {
        @file_put_contents($index, '');
    }

    return true;
}

/**
 * Rotates the debug log once it grows past the size cap, keeping one backup.
 *
 * @since 0.6.1
 *
 * @param string $path Debug log path.
 * @return void
 */
function rotate_debug_log_if_large(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $size = @filesize($path);
    if ($size === false || $size < DEBUG_LOG_MAX_BYTES) {
        return;
    }

    @rename($path, $path . '.1');
}

/**
 * Logs fatal PHP errors originating from this plugin to aid debugging.
 *
 * Only runs while WP_DEBUG is enabled and only records fatals whose file lives
 * inside this plugin, so it never captures unrelated site-wide errors.
 *
 * @since 0.2.2
 *
 * @return void
 */
function log_shutdown_error(): void
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $error = error_get_last();
    if (!is_array($error) || empty($error['type'])) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    $file = (string) $error['file'];
    if ($file === '' || !is_plugin_origin_file($file)) {
        return;
    }

    $path = get_debug_log_path();
    if ($path === null || !prepare_debug_log_dir(dirname($path))) {
        return;
    }

    rotate_debug_log_if_large($path);

    $message = sprintf(
        "[%s] type=%s file=%s line=%s message=%s\n",
        gmdate('c'),
        (string) $error['type'],
        $file,
        (string) $error['line'],
        (string) $error['message']
    );

    @file_put_contents($path, $message, FILE_APPEND | LOCK_EX);
}

/**
 * Checks whether a file path belongs to this plugin.
 *
 * @since 0.6.1
 *
 * @param string $file Absolute file path from an error record.
 * @return bool Whether the file is inside this plugin's directory.
 */
function is_plugin_origin_file(string $file): bool
{
    $normalize = static function (string $path): string {
        return function_exists('wp_normalize_path')
            ? wp_normalize_path($path)
            : str_replace('\\', '/', $path);
    };

    return strpos($normalize($file), $normalize(__DIR__)) === 0;
}

register_shutdown_function(__NAMESPACE__ . '\\log_shutdown_error');

/**
 * Loads plugin translations.
 *
 * @since 0.6.0
 *
 * @return void
 */
function load_textdomain(): void
{
    load_plugin_textdomain(
        'ai-provider-for-nanogpt',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

add_action('plugins_loaded', __NAMESPACE__ . '\\load_textdomain');

/**
 * Registers AI Provider for NanoGPT with the AI Client.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(NanoGptProvider::class)) {
        return;
    }

    $registry->registerProvider(NanoGptProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);

/**
 * Registers WordPress admin settings for this provider.
 *
 * @since 0.2.0
 *
 * @return void
 */
function register_admin_settings(): void
{
    if (!is_admin()) {
        return;
    }

    (new SettingsPage())->register();
}

add_action('plugins_loaded', __NAMESPACE__ . '\\register_admin_settings');

/**
 * Prepends the configured default image model to provider preferences.
 *
 * @since 0.2.1
 *
 * @param list<string> $modelIds Preferred model IDs.
 * @return list<string> Preferred model IDs.
 */
function prepend_default_image_model(array $modelIds): array
{
    return prepend_configured_model(Settings::getDefaultImageModelId(), $modelIds);
}

/**
 * Prepends the configured default text model to provider preferences.
 *
 * @since 0.2.1
 *
 * @param list<string> $modelIds Preferred model IDs.
 * @return list<string> Preferred model IDs.
 */
function prepend_default_text_model(array $modelIds): array
{
    return prepend_configured_model(Settings::getDefaultTextModelId(), $modelIds);
}

/**
 * Prepends a configured model ID to a model list.
 *
 * @since 0.2.1
 *
 * @param string|null  $configuredModelId Configured model ID.
 * @param list<string> $modelIds Preferred model IDs.
 * @return list<string> Preferred model IDs.
 */
function prepend_configured_model(?string $configuredModelId, array $modelIds): array
{
    if ($configuredModelId === null) {
        return $modelIds;
    }

    return array_values(array_unique(array_merge([$configuredModelId], $modelIds)));
}

add_filter('nanogpt_ai_provider_preferred_image_model_ids', __NAMESPACE__ . '\\prepend_default_image_model', 1);
add_filter('nanogpt_ai_provider_preferred_text_model_ids', __NAMESPACE__ . '\\prepend_default_text_model', 1);

<?php

declare(strict_types=1);

namespace WordPress\NanoGptAiProvider\Settings;

/**
 * Shared settings helper for the NanoGPT provider.
 *
 * @since 0.2.0
 */
class Settings
{
    public const OPTION_NAME = 'nanogpt_ai_provider_settings';
    public const KEY_DEFAULT_IMAGE_MODEL_ID = 'default_image_model_id';
    public const KEY_DEFAULT_TEXT_MODEL_ID = 'default_text_model_id';
    public const KEY_DEFAULT_IMAGE_SIZE = 'default_image_size';
    public const KEY_DEFAULT_IMAGE_OPTIONS_JSON = 'default_image_options_json';

    /**
     * Gets the selected default image model ID.
     *
     * @since 0.2.0
     *
     * @return string|null Selected model ID, or null if unset.
     */
    public static function getDefaultImageModelId(): ?string
    {
        return self::getStringSetting(self::KEY_DEFAULT_IMAGE_MODEL_ID);
    }

    /**
     * Gets the selected default text model ID.
     *
     * @since 0.2.0
     *
     * @return string|null Selected model ID, or null if unset.
     */
    public static function getDefaultTextModelId(): ?string
    {
        return self::getStringSetting(self::KEY_DEFAULT_TEXT_MODEL_ID);
    }

    /**
     * Gets the selected default image size.
     *
     * @since 0.3.0
     *
     * @return string|null Selected size, or null if unset.
     */
    public static function getDefaultImageSize(): ?string
    {
        return self::getStringSetting(self::KEY_DEFAULT_IMAGE_SIZE);
    }

    /**
     * Gets extra default image options.
     *
     * @since 0.3.0
     *
     * @return array<string, mixed> Extra image options.
     */
    public static function getDefaultImageOptions(): array
    {
        $json = self::getStringSetting(self::KEY_DEFAULT_IMAGE_OPTIONS_JSON);
        if ($json === null) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
    }

    /**
     * Gets a string setting.
     *
     * @since 0.2.0
     *
     * @param string $key Setting key.
     * @return string|null Setting value.
     */
    private static function getStringSetting(string $key): ?string
    {
        $settings = self::getSettings();
        if (!isset($settings[$key]) || !is_string($settings[$key]) || $settings[$key] === '') {
            return null;
        }
        return $settings[$key];
    }

    /**
     * Gets all plugin settings.
     *
     * @since 0.2.0
     *
     * @return array<string, mixed> Settings.
     */
    public static function getSettings(): array
    {
        if (!function_exists('get_option')) {
            return [];
        }

        $settings = get_option(self::OPTION_NAME, []);
        return is_array($settings) ? $settings : [];
    }
}

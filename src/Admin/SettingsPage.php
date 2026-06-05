<?php

declare(strict_types=1);

namespace WordPress\NanoGptAiProvider\Admin;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\NanoGptAiProvider\Provider\NanoGptProvider;
use WordPress\NanoGptAiProvider\Settings\Settings;

/**
 * WordPress admin settings page for the NanoGPT provider.
 *
 * @since 0.2.0
 */
class SettingsPage
{
    private const PAGE_SLUG = 'nanogpt-ai-provider';
    private const IMAGE_MODEL_FIELD_ID = 'nanogpt-ai-provider-image-model';
    private const TEXT_MODEL_FIELD_ID = 'nanogpt-ai-provider-text-model';
    private const IMAGE_SIZE_FIELD_ID = 'nanogpt-ai-provider-image-size';
    private const IMAGE_PRICE_ID = 'nanogpt-ai-provider-image-price';

    /**
     * Registers WordPress hooks for the settings page.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Adds the settings page.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function addMenuPage(): void
    {
        add_options_page(
            __('nano-gpt.com AI', 'ai-provider-for-nanogpt'),
            __('NanoGPT', 'ai-provider-for-nanogpt'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Registers settings and fields.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function registerSettings(): void
    {
        register_setting(
            self::PAGE_SLUG,
            Settings::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => [],
            ]
        );

        add_settings_section(
            'nanogpt_ai_provider_defaults',
            __('Default models', 'ai-provider-for-nanogpt'),
            [$this, 'renderDefaultsSection'],
            self::PAGE_SLUG
        );

        add_settings_field(
            Settings::KEY_DEFAULT_IMAGE_MODEL_ID,
            __('Image generation model', 'ai-provider-for-nanogpt'),
            [$this, 'renderImageModelField'],
            self::PAGE_SLUG,
            'nanogpt_ai_provider_defaults'
        );

        add_settings_field(
            Settings::KEY_DEFAULT_IMAGE_SIZE,
            __('Image size', 'ai-provider-for-nanogpt'),
            [$this, 'renderImageSizeField'],
            self::PAGE_SLUG,
            'nanogpt_ai_provider_defaults'
        );

        add_settings_field(
            Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON,
            __('Extra image parameters', 'ai-provider-for-nanogpt'),
            [$this, 'renderImageOptionsField'],
            self::PAGE_SLUG,
            'nanogpt_ai_provider_defaults'
        );

        add_settings_field(
            Settings::KEY_DEFAULT_TEXT_MODEL_ID,
            __('Text generation model', 'ai-provider-for-nanogpt'),
            [$this, 'renderTextModelField'],
            self::PAGE_SLUG,
            'nanogpt_ai_provider_defaults'
        );
    }

    /**
     * Sanitizes submitted settings.
     *
     * @since 0.2.0
     *
     * @param mixed $settings Raw settings.
     * @return array<string, string> Sanitized settings.
     */
    public function sanitizeSettings($settings): array
    {
        if (!is_array($settings)) {
            return [];
        }

        $sanitized = [];
        foreach (
            [
                Settings::KEY_DEFAULT_IMAGE_MODEL_ID,
                Settings::KEY_DEFAULT_TEXT_MODEL_ID,
                Settings::KEY_DEFAULT_IMAGE_SIZE,
            ] as $key
        ) {
            if (!isset($settings[$key]) || !is_string($settings[$key])) {
                continue;
            }

            $value = sanitize_text_field($settings[$key]);
            if ($value !== '') {
                $sanitized[$key] = $value;
            }
        }

        if (
            isset($settings[Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON]) &&
            is_string($settings[Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON])
        ) {
            $json = trim(wp_unslash($settings[Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON]));
            if ($json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded) && !array_is_list($decoded)) {
                    $encoded = wp_json_encode(
                        $decoded,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    );
                    if (is_string($encoded)) {
                        $sanitized[Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON] = $encoded;
                    }
                } else {
                    add_settings_error(
                        Settings::OPTION_NAME,
                        'invalid_image_options_json',
                        __('Extra image parameters must be a valid JSON object.', 'ai-provider-for-nanogpt')
                    );
                }
            }
        }

        return $sanitized;
    }

    /**
     * Renders the settings page.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $imageModelData = $this->fetchModelData('image-models?detailed=true', true);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('NanoGPT', 'ai-provider-for-nanogpt'); ?></h1>
            <?php $this->renderBalanceNotice(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::PAGE_SLUG);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php $this->renderModelMetadataScript(
            $imageModelData,
            $this->fetchModelData('models?detailed=true', false)
        ); ?>
        <?php
    }

    /**
     * Renders the account balance notice shown under the page heading.
     *
     * @since 0.7.0
     *
     * @return void
     */
    private function renderBalanceNotice(): void
    {
        $apiKey = $this->getConfiguredApiKey();
        if ($apiKey === null) {
            printf(
                '<div class="notice notice-info inline"><p>%s</p></div>',
                esc_html__(
                    'Add your NanoGPT API key in Settings → Connectors to see your account balance.',
                    'ai-provider-for-nanogpt'
                )
            );
            return;
        }

        $balance = $this->fetchBalance($apiKey);
        if ($balance === null) {
            printf(
                '<div class="notice notice-warning inline"><p>%s</p></div>',
                esc_html__('Could not retrieve your NanoGPT account balance right now.', 'ai-provider-for-nanogpt')
            );
            return;
        }

        $parts = [];
        if ($balance['usd'] !== null) {
            $parts[] = sprintf(
                /* translators: %s: account balance in US dollars. */
                esc_html__('%s USD', 'ai-provider-for-nanogpt'),
                esc_html(number_format_i18n($balance['usd'], 2))
            );
        }
        if ($balance['nano'] !== null) {
            $parts[] = sprintf(
                /* translators: %s: account balance in NANO. */
                esc_html__('%s NANO', 'ai-provider-for-nanogpt'),
                esc_html(number_format_i18n($balance['nano'], 4))
            );
        }

        if ($parts === []) {
            return;
        }

        printf(
            '<div class="notice notice-info inline"><p><strong>%s</strong> %s</p></div>',
            esc_html__('NanoGPT account balance:', 'ai-provider-for-nanogpt'),
            implode(' · ', $parts)
        );
    }

    /**
     * Gets the NanoGPT API key configured via the AI Client (Connectors UI or environment).
     *
     * @since 0.7.0
     *
     * @return string|null API key, or null if none is configured.
     */
    private function getConfiguredApiKey(): ?string
    {
        if (!class_exists(AiClient::class)) {
            return null;
        }

        try {
            $registry = AiClient::defaultRegistry();
            if (!$registry->hasProvider(NanoGptProvider::class)) {
                return null;
            }

            $authentication = $registry->getProviderRequestAuthentication(NanoGptProvider::class);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$authentication instanceof ApiKeyRequestAuthentication) {
            return null;
        }

        $apiKey = $authentication->getApiKey();
        return $apiKey !== '' ? $apiKey : null;
    }

    /**
     * Fetches the account balance from the NanoGPT check-balance endpoint.
     *
     * The result is cached briefly per API key to avoid a request on every page load.
     *
     * @since 0.7.0
     *
     * @param string $apiKey NanoGPT API key.
     * @return array{usd: float|null, nano: float|null}|null Balance, or null on failure.
     */
    private function fetchBalance(string $apiKey): ?array
    {
        $cacheKey = 'nanogpt_ai_provider_balance_' . md5($apiKey);
        $cached = get_transient($cacheKey);
        if (is_array($cached) && array_key_exists('usd', $cached) && array_key_exists('nano', $cached)) {
            return $cached;
        }

        $response = wp_remote_post(
            NanoGptProvider::rootUrlWithPath('api/check-balance'),
            [
                'timeout' => 15,
                'headers' => ['x-api-key' => $apiKey],
            ]
        );
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return null;
        }

        $balance = [
            'usd' => isset($data['usd_balance']) && is_numeric($data['usd_balance'])
                ? (float) $data['usd_balance']
                : null,
            'nano' => isset($data['nano_balance']) && is_numeric($data['nano_balance'])
                ? (float) $data['nano_balance']
                : null,
        ];

        if ($balance['usd'] === null && $balance['nano'] === null) {
            return null;
        }

        set_transient($cacheKey, $balance, 5 * MINUTE_IN_SECONDS);
        return $balance;
    }

    /**
     * Renders the default models section intro.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function renderDefaultsSection(): void
    {
        echo '<p>' . esc_html__(
            'These models are used first when a WordPress AI feature asks for text or image generation without selecting a specific model.',
            'ai-provider-for-nanogpt'
        ) . '</p>';
    }

    /**
     * Renders the image model select field.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function renderImageModelField(): void
    {
        $attributes = [
            'id' => self::IMAGE_MODEL_FIELD_ID,
            'data-nanogpt-image-model-select' => '1',
        ];

        $this->renderModelField(
            Settings::KEY_DEFAULT_IMAGE_MODEL_ID,
            Settings::getDefaultImageModelId(),
            $this->fetchModelData('image-models?detailed=true', true),
            __('Use provider default', 'ai-provider-for-nanogpt'),
            $attributes
        );
    }

    /**
     * Renders the image size field.
     *
     * @since 0.3.0
     *
     * @return void
     */
    public function renderImageSizeField(): void
    {
        $selectedModelId = Settings::getDefaultImageModelId();
        $sizes = $this->getImageSizeSuggestions($selectedModelId);
        $selectedSize = Settings::getDefaultImageSize();

        // Keep a previously saved size selectable even if the current model no longer suggests it.
        if ($selectedSize !== null && !in_array($selectedSize, $sizes, true)) {
            array_unshift($sizes, $selectedSize);
        }

        printf(
            '<select id="%1$s" class="regular-text" name="%2$s">',
            esc_attr(self::IMAGE_SIZE_FIELD_ID),
            esc_attr(Settings::OPTION_NAME . '[' . Settings::KEY_DEFAULT_IMAGE_SIZE . ']')
        );
        printf('<option value="">%s</option>', esc_html__('Provider default', 'ai-provider-for-nanogpt'));
        foreach ($sizes as $size) {
            printf(
                '<option value="%1$s"%2$s>%1$s</option>',
                esc_attr($size),
                selected($selectedSize, $size, false)
            );
        }
        echo '</select>';

        echo '<p class="description">' . esc_html__(
            'Suggested values come from the selected NanoGPT image model. Choose Provider default to let NanoGPT decide.',
            'ai-provider-for-nanogpt'
        ) . '</p>';

        printf(
            '<p class="description" id="%s" style="display:none;"></p>',
            esc_attr(self::IMAGE_PRICE_ID)
        );
    }

    /**
     * Renders the extra image parameters field.
     *
     * @since 0.3.0
     *
     * @return void
     */
    public function renderImageOptionsField(): void
    {
        $settings = Settings::getSettings();
        $value = isset($settings[Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON]) &&
            is_string($settings[Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON])
            ? $settings[Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON]
            : '';

        printf(
            '<textarea class="large-text code" rows="7" name="%1$s" placeholder="%2$s">%3$s</textarea>',
            esc_attr(Settings::OPTION_NAME . '[' . Settings::KEY_DEFAULT_IMAGE_OPTIONS_JSON . ']'),
            esc_attr('{"n":1}'),
            esc_textarea($value)
        );
        echo '<p class="description">' . esc_html__(
            'Optional JSON object with NanoGPT image parameters. The Image size field writes the size parameter separately and wins over size here.',
            'ai-provider-for-nanogpt'
        ) . '</p>';
    }

    /**
     * Renders the text model select field.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function renderTextModelField(): void
    {
        $this->renderModelField(
            Settings::KEY_DEFAULT_TEXT_MODEL_ID,
            Settings::getDefaultTextModelId(),
            $this->fetchModelData('models?detailed=true', false),
            __('Use NanoGPT catalog order', 'ai-provider-for-nanogpt'),
            [
                'id' => self::TEXT_MODEL_FIELD_ID,
                'data-nanogpt-text-model-select' => '1',
            ]
        );
    }

    /**
     * Renders a model select or fallback text input.
     *
     * @since 0.2.0
     *
     * @param string              $key Setting key.
     * @param string|null         $selectedModelId Selected model ID.
     * @param array<string,array<string,mixed>> $models Raw model data by ID.
     * @param string              $emptyLabel Empty option label.
     * @param array<string,string> $attributes Additional select attributes.
     * @return void
     */
    private function renderModelField(
        string $key,
        ?string $selectedModelId,
        array $models,
        string $emptyLabel,
        array $attributes = []
    ): void {
        $name = Settings::OPTION_NAME . '[' . $key . ']';

        if ($models === []) {
            printf(
                '<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />',
                esc_attr($name),
                esc_attr((string) $selectedModelId),
                esc_attr__('Model ID', 'ai-provider-for-nanogpt')
            );
            echo '<p class="description">' . esc_html__(
                'Could not load NanoGPT model list. Enter a model ID manually, or reload this page later.',
                'ai-provider-for-nanogpt'
            ) . '</p>';
            return;
        }

        if ($selectedModelId && !isset($models[$selectedModelId])) {
            $models = [$selectedModelId => ['id' => $selectedModelId, 'name' => $selectedModelId]] + $models;
        }

        $attributeHtml = '';
        foreach ($attributes as $attributeName => $attributeValue) {
            $attributeHtml .= sprintf(' %s="%s"', esc_attr($attributeName), esc_attr($attributeValue));
        }

        $models = $this->sortModelsByName($models);

        printf('<select name="%1$s" class="regular-text"%2$s>', esc_attr($name), $attributeHtml);
        printf('<option value="">%s</option>', esc_html($emptyLabel));
        foreach ($models as $modelId => $modelData) {
            printf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($modelId),
                selected($selectedModelId, $modelId, false),
                esc_html($this->getModelDisplayName($modelId, $modelData) . ' (' . $modelId . ')')
            );
        }
        echo '</select>';

        $description = $this->getSelectedModelDescription($selectedModelId, $models);
        printf(
            '<p class="description" data-nanogpt-model-description-for="%1$s"%2$s>%3$s</p>',
            esc_attr($attributes['id'] ?? ''),
            $description === '' ? ' style="display:none;"' : '',
            esc_html($description)
        );
    }

    /**
     * Fetches a NanoGPT model catalog for the settings UI.
     *
     * @since 0.2.0
     *
     * @param string $path Endpoint path.
     * @param bool   $image Whether this is the image model catalog.
     * @return array<string,array<string,mixed>> Model metadata by ID.
     */
    private function fetchModelData(string $path, bool $image): array
    {
        $cacheKey = 'nanogpt_ai_provider_admin_models_v2_' . md5($path);
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            NanoGptProvider::url($path),
            [
                'timeout' => 15,
            ]
        );
        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $models = [];
        foreach ($data['data'] as $modelData) {
            if (!is_array($modelData) || !isset($modelData['id']) || !is_string($modelData['id'])) {
                continue;
            }
            if ($image && !$this->isSelectableImageModel($modelData)) {
                continue;
            }

            $models[$modelData['id']] = $modelData;
        }

        set_transient($cacheKey, $models, HOUR_IN_SECONDS);
        return $models;
    }

    /**
     * Gets image size suggestions for the selected image model.
     *
     * @since 0.3.0
     *
     * @param string|null $modelId Selected model ID.
     * @return list<string> Suggested image size values.
     */
    private function getImageSizeSuggestions(?string $modelId): array
    {
        $fallback = [
            'auto',
            '1024x1024',
            '1536x1024',
            '1024x1536',
            '1k',
            '2k',
            '4k',
            'square',
            'square_hd',
            'landscape_16_9',
            'portrait_16_9',
        ];

        if ($modelId === null) {
            return $fallback;
        }

        $models = $this->fetchModelData('image-models?detailed=true', true);
        if (!isset($models[$modelId]) || !is_array($models[$modelId])) {
            return $fallback;
        }

        $supportedParameters = $models[$modelId]['supported_parameters'] ?? null;
        if (!is_array($supportedParameters)) {
            return $fallback;
        }

        $resolutions = $supportedParameters['resolutions'] ?? null;
        if (!is_array($resolutions)) {
            return $fallback;
        }

        $sizes = [];
        foreach ($resolutions as $resolution) {
            if (is_string($resolution) && $resolution !== '') {
                $sizes[] = $resolution;
            }
        }

        return $sizes === [] ? $fallback : array_values(array_unique($sizes));
    }

    /**
     * Renders JavaScript to update model descriptions and image size suggestions.
     *
     * @since 0.5.0
     *
     * @param array<string,array<string,mixed>> $models Raw image model data by ID.
     * @param array<string,array<string,mixed>> $textModels Raw text model data by ID.
     * @return void
     */
    private function renderModelMetadataScript(array $models, array $textModels): void
    {
        $sizesByModelId = [];
        $descriptionsByModelId = [];
        foreach (array_merge($models, $textModels) as $modelId => $modelData) {
            $description = $this->getModelDescription($modelData);
            if ($description !== '') {
                $descriptionsByModelId[$modelId] = $description;
            }
        }

        foreach ($models as $modelId => $modelData) {
            $supportedParameters = $modelData['supported_parameters'] ?? null;
            if (!is_array($supportedParameters)) {
                continue;
            }

            $resolutions = $supportedParameters['resolutions'] ?? null;
            if (!is_array($resolutions)) {
                continue;
            }

            $sizes = [];
            foreach ($resolutions as $resolution) {
                if (is_string($resolution) && $resolution !== '') {
                    $sizes[] = $resolution;
                }
            }

            if ($sizes !== []) {
                $sizesByModelId[$modelId] = array_values(array_unique($sizes));
            }
        }

        $pricingByModelId = $this->buildPricingMap($models);

        $encodedSizes = wp_json_encode($sizesByModelId);
        $encodedDescriptions = wp_json_encode($descriptionsByModelId);
        $encodedPricing = wp_json_encode($pricingByModelId);
        $encodedLabels = wp_json_encode([
            'price' => __('Estimated price:', 'ai-provider-for-nanogpt'),
            'perImage' => __('per image', 'ai-provider-for-nanogpt'),
            'providerDefault' => __('Provider default', 'ai-provider-for-nanogpt'),
        ]);
        if (
            !is_string($encodedSizes) ||
            !is_string($encodedDescriptions) ||
            !is_string($encodedPricing) ||
            !is_string($encodedLabels)
        ) {
            return;
        }
        ?>
        <script>
        (function () {
            const sizesByModelId = <?php echo $encodedSizes; ?>;
            const descriptionsByModelId = <?php echo $encodedDescriptions; ?>;
            const pricingByModelId = <?php echo $encodedPricing; ?>;
            const labels = <?php echo $encodedLabels; ?>;
            const imageModelSelect = document.getElementById(<?php echo wp_json_encode(self::IMAGE_MODEL_FIELD_ID); ?>);
            const textModelSelect = document.getElementById(<?php echo wp_json_encode(self::TEXT_MODEL_FIELD_ID); ?>);
            const sizeSelect = document.getElementById(<?php echo wp_json_encode(self::IMAGE_SIZE_FIELD_ID); ?>);
            const priceEl = document.getElementById(<?php echo wp_json_encode(self::IMAGE_PRICE_ID); ?>);

            function formatPrice(amount, currency) {
                const value = Number(amount);
                if (!isFinite(value)) {
                    return '';
                }
                const rounded = Math.round(value * 10000) / 10000;
                return currency === 'USD' ? '$' + rounded : rounded + ' ' + currency;
            }

            function updatePrice() {
                if (!priceEl || !imageModelSelect) {
                    return;
                }

                const pricing = pricingByModelId[imageModelSelect.value];
                const perImage = pricing && pricing.perImage ? pricing.perImage : null;
                if (!perImage) {
                    priceEl.textContent = '';
                    priceEl.style.display = 'none';
                    return;
                }

                const currency = pricing.currency || 'USD';
                const size = sizeSelect ? sizeSelect.value.trim() : '';
                let amountText;
                let suffix = labels.perImage;

                if (size && Object.prototype.hasOwnProperty.call(perImage, size)) {
                    amountText = formatPrice(perImage[size], currency);
                    suffix = labels.perImage + ' (' + size + ')';
                } else {
                    const values = Object.keys(perImage).map(function (k) { return Number(perImage[k]); })
                        .filter(function (v) { return isFinite(v); });
                    if (values.length === 0) {
                        priceEl.textContent = '';
                        priceEl.style.display = 'none';
                        return;
                    }
                    const min = Math.min.apply(null, values);
                    const max = Math.max.apply(null, values);
                    amountText = min === max
                        ? formatPrice(min, currency)
                        : formatPrice(min, currency) + '–' + formatPrice(max, currency);
                }

                priceEl.textContent = labels.price + ' ' + amountText + ' ' + suffix;
                priceEl.style.display = '';
            }

            function setOptions(sizes) {
                if (!sizeSelect) {
                    return;
                }

                const current = sizeSelect.value;
                sizeSelect.innerHTML = '';

                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = labels.providerDefault;
                sizeSelect.appendChild(empty);

                let hasCurrent = false;
                sizes.forEach(function (size) {
                    const option = document.createElement('option');
                    option.value = size;
                    option.textContent = size;
                    if (size === current) {
                        hasCurrent = true;
                    }
                    sizeSelect.appendChild(option);
                });

                // Preserve the current selection if still offered, otherwise fall back to provider default.
                sizeSelect.value = hasCurrent ? current : '';
            }

            function updateDescription(select) {
                if (!select) {
                    return;
                }

                const description = descriptionsByModelId[select.value] || '';
                const descriptionEl = document.querySelector('[data-nanogpt-model-description-for="' + select.id + '"]');
                if (!descriptionEl) {
                    return;
                }

                descriptionEl.textContent = description;
                descriptionEl.style.display = description ? '' : 'none';
            }

            if (imageModelSelect && sizeSelect) {
                imageModelSelect.addEventListener('change', function () {
                    const sizes = sizesByModelId[imageModelSelect.value] || [];
                    setOptions(sizes);
                    updateDescription(imageModelSelect);
                    updatePrice();
                });
            }

            if (sizeSelect) {
                sizeSelect.addEventListener('change', updatePrice);
            }

            if (textModelSelect) {
                textModelSelect.addEventListener('change', function () {
                    updateDescription(textModelSelect);
                });
            }

            updatePrice();
        }());
        </script>
        <?php
    }

    /**
     * Builds an image price map keyed by model ID from raw NanoGPT catalog data.
     *
     * @since 0.7.0
     *
     * @param array<string,array<string,mixed>> $models Raw image model data by ID.
     * @return array<string,array{currency:string,perImage:array<string,float>}> Pricing by model ID.
     */
    private function buildPricingMap(array $models): array
    {
        $pricingByModelId = [];
        foreach ($models as $modelId => $modelData) {
            $pricing = $modelData['pricing'] ?? null;
            if (!is_array($pricing)) {
                continue;
            }

            $perImageRaw = $pricing['per_image'] ?? null;
            if (!is_array($perImageRaw)) {
                continue;
            }

            $perImage = [];
            foreach ($perImageRaw as $size => $amount) {
                if (is_string($size) && $size !== '' && is_numeric($amount)) {
                    $perImage[$size] = (float) $amount;
                }
            }

            if ($perImage === []) {
                continue;
            }

            $currency = isset($pricing['currency']) && is_string($pricing['currency']) && $pricing['currency'] !== ''
                ? $pricing['currency']
                : 'USD';

            $pricingByModelId[$modelId] = [
                'currency' => $currency,
                'perImage' => $perImage,
            ];
        }

        return $pricingByModelId;
    }

    /**
     * Sorts models by their display name.
     *
     * @since 0.5.0
     *
     * @param array<string,array<string,mixed>> $models Raw model data by ID.
     * @return array<string,array<string,mixed>> Sorted raw model data by ID.
     */
    private function sortModelsByName(array $models): array
    {
        uksort(
            $models,
            function (string $a, string $b) use ($models): int {
                return strcasecmp(
                    $this->getModelDisplayName($a, $models[$a]),
                    $this->getModelDisplayName($b, $models[$b])
                );
            }
        );

        return $models;
    }

    /**
     * Gets a model display name.
     *
     * @since 0.5.0
     *
     * @param string              $modelId Model ID.
     * @param array<string,mixed> $modelData Raw model data.
     * @return string Display name.
     */
    private function getModelDisplayName(string $modelId, array $modelData): string
    {
        return isset($modelData['name']) && is_string($modelData['name']) && $modelData['name'] !== ''
            ? $modelData['name']
            : $modelId;
    }

    /**
     * Gets the selected model description.
     *
     * @since 0.5.0
     *
     * @param string|null                      $selectedModelId Selected model ID.
     * @param array<string,array<string,mixed>> $models Raw model data by ID.
     * @return string Description.
     */
    private function getSelectedModelDescription(?string $selectedModelId, array $models): string
    {
        if ($selectedModelId === null || !isset($models[$selectedModelId])) {
            return '';
        }

        return $this->getModelDescription($models[$selectedModelId]);
    }

    /**
     * Gets a model description.
     *
     * @since 0.5.0
     *
     * @param array<string,mixed> $modelData Raw model data.
     * @return string Description.
     */
    private function getModelDescription(array $modelData): string
    {
        return isset($modelData['description']) && is_string($modelData['description'])
            ? trim($modelData['description'])
            : '';
    }

    /**
     * Checks whether an image model should be exposed as a default generation choice.
     *
     * @since 0.2.0
     *
     * @param array<string,mixed> $modelData Raw model data.
     * @return bool Whether the model is selectable.
     */
    private function isSelectableImageModel(array $modelData): bool
    {
        if (!isset($modelData['capabilities']) || !is_array($modelData['capabilities'])) {
            return true;
        }

        return !isset($modelData['capabilities']['image_generation']) ||
            !empty($modelData['capabilities']['image_generation']);
    }
}

# nano-gpt.com AI

WordPress AI Client provider for [nano-gpt.com](https://nano-gpt.com/).

> 🧩 **Companion plugin:** pairs with **[AI Image Block (NanoGPT)](https://github.com/jpribil/ai-image-block-for-nanogpt)** — a Gutenberg block that generates images in the editor with model/size selection and a live price estimate, built on top of this provider. The two are designed to be used together: this plugin teaches WordPress how to talk to NanoGPT, the block gives editors a UI for it.

## Get a NanoGPT account

You need a NanoGPT account and API key.

> 💸 **Affiliate link (optional):** signing up via **[nano-gpt.com/r/VLX8bWbQ](https://nano-gpt.com/r/VLX8bWbQ)** supports this plugin's author at no extra cost to you. **This is an affiliate link and is entirely optional** — if you'd rather not use it, the plain link works exactly the same: **[nano-gpt.com](https://nano-gpt.com/)**.

## Requirements

- WordPress 7.0 or newer
- PHP 7.4 or newer
- NanoGPT API key

## Capabilities

- Text generation through NanoGPT's OpenAI-compatible chat completions endpoint.
- Image generation through NanoGPT's OpenAI-compatible image generation endpoint.
- Dynamic model discovery from NanoGPT text and image model catalog endpoints.

## Installation

Upload `ai-provider-for-nanogpt` to `wp-content/plugins/`, activate the plugin, then add your NanoGPT API key in `Settings > Connectors`.

## Default model selection

Go to `Settings > NanoGPT` in WordPress and choose:

- Image generation model
- Image size
- Extra image parameters
- Text generation model

These defaults are used when a WordPress AI feature asks this provider to generate text or an image without selecting a specific model.

Model dropdowns are sorted alphabetically and show the selected model description when NanoGPT returns one. The image size field updates its suggestions immediately when you change the selected image model, using NanoGPT `supported_parameters.resolutions`. Extra image parameters are a JSON object passed to NanoGPT image generation, for example:

```json
{
  "n": 1
}
```

When `WP_DEBUG` is enabled, this plugin records fatal PHP errors that originate from its own files to an access-guarded log:

```text
wp-content/uploads/nanogpt-ai/debug.log
```

The directory is protected with `.htaccess` and `index.html` guards, the log is capped at 1 MB (rotated to `debug.log.1`), and unrelated site-wide errors are never recorded. Leave `WP_DEBUG` off in production to disable logging entirely.

## Usage

Once configured, other WordPress AI Client integrations can use NanoGPT automatically:

```php
$image = wp_ai_client_prompt('A clean editorial image of a WordPress block editor screen')
    ->usingModelPreference('gpt-image-1.5')
    ->generateImage();
```

If the calling UI does not expose model selection, the AI Client will use the default selected in `Settings > NanoGPT`. You can still override that ordering in code:

```php
add_filter(
    'nanogpt_ai_provider_preferred_image_model_ids',
    static function (array $defaultModelIds): array {
        return array(
            'nano-banana-pro',
            'gpt-image-1.5',
            'seedream-v4.5',
        );
    }
);
```

Text defaults can be controlled with `nanogpt_ai_provider_preferred_text_model_ids`.

For NanoGPT model-specific parameters, use AI Client custom options:

```php
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;

$config = ModelConfig::fromArray(
    array(
        'customOptions' => array(
            'size' => '1024x1024',
            'guidance_scale' => 7.5,
        ),
    )
);

$image = wp_ai_client_prompt('A product photo on a white background')
    ->usingModelPreference('gpt-image-1.5')
    ->usingModelConfig($config)
    ->generateImage();
```

## Languages

The settings UI uses the active WordPress locale. English is the default, with bundled Czech (`cs_CZ`), German (`de_DE`), and English (`en_US`) translation files. Model names and model descriptions are shown exactly as NanoGPT returns them.

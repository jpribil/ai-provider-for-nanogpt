=== AI Provider for NanoGPT ===
Contributors: jiri
Tags: ai, nanogpt, image-generation, llm, ai-client
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.7.3
License: GPL-2.0-or-later
License URI: https://spdx.org/licenses/GPL-2.0-or-later.html

nano-gpt.com AI provider for the WordPress AI Client.

== Description ==

Registers NanoGPT as a provider for the WordPress AI Client. Supports text and image generation using NanoGPT's OpenAI-compatible API endpoints.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/ai-provider-for-nanogpt/`.
2. Activate the plugin in WordPress.
3. Add your NanoGPT API key in Settings > Connectors.

== Changelog ==

= 0.7.3 =
* Add Tags and Tested up to headers to the readme for the WordPress.org plugin directory.

= 0.7.2 =
* Rename the plugin to "AI Provider for NanoGPT" to follow the WordPress.org "X for Y" naming guideline.

= 0.7.1 =
* Rename the plugin from "nano-gpt.com AI (codex)" to "nano-gpt.com AI" and move the debug log to uploads/nanogpt-ai/.

= 0.7.0 =
* Show the remaining NanoGPT account balance at the top of the settings page.
* Show the estimated NanoGPT image price for the selected model and size on the settings page.

= 0.6.1 =
* Fix a fatal error on PHP 7.4 and 8.0 by polyfilling array_is_list().
* Detect ProviderMetadata description support from the constructor signature instead of a hardcoded AI Client version.
* Harden the debug log: gate it behind WP_DEBUG, only record this plugin's own fatal errors, move it to an access-guarded uploads subdirectory, and cap its size.

= 0.6.0 =
* Add Czech, German, and English translation files for the settings UI.

= 0.5.0 =
* Sort model dropdowns alphabetically and show selected model descriptions.

= 0.4.0 =
* Update image size suggestions immediately when the selected image model changes.
* Rename the settings menu item to NanoGPT.

= 0.3.0 =
* Add settings for default image size and extra model-specific image parameters.

= 0.2.2 =
* Add a plugin-local fatal error log for debugging broken WordPress REST JSON responses.

= 0.2.1 =
* Move default model preference handling to WordPress filters for more robust REST responses.

= 0.2.0 =
* Add a WordPress settings page for selecting default text and image models.

= 0.1.1 =
* Prefer sensible default image generation models and add filters to control default model ordering.

= 0.1.0 =
* Initial provider implementation for text and image generation.

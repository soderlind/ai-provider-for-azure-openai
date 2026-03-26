=== AI Provider for Azure OpenAI ===
Contributors: PerS
Tags: ai, azure, openai, gpt, connector
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Provider for Azure OpenAI for the WordPress AI Client.

== Description ==

This plugin provides Azure OpenAI integration for the WordPress AI Client, enabling text generation, image generation, embedding generation, and text-to-speech using Azure's hosted OpenAI models.

**Features:**

* Text generation using GPT-4, GPT-4o, GPT-4.1, GPT-3.5-Turbo deployments
* Image generation using DALL-E 2 and DALL-E 3 deployments
* Embedding generation using text-embedding-ada-002, text-embedding-3-small/large deployments
* Text-to-speech using tts-1 and tts-1-hd deployments
* Integrated into the Connectors settings page (Settings → Connectors) — configure API key, endpoint, deployment, and capabilities in one place
* Environment variable support for credentials

**Requirements:**

* PHP 7.4 or higher
* WordPress 7.0 or higher
* Azure OpenAI resource with deployed models

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-azure-openai`, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your Azure OpenAI credentials (see below).

**Connectors Page:**

1. Go to Settings → Connectors
2. Find "Azure OpenAI" and click **Set Up**
3. Enter your API Key (from Azure Portal → Your OpenAI Resource → Keys and Endpoint)
4. Enter your Endpoint URL, API Version, Deployment ID, and Capabilities
5. Click **Save Settings**

**Configuration via Environment Variables:**

Set the following environment variables:

* `AZURE_OPENAI_API_KEY` - Your Azure OpenAI API key
* `AZURE_OPENAI_ENDPOINT` - Your Azure OpenAI endpoint URL
* `AZURE_OPENAI_API_VERSION` - (Optional) API version, defaults to `2024-02-15-preview`
* `AZURE_OPENAI_DEPLOYMENT_ID` - (Optional) Your Azure OpenAI deployment name
* `AZURE_OPENAI_CAPABILITIES` - (Optional) Comma-separated capabilities: `text_generation`, `image_generation`, `chat_history`, `embedding_generation`, `text_to_speech_conversion`

Environment variables are used as fallbacks when settings are not saved in the database.

== Frequently Asked Questions ==

= What Azure OpenAI models are supported? =

This provider supports any model deployed to your Azure OpenAI resource, including:

* GPT-4, GPT-4-Turbo, GPT-4o, GPT-4o-mini, GPT-4.1
* GPT-3.5-Turbo
* DALL-E 2, DALL-E 3
* text-embedding-ada-002, text-embedding-3-small, text-embedding-3-large
* tts-1, tts-1-hd

Note: You must deploy models in Azure Portal before using them with this provider.

= How do I get Azure OpenAI credentials? =

1. Go to the [Azure Portal](https://portal.azure.com)
2. Create or navigate to your Azure OpenAI resource
3. Go to "Keys and Endpoint" to find your API key and endpoint URL

= What's the difference between Azure OpenAI and OpenAI? =

Azure OpenAI provides the same OpenAI models but hosted on Microsoft Azure infrastructure, with:

* Enterprise security and compliance features
* Private networking options
* Regional deployment choices
* Azure billing integration

= Can I use both OpenAI and Azure OpenAI providers? =

Yes, both providers can be active simultaneously. Each is registered as a separate provider in the WordPress AI Client.

== Screenshots ==

1. Connectors page showing Azure OpenAI settings form integrated with other connectors.
2. Settings page for configuring Azure OpenAI.

== Changelog ==

= 1.5.2 =
* Add missing `outputModalities` (image) for image generation models — fixes model matching when callers request image output
* Apply WPCS array bracket spacing in image generation model

= 1.5.1 =
* Fix double API key masking caused by RC1's centralized REST dispatch handler
* Fix API key silently emptied on save when endpoint URL not yet configured (RC1 key validation)
* Replace script module data filter with connector registry unregister pattern via `wp_connectors_init`
* Updated how-to documentation with RC1 connector registry, `wp_supports_ai()` gate, and key validation changes

= 1.5.0 =
* WordPress 7.0 RC1 compatibility: `ConnectorItem` prop renamed from `icon` to `logo`, `registerConnector` config key renamed from `label` to `name`
* Render function accepts `logo` prop from the Connectors page with cloud SVG fallback
* Script module data filter updated for RC1's `connectors` keyed object format
* Updated how-to documentation with RC1 API changes
* Updated tests for the `logo`/`name` API

= 1.4.2 =
* WordPress 7.0 Beta 6 compatibility: run Azure authentication setup after core connector key binding to preserve `api-key` header authentication
* Updated documentation with Beta 6 hook-order notes

= 1.4.1 =
* Removed GitHub updater integration for WordPress.org compatibility
* Added direct file access protection to src/autoload.php

= 1.4.0 =
* Fixed provider not appearing in WP Connector Registry — changed provider ID from `azure-openai` to `azure_openai` to satisfy the `[a-z0-9_]+` validation rule
* Fixed "Most experiments require a valid AI Connector" warning in AI Experiments plugin
* Updated JS connector slug to `ai_provider/azure_openai`
* Added migration for existing connector settings (seamless upgrade)
* Added Core Trac ticket proposing `_wp_connectors_init()` sanitize provider IDs before registration

= 1.3.0 =
* Added GitHub-based automatic plugin updates — the plugin now self-updates from GitHub releases
* Added release zip verification in GitHub Actions workflows
* Updated @testing-library/react to ^16.3.2 and @wordpress/scripts to ^31.6.0
* Fixed 14 npm audit vulnerabilities via direct updates and overrides

= 1.2.0 =
* Fixed connector settings page on WordPress 7.0 beta 3 — hooks both page variants to cover the renamed connectors page
* Fixed custom connector UI being overwritten by core's auto-registered generic ApiKeyConnector
* Connector slug changed to `ai-provider/azure-openai` to match Beta 3's `{type}/{id}` format
* Updated how-to guide with WordPress 7.0 Beta 3 differences

= 1.1.2 =
* Fixed fatal error on WordPress 7.0 beta 3 caused by the "AI Experiments" plugin shipping an outdated php-ai-client library that overrides the version built into core
* Added version-conflict detection for incompatible php-ai-client libraries
* Added admin notice guiding users to deactivate the conflicting plugin

= 1.1.1 =
* Added environment variable fallback for capabilities (AZURE_OPENAI_CAPABILITIES) — accepts a comma-separated list

= 1.1.0 =
* Connectors page integration (Settings → Connectors) for API key, endpoint, deployment, and capabilities
* wp-scripts build pipeline for JavaScript (ESM script module output)
* Vitest test infrastructure for the Connectors JavaScript
* i18n pipeline (make-pot, update-po, make-mo, make-json, make-php)
* How-to guide for building custom AI providers (docs/how-to-add-ai-provider.md)
* Connector icon changed to Gutenberg cloud icon
* Button labels follow WP core conventions: "Set Up" / "Edit"
* Added __next40pxDefaultSize and __nextHasNoMarginBottom to all form controls
* Authentication class now extends ApiKeyRequestAuthentication (fixes SDK type validation)
* Simplified Settings_Manager with environment variable fallback
* Removed legacy Settings → Azure OpenAI page
* Fixed fatal InvalidArgumentException when SDK validated the authentication object type
* Fixed model discovery reading API key from legacy storage instead of Connector Settings
* Fixed missing webSearch supported option causing "No models found" error
* Fixed multimodal input modalities — now declares image, audio, and document combinations so callers using with_file() find matching models

= 1.0.0 =
* Initial release
* Text generation support (GPT models)
* Image generation support (DALL-E models)
* Embedding generation support (text-embedding models)
* Text-to-speech support (tts-1, tts-1-hd models)
* Settings page with environment variable fallback
* Configurable capabilities per deployment

== Credits ==

This plugin is based on [AI Provider for OpenAI](https://github.com/WordPress/ai-provider-for-openai) by the WordPress AI Team. It adapts the OpenAI provider architecture for Azure OpenAI's API format and authentication requirements.

== Upgrade Notice ==

= 1.4.2 =
Fixes WordPress 7.0 Beta 6 auth hook-order compatibility that could cause Azure requests to use the wrong authentication object.

= 1.4.1 =
Removes GitHub updater integration to comply with WordPress.org plugin guidelines.

= 1.4.0 =
Fixes provider registration with WP Connector Registry. Required if you use AI Experiments or any feature relying on wp_get_connectors().

= 1.3.0 =
Adds automatic updates from GitHub releases. Security dependency fixes.

= 1.2.0 =
Fixes connector settings page on WordPress 7.0 beta 3. Required if you upgraded to beta 3.

= 1.1.2 =
Fixes a fatal error when the AI Experiments plugin is active alongside WordPress 7.0. Deactivate AI Experiments to resolve.

= 1.1.1 =
Capabilities can now be set via the AZURE_OPENAI_CAPABILITIES environment variable.

= 1.1.0 =
Connectors page integration, multimodal input support, and multiple bug fixes. Settings migrated to individual connector options.

= 1.0.0 =
Initial release.

=== AI Provider for Azure OpenAI ===
Contributors: PerS
Tags: ai, azure, openai, gpt, artificial-intelligence, connector
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
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
* Settings page for easy configuration
* Environment variable support for credentials

**Requirements:**

* PHP 7.4 or higher
* When using with WordPress, requires WordPress 7.0 or higher
  * If using an older WordPress release, the wordpress/php-ai-client package must be installed
* Azure OpenAI resource with deployed models

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-azure-openai`, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your Azure OpenAI credentials via Settings → Azure OpenAI, or set environment variables.

**Configuration via Settings Page:**

1. Go to Settings → Azure OpenAI
2. Enter your Endpoint URL (e.g., `https://your-resource.openai.azure.com`)
3. Optionally set the API Version (defaults to `2024-02-15-preview`)
4. Enter your Deployment ID (the name of your Azure OpenAI deployment, e.g., `gpt-4o`)
5. Select the Capabilities your deployment supports (text generation, image generation, embedding generation, text-to-speech)
6. Save settings
7. Set your API Key in Settings → AI Client under the Azure OpenAI provider credentials (from Azure Portal → Your OpenAI Resource → Keys and Endpoint)

**Configuration via Environment Variables:**

Set the following environment variables:

* `AZURE_OPENAI_API_KEY` - Your Azure OpenAI API key
* `AZURE_OPENAI_ENDPOINT` - Your Azure OpenAI endpoint URL
* `AZURE_OPENAI_API_VERSION` - (Optional) API version, defaults to `2024-02-15-preview`
* `AZURE_OPENAI_DEPLOYMENT_ID` - (Optional) Your Azure OpenAI deployment name

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

1. Settings page for configuring Azure OpenAI.

== Changelog ==

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

= 1.0.0 =
Initial release.

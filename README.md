# AI Provider for Azure OpenAI

AI Provider for Azure OpenAI for the WordPress AI Client.

## Description

This plugin provides Azure OpenAI integration for the WordPress AI Client, enabling text and image generation using Azure's hosted OpenAI models.

### Features

- Text generation using GPT-4, GPT-4o, GPT-3.5-Turbo deployments
- Image generation using DALL-E 2 and DALL-E 3 deployments
- Settings page for easy configuration
- Environment variable support for credentials
- Automatic deployment discovery from Azure OpenAI resource

## Requirements

- WordPress 7.0 or higher (with built-in AI Client)
- PHP 7.4 or higher
- Azure OpenAI resource with deployed models

## Installation

1. Download [`ai-provider-for-azure-openai .zip`](https://github.com/soderlind/ai-provider-for-azure-openai/releases/latest/download/ai-provider-for-azure-openai.zip)
2. Upload via  `Plugins → Add New → Upload Plugin`
3. Activate the plugin through the WordPress admin
4. Configure credentials via Settings → Azure OpenAI or environment variables

## Configuration

### Via Settings Page

1. Go to **Settings → Azure OpenAI**
2. Enter your **API Key** (from Azure Portal → Your OpenAI Resource → Keys and Endpoint)
3. Enter your **Endpoint URL** (e.g., `https://your-resource.openai.azure.com`)
4. Optionally set the **API Version** (defaults to `2024-02-15-preview`)
5. Save settings

### Via Environment Variables

Set the following environment variables:

```bash
export AZURE_OPENAI_API_KEY="your-api-key"
export AZURE_OPENAI_ENDPOINT="https://your-resource.openai.azure.com"
export AZURE_OPENAI_API_VERSION="2024-02-15-preview"  # Optional
```

Environment variables are used when settings are not saved in the database.

## Usage

Once configured, the Azure OpenAI provider is automatically registered with the WordPress AI Client and can be used like any other provider:

```php
use WordPress\AiClient\AiClient;

// Get an Azure OpenAI model
$client = AiClient::default();
$model = $client->getModel( 'azure-openai', 'gpt-4o' );

// Generate text
$result = $model->generateText( [
    [ 'role' => 'user', 'content' => 'Hello, how are you?' ]
] );

echo $result->getText();
```

## API Differences from OpenAI

Azure OpenAI uses a different URL structure:

```
{endpoint}/openai/deployments/{deployment}/chat/completions?api-version={version}
```

And uses the `api-key` header instead of `Authorization: Bearer`:

```
api-key: your-api-key
```

The plugin handles these differences automatically.

## Development

### Linting

```bash
composer install
composer lint:php
```

### Testing

```bash
composer test
```

## Credits

This plugin is based on [AI Provider for OpenAI](https://github.com/WordPress/ai-provider-for-openai) by the WordPress AI Team. It adapts the OpenAI provider architecture for Azure OpenAI's API format and authentication requirements.

## License

GPL-2.0-or-later

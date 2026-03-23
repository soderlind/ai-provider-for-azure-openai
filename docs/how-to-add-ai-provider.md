# How to Add a Custom AI Provider to WordPress 7

> Tested with WordPress 7.0-beta6

This guide walks you through building a WordPress plugin that registers a
custom AI provider with the new **AI Client** that ships with WordPress 7.0.
By the end you will have:

1. A provider that appears on the **Settings → Connectors** page.
2. Settings (API key, endpoint, etc.) saved via the REST API.
3. A model that WordPress can use for text generation.

> **Audience:** Plugin developers who are comfortable with PHP and have basic
> JavaScript knowledge. No prior experience with the WP AI APIs is assumed.

> **⚠️ Experimental APIs:** The JavaScript Connectors API is still marked
> experimental in WordPress 7.0. The public names are prefixed with
> `__experimental` (e.g. `__experimentalRegisterConnector`,
> `__experimentalConnectorItem`, `__experimentalDefaultConnectorSettings`).
> This guide aliases them to shorter names on import for readability:
>
> ```js
> import {
>     __experimentalRegisterConnector as registerConnector,
>     __experimentalConnectorItem as ConnectorItem,
>     __experimentalDefaultConnectorSettings as DefaultConnectorSettings,
> } from '@wordpress/connectors';
> ```
>
> The `__experimental` prefix signals that these APIs **may change in a future
> WordPress release** without a deprecation period. Pin your tested WP version
> and watch the [Make/Core blog](https://make.wordpress.org/core/) for updates.

> **🔄 Changes in WordPress 7.0 Beta 3** (compared to Beta 2):
>
> 1. **Connectors page moved** from `connectors.php` to `options-connectors.php`.
>    The page-specific hook changed from `connectors-wp-admin_init` to
>    `options-connectors-wp-admin_init`. Hook **both** to stay compatible.
> 2. **Core auto-registers providers.** Beta 3 reads all registered AI Client
>    providers and creates a default `ApiKeyConnector` for each on the
>    Connectors page. If your plugin provides a custom UI, you must **filter
>    out** your provider from the page's JSON data — otherwise core's generic
>    connector overwrites yours.
> 3. **Slug format changed.** Auto-registered connectors use the slug
>    `{type}/{id}` (e.g. `ai_provider/my_provider`). Your
>    `registerConnector()` call must use the same slug so the store merges
>    correctly.
> 4. **Data filter names changed** to match the new page:
>    `script_module_data_options-connectors-wp-admin` (primary) and
>    `script_module_data_connectors-wp-admin` (fallback).
>
> See [§5c](#5c-register-and-enqueue-the-module),
> [§5d](#5d-write-the-javascript-connector), and
> [§5e](#5e-prevent-core-from-overriding-your-connector-beta-3) for details.

> **🔄 Changes in WordPress 7.0 Beta 6**:
>
> Core now binds connector API keys to providers on `init` priority `20` via
> `_wp_connectors_pass_default_keys_to_ai_client()`. If your provider needs a
> custom authentication object (for example Azure's `api-key` header), register
> your override **after** that runs (e.g. `init` priority `30`).

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Prerequisites](#prerequisites)
- [Step 1 — Scaffold the Plugin](#step-1--scaffold-the-plugin)
- [Step 2 — Create the Provider (PHP)](#step-2--create-the-provider-php)
  - [2a. Provider Metadata](#2a-provider-metadata)
  - [2b. Model Metadata Directory](#2b-model-metadata-directory)
  - [2c. Model Class](#2c-model-class)
  - [2d. Custom Authentication (optional)](#2d-custom-authentication-optional)
- [Step 3 — Register the Provider](#step-3--register-the-provider)
- [Step 4 — Register Connector Settings (PHP)](#step-4--register-connector-settings-php)
- [Step 5 — Build the Connectors Page UI (JS)](#step-5--build-the-connectors-page-ui-js)
  - [5a. Important: Script Modules vs Classic Scripts](#5a-important-script-modules-vs-classic-scripts)
  - [5b. Build the JavaScript with wp-scripts](#5b-build-the-javascript-with-wp-scripts)
  - [5c. Register and Enqueue the Module](#5c-register-and-enqueue-the-module)
  - [5d. Write the JavaScript Connector](#5d-write-the-javascript-connector)
  - [5e. Prevent Core from Overriding Your Connector (Beta 3)](#5e-prevent-core-from-overriding-your-connector-beta-3)
- [Step 6 — Wire Up Authentication](#step-6--wire-up-authentication)
- [Complete File Listing](#complete-file-listing)
- [Testing Your Provider](#testing-your-provider)
  - [Testing the Connectors JavaScript (Vitest)](#testing-the-connectors-javascript-vitest)
- [Gotchas and Tips](#gotchas-and-tips)

---

> **⚠️ Provider ID format:** The Connector Registry (`WP_Connector_Registry`)
> only accepts IDs matching `/^[a-z0-9_]+$/` — **lowercase letters, digits,
> and underscores only**. Hyphens are **not** allowed in provider IDs and will
> cause silent registration failure. Use underscores: `my_ai_provider`, not
> `my-ai-provider`. This restriction applies to:
> - The first argument of `ProviderMetadata` (PHP provider slug)
> - The first argument of `registerConnector()` (JS connector slug — the `{id}` part)
> - The first argument of `setProviderRequestAuthentication()` (auth binding)
> - The `usingProvider()` call in the AI Client API
>
> Note: Text domains and plugin directory names **can** use hyphens — this
> restriction only applies to the provider/connector ID.
>
> This might be fixed if core ticket is accepted: https://core.trac.wordpress.org/ticket/64861 

---

## Architecture Overview

WordPress 7.0 introduces two systems that work together:

```
┌──────────────────────────────────────────────────────┐
│                  WP Admin UI                         │
│   Settings → Connectors page (React, script modules) │
│      ↕ REST API (/wp/v2/settings)                    │
├──────────────────────────────────────────────────────┤
│                  PHP Backend                          │
│   register_setting('connectors', ...)                │
│   AiClient::defaultRegistry()->registerProvider()    │
│   ProviderInterface → Models → HTTP requests         │
└──────────────────────────────────────────────────────┘
```

| Layer               | What it does                                                                 |
| ------------------- | ---------------------------------------------------------------------------- |
| **AI Client SDK**   | PHP library at `wp-includes/php-ai-client/` — defines providers, models, capabilities, and the registry. |
| **Connectors Page** | React-based admin page at `Settings → Connectors` — UI for managing API keys and provider settings.        |

Your plugin bridges both: it **registers a PHP provider** with the AI Client
and **registers a JS connector** with the Connectors page.

---

## Prerequisites

- WordPress 7.0 or later.
- PHP 7.4+.
- Basic familiarity with WordPress plugin development (`add_action`,
  `register_setting`, etc.).

---

## Step 1 — Scaffold the Plugin

Create a new plugin directory and a main file:

```
wp-content/plugins/my-ai-provider/
├── my-ai-provider.php          ← Main plugin file
├── package.json                ← npm scripts (build, test, i18n)
├── webpack.config.js           ← wp-scripts config
├── vitest.config.js            ← Vitest config (alias for @wordpress/connectors)
├── i18n-map.json               ← Source→build map for i18n
├── build/
│   └── connectors.js           ← Compiled output (git-ignored)
├── languages/                  ← Translation files
├── tests/
│   └── js/
│       ├── __mocks__/
│       │   └── @wordpress/
│       │       └── connectors.js  ← Stub for the script-module import
│       ├── setup-globals.js       ← window.wp mock (apiFetch, element, etc.)
│       └── connectors.test.js     ← Vitest tests for the connector UI
└── src/
    ├── autoload.php            ← PSR-4 autoloader
    ├── js/
    │   └── connectors.js       ← Connectors page UI (source)
    ├── Provider/
    │   └── MyProvider.php      ← Provider class
    ├── Models/
    │   └── MyTextGenerationModel.php
    ├── Metadata/
    │   └── MyModelMetadataDirectory.php
    ├── Http/
    │   └── MyRequestAuthentication.php  (optional)
    └── Settings/
        └── ConnectorSettings.php
```

### Main plugin file header

```php
<?php
/**
 * Plugin Name: My AI Provider
 * Description: Custom AI provider for WordPress 7.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 * Text Domain: my-ai-provider
 */

namespace MyAiProvider;

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

define( 'MY_AI_PROVIDER_VERSION', '1.0.0' );
define( 'MY_AI_PROVIDER_FILE', __FILE__ );

require_once __DIR__ . '/src/autoload.php';
```

### Autoloader (`src/autoload.php`)

```php
<?php
spl_autoload_register( static function ( string $class ): void {
    $prefix   = 'MyAiProvider\\';
    $base_dir = __DIR__ . '/';
    $len      = strlen( $prefix );

    if ( strncmp( $class, $prefix, $len ) !== 0 ) {
        return;
    }

    $relative = substr( $class, $len );
    $file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );
```

---

## Step 2 — Create the Provider (PHP)

The AI Client SDK is class-based. You'll extend abstract classes and implement
a handful of factory methods.

### Key SDK Classes

| Class / Interface                              | Purpose                                                     |
| ---------------------------------------------- | ----------------------------------------------------------- |
| `AbstractApiProvider`                          | Base class for cloud-based API providers.                   |
| `ProviderMetadata`                             | Name, slug, type (cloud/server/client), credentials URL.    |
| `ModelMetadataDirectoryInterface`              | Tells the SDK which models the provider offers.             |
| `ModelMetadata`                                | ID, name, capabilities, supported options for a single model.|
| `AbstractOpenAiCompatibleTextGenerationModel`  | Full OpenAI chat/completions implementation you can extend. |
| `RequestAuthenticationInterface`               | How requests are authenticated (API key header, etc.).      |
| `CapabilityEnum`                               | `text_generation`, `image_generation`, `embedding_generation`, `chat_history`, `text_to_speech_conversion`, etc. |
| `ProviderTypeEnum`                             | `cloud`, `server`, `client`.                                |

### 2a. Provider Metadata

Create `src/Provider/MyProvider.php`:

```php
<?php
namespace MyAiProvider\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use MyAiProvider\Metadata\MyModelMetadataDirectory;
use MyAiProvider\Models\MyTextGenerationModel;

class MyProvider extends AbstractApiProvider {

    /**
     * Base URL of your AI service API.
     */
    protected static function baseUrl(): string {
        // Read from your settings or hardcode for a public API.
        return 'https://api.my-ai-service.com/v1';
    }

    /**
     * Create a model instance based on its capabilities.
     *
     * The SDK calls this when it needs a model object.
     */
    protected static function createModel(
        ModelMetadata $model_metadata,
        ProviderMetadata $provider_metadata
    ): ModelInterface {
        // Route to the correct model class based on capability.
        // For now we only support text generation.
        return new MyTextGenerationModel( $model_metadata, $provider_metadata );
    }

    /**
     * Metadata that identifies this provider in the registry.
     */
    protected static function createProviderMetadata(): ProviderMetadata {
        return new ProviderMetadata(
            'my_ai_provider',                // Unique slug (underscores only, no hyphens).
            __( 'My AI Service', 'my-ai-provider' ),  // Display name.
            ProviderTypeEnum::cloud(),       // cloud | server | client.
            'https://my-ai-service.com/keys', // Where users get API keys.
            RequestAuthenticationMethod::apiKey() // Auth method.
        );
    }

    /**
     * How the SDK checks if the provider is reachable.
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface {
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    /**
     * Factory for the model metadata directory.
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
        return new MyModelMetadataDirectory();
    }
}
```

#### What the four `create*()` methods do

| Method                             | Returns                   | Purpose                                                                   |
| ---------------------------------- | ------------------------- | ------------------------------------------------------------------------- |
| `createProviderMetadata()`         | `ProviderMetadata`        | Slug, human name, type, and credentials URL shown to users.               |
| `createModelMetadataDirectory()`   | Directory object           | Lists available models with their capabilities.                           |
| `createProviderAvailability()`     | Availability checker       | Verifies the provider can be reached (e.g. by listing models).            |
| `createModel()`                    | `ModelInterface`           | Instantiates the right model class given its metadata.                    |

### 2b. Model Metadata Directory

The directory tells the SDK which models your provider offers and what they
can do. Create `src/Metadata/MyModelMetadataDirectory.php`:

```php
<?php
namespace MyAiProvider\Metadata;

use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;

class MyModelMetadataDirectory implements ModelMetadataDirectoryInterface {

    /** @var list<ModelMetadata>|null */
    private ?array $cached = null;

    public function listModelMetadata(): array {
        if ( null !== $this->cached ) {
            return $this->cached;
        }

        // Define the models your service provides.
        $this->cached = [
            new ModelMetadata(
                'my-model-large',             // Model ID.
                'My Model Large',             // Display name.
                [                             // Capabilities.
                    CapabilityEnum::textGeneration(),
                    CapabilityEnum::chatHistory(),
                ],
                $this->textGenerationOptions() // Supported options.
            ),
        ];

        return $this->cached;
    }

    public function hasModelMetadata( string $modelId ): bool {
        foreach ( $this->listModelMetadata() as $meta ) {
            if ( $meta->getId() === $modelId ) {
                return true;
            }
        }
        return false;
    }

    public function getModelMetadata( string $modelId ): ModelMetadata {
        foreach ( $this->listModelMetadata() as $meta ) {
            if ( $meta->getId() === $modelId ) {
                return $meta;
            }
        }
        throw new InvalidArgumentException( "Unknown model: $modelId" );
    }

    /**
     * Supported options for text generation models.
     *
     * The SDK uses these to match models to caller requirements. Every
     * option a caller sets (temperature, webSearch, functionDeclarations…)
     * becomes a RequiredOption. If your model doesn't declare it here,
     * areMetBy() silently rejects the model.
     *
     * Including outputModalities is critical — without it the SDK's
     * PromptBuilder rejects the model when callers request text output.
     *
     * Including multimodal inputModalities is important — if a caller
     * attaches images or documents via with_file(), the SDK adds
     * inputModalities=[text, image] (or similar) to the requirements.
     * Without declaring those combinations here, the model is rejected
     * with "No models found that support text_generation for this prompt."
     *
     * Pass null as the second argument to accept any value.
     */
    private function textGenerationOptions(): array {
        return [
            new SupportedOption(
                OptionEnum::inputModalities(),
                [
                    [ ModalityEnum::text() ],
                    [ ModalityEnum::text(), ModalityEnum::image() ],
                    [ ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::audio() ],
                    [ ModalityEnum::text(), ModalityEnum::document() ],
                    [ ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::document() ],
                ]
            ),
            new SupportedOption(
                OptionEnum::outputModalities(),
                [ [ ModalityEnum::text() ] ]
            ),
            new SupportedOption( OptionEnum::systemInstruction() ),
            new SupportedOption( OptionEnum::temperature() ),
            new SupportedOption( OptionEnum::maxTokens() ),
            new SupportedOption( OptionEnum::topP() ),
            new SupportedOption( OptionEnum::stopSequences() ),
            new SupportedOption( OptionEnum::presencePenalty() ),
            new SupportedOption( OptionEnum::frequencyPenalty() ),
            new SupportedOption( OptionEnum::logprobs() ),
            new SupportedOption( OptionEnum::topLogprobs() ),
            new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
            new SupportedOption( OptionEnum::outputSchema() ),
            new SupportedOption( OptionEnum::functionDeclarations() ),
            new SupportedOption( OptionEnum::webSearch() ),
            new SupportedOption( OptionEnum::customOptions() ),
        ];
    }
}
```

#### Available Capabilities

```php
CapabilityEnum::textGeneration()          // GPT-style text
CapabilityEnum::imageGeneration()         // DALL-E-style images
CapabilityEnum::embeddingGeneration()     // Vector embeddings
CapabilityEnum::textToSpeechConversion()  // TTS
CapabilityEnum::chatHistory()             // Multi-turn conversation
CapabilityEnum::speechGeneration()
CapabilityEnum::musicGeneration()
CapabilityEnum::videoGeneration()
```

### 2c. Model Class

If your API is **OpenAI-compatible** (accepts `chat/completions` requests in
the same format), extend the provided abstract class. Create
`src/Models/MyTextGenerationModel.php`:

```php
<?php
namespace MyAiProvider\Models;

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * If your API uses the same request/response format as OpenAI's
 * chat/completions endpoint, this is the only code you need.
 *
 * The base class handles:
 * - Parameter building (temperature, max_tokens, etc.)
 * - Message formatting (system/user/assistant roles)
 * - Response parsing
 * - Tool/function calls
 * - Streaming (streamGenerateTextResult)
 */
class MyTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
    // Nothing to override! The base class does everything.
    // The request URL comes from MyProvider::url('chat/completions').
}
```

If your API uses a **different request format**, extend
`AbstractApiBasedModel` directly and implement `generateTextResult()` yourself:

```php
<?php
namespace MyAiProvider\Models;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

class MyCustomModel extends AbstractApiBasedModel implements TextGenerationModelInterface {

    public function generateTextResult( array $prompt ): GenerativeAiResult {
        $url  = 'https://api.my-service.com/v1/generate';
        $data = [ 'prompt' => $prompt, 'max_tokens' => 1024 ];

        $request = new Request(
            HttpMethodEnum::POST(),
            $url,
            [ 'Content-Type' => 'application/json' ],
            $data,
            $this->getRequestOptions()
        );

        $request  = $this->getRequestAuthentication()->authenticateRequest( $request );
        $response = $this->getHttpTransporter()->send( $request );

        // Parse your API's response into a GenerativeAiResult.
        // ...
    }
}
```

### 2d. Custom Authentication (optional)

By default the SDK sends `Authorization: Bearer <key>`. If your API needs a
different header (e.g. Azure uses `api-key`), you must **extend the SDK's
authentication class** that matches your provider's declared
`RequestAuthenticationMethod`.

> **⚠️ Important:** The `ProviderRegistry` validates that your authentication
> object is an `instanceof` the class returned by
> `RequestAuthenticationMethod::…()->getImplementationClass()`. If your
> provider declares `RequestAuthenticationMethod::apiKey()`, your custom class
> **must extend `ApiKeyRequestAuthentication`** — implementing the interface
> alone will throw an `InvalidArgumentException` at runtime.

For providers using `RequestAuthenticationMethod::apiKey()`, extend
`ApiKeyRequestAuthentication` and override only `authenticateRequest()`:

```php
<?php
namespace MyAiProvider\Http;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

class MyRequestAuthentication extends ApiKeyRequestAuthentication {

    /**
     * Replace the default "Authorization: Bearer" header with a custom one.
     *
     * The parent class provides the constructor, getApiKey(), toArray(),
     * fromArray(), and getJsonSchema() — no need to redefine them.
     */
    public function authenticateRequest( Request $request ): Request {
        return $request->withHeader( 'X-My-Api-Key', $this->getApiKey() );
    }
}
```

If your provider uses a completely different authentication method (not API
key), implement `RequestAuthenticationInterface` directly instead:

```php
<?php
namespace MyAiProvider\Http;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;

class MyCustomAuthentication implements RequestAuthenticationInterface {

    private string $token;

    public function __construct( string $token ) {
        $this->token = $token;
    }

    public function authenticateRequest( Request $request ): Request {
        return $request->withHeader( 'X-Custom-Token', $this->token );
    }

    public static function getJsonSchema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'token' => [
                    'type'  => 'string',
                    'title' => 'Token',
                ],
            ],
            'required' => [ 'token' ],
        ];
    }
}
```

---

## Step 3 — Register the Provider

Back in your main plugin file (`my-ai-provider.php`), register the provider
with the AI Client registry:

```php
use WordPress\AiClient\AiClient;
use MyAiProvider\Provider\MyProvider;

/**
 * Register the provider early — the AI Client needs it during init.
 */
function register_provider(): void {
    if ( ! class_exists( AiClient::class ) ) {
        return; // AI Client not available (shouldn't happen on WP 7).
    }

    $registry = AiClient::defaultRegistry();

    if ( ! $registry->hasProvider( MyProvider::class ) ) {
        $registry->registerProvider( MyProvider::class );
    }
}
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );
```

---

## Step 4 — Register Connector Settings (PHP)

Settings registered under the `'connectors'` group automatically appear in
the REST API at `GET /wp/v2/settings` and can be read/written from the
Connectors page JS.

Create `src/Settings/ConnectorSettings.php`:

```php
<?php
namespace MyAiProvider\Settings;

class ConnectorSettings {

    // Option names — prefixed to avoid collisions.
    const OPTION_API_KEY  = 'connectors_ai_my_ai_provider_api_key';
    const OPTION_ENDPOINT = 'connectors_ai_my_ai_provider_endpoint';

    /**
     * Register settings. Call on `init`.
     */
    public static function register(): void {

        // ── API Key ─────────────────────────────────────────────
        register_setting(
            'connectors',                  // ← Must be 'connectors'
            self::OPTION_API_KEY,
            [
                'type'              => 'string',
                'label'             => __( 'My AI API Key', 'my-ai-provider' ),
                'description'       => __( 'API key for My AI Service.', 'my-ai-provider' ),
                'default'           => '',
                'show_in_rest'      => true,  // ← Required for the JS UI
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );
        // Mask the key so it's not returned in full via REST.
        add_filter(
            'option_' . self::OPTION_API_KEY,
            [ __CLASS__, 'mask_api_key' ]
        );

        // ── Endpoint URL ────────────────────────────────────────
        register_setting(
            'connectors',
            self::OPTION_ENDPOINT,
            [
                'type'              => 'string',
                'label'             => __( 'API Endpoint', 'my-ai-provider' ),
                'default'           => '',
                'show_in_rest'      => true,
                'sanitize_callback' => 'esc_url_raw',
            ]
        );
    }

    /**
     * Mask an API key: show bullet characters + last 4 chars.
     */
    public static function mask_api_key( $key ): string {
        if ( ! is_string( $key ) || strlen( $key ) <= 4 ) {
            return is_string( $key ) ? $key : '';
        }
        return str_repeat( "\u{2022}", min( strlen( $key ) - 4, 16 ) )
             . substr( $key, -4 );
    }

    /**
     * Read the real (unmasked) API key from the database.
     */
    public static function get_real_api_key(): string {
        remove_filter( 'option_' . self::OPTION_API_KEY, [ __CLASS__, 'mask_api_key' ] );
        $value = get_option( self::OPTION_API_KEY, '' );
        add_filter( 'option_' . self::OPTION_API_KEY, [ __CLASS__, 'mask_api_key' ] );
        return (string) $value;
    }
}
```

Register it from your main plugin file:

```php
add_action( 'init', [ \MyAiProvider\Settings\ConnectorSettings::class, 'register' ] );
```

### Key rules for connector settings

1. **Group must be `'connectors'`** — this is what makes them appear on the
   Connectors page and in the REST settings endpoint.
2. **`'show_in_rest' => true`** — required so the JS UI can read/write them.
3. **Mask API keys** — add an `option_` filter that replaces all but the last
   4 characters with bullets, so the full key is never sent to the browser.

---

## Step 5 — Build the Connectors Page UI (JS)

### 5a. Important: Script Modules vs Classic Scripts

> **This is the single most common pitfall when building connectors.**

WordPress 7.0 uses two script systems side by side:

| System              | Examples                                                     | How to use in JS              |
| ------------------- | ------------------------------------------------------------ | ----------------------------- |
| **Script Modules**  | `@wordpress/connectors`, `@wordpress/boot`, `@wordpress/a11y` | `import { ... } from '...'`  |
| **Classic Scripts**  | `@wordpress/api-fetch`, `@wordpress/element`, `@wordpress/i18n`, `@wordpress/components` | `window.wp.apiFetch`, etc.   |

**Only `@wordpress/connectors` (and a few others like `@wordpress/a11y`,
`@wordpress/boot`) are registered as script modules in WP 7.0.**

Packages like `api-fetch`, `element`, `i18n`, and `components` are loaded as
traditional enqueued scripts and exposed as `window.wp.*` globals.

**If you declare a classic-script package as a script module dependency, your
module will silently fail to load.** WP's script module system drops any
module whose dependencies aren't registered — without any visible error in the
browser console.

### 5b. Build the JavaScript with wp-scripts

Use `@wordpress/scripts` to compile your source JS into the `build/` directory.

**`package.json`:**

```json
{
    "name": "my-ai-provider",
    "private": true,
    "scripts": {
        "build": "wp-scripts build",
        "start": "wp-scripts start",
        "test": "vitest run",
        "test:watch": "vitest"
    },
    "devDependencies": {
        "@testing-library/react": "^16.3.0",
        "@wordpress/scripts": "^31.5.0",
        "jsdom": "^26.1.0",
        "react": "^18.3.1",
        "react-dom": "^18.3.1",
        "vitest": "^4.0.18"
    }
}
```

> **Note:** `react` and `react-dom` must be v18 to satisfy the `@wordpress/scripts`
> peer dependency.  `jsdom` provides the DOM environment for Vitest tests.

**`webpack.config.js`** (ESM output for script modules):

```js
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

// Remove DependencyExtractionWebpackPlugin — this plugin outputs
// an ESM script-module, so the classic-script dependency map is not used.
const plugins = defaultConfig.plugins.filter(
    ( plugin ) =>
        plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
);

module.exports = {
    ...defaultConfig,
    entry: {
        connectors: path.resolve( process.cwd(), 'src/js', 'connectors.js' ),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve( process.cwd(), 'build' ),
        module: true,
        chunkFormat: 'module',
        library: { type: 'module' },
    },
    experiments: { ...defaultConfig.experiments, outputModule: true },
    externalsType: 'module',
    externals: {
        '@wordpress/connectors': '@wordpress/connectors',
    },
    plugins,
};
```

Run `npm run build` to produce `build/connectors.js`.

### 5c. Register and Enqueue the Module

In your main plugin file:

```php
/**
 * Register the connector JS module.
 *
 * IMPORTANT: Only list actual script modules as dependencies.
 * Classic-script packages (api-fetch, element, i18n, components)
 * must be accessed via window.wp.* in the JS file.
 */
function register_connector_module(): void {
    wp_register_script_module(
        'my-ai-provider/connectors',
        plugins_url( 'build/connectors.js', MY_AI_PROVIDER_FILE ),
        [
            [
                'id'     => '@wordpress/connectors',  // ← The only script module dep.
                'import' => 'dynamic',
            ],
            // ⛔ Do NOT add @wordpress/api-fetch, @wordpress/element, etc. here!
        ],
        MY_AI_PROVIDER_VERSION
    );
}
add_action( 'init', __NAMESPACE__ . '\\register_connector_module' );

/**
 * Enqueue the module when the Connectors page renders.
 *
 * Beta 2 uses 'connectors-wp-admin_init'.
 * Beta 3 moved the page to options-connectors.php, so the hook became
 * 'options-connectors-wp-admin_init'. Hook both so your plugin works
 * regardless of which beta (or final release) the site runs.
 */
function enqueue_connector_module(): void {
    wp_enqueue_script_module( 'my-ai-provider/connectors' );
}
add_action( 'options-connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );
add_action( 'connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );
```

### 5d. Write the JavaScript Connector

Create `src/js/connectors.js`:

```javascript
/**
 * My AI Provider — Connector for the WP 7 Connectors page.
 */

// ── Script module import (the only real ES module) ──────────────
import {
    __experimentalRegisterConnector as registerConnector,
    __experimentalConnectorItem as ConnectorItem,
    __experimentalDefaultConnectorSettings as DefaultConnectorSettings,
} from '@wordpress/connectors';

// ── Classic scripts — accessed via window globals ───────────────
const apiFetch = window.wp.apiFetch;
const { useState, useEffect, useCallback, createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const { Button, TextControl } = window.wp.components;

const el = createElement;

// Option names must match your PHP register_setting() calls.
const API_KEY_OPTION  = 'connectors_ai_my_ai_provider_api_key';
const ENDPOINT_OPTION = 'connectors_ai_my_ai_provider_endpoint';

/**
 * Hook: load & save settings via the WP REST Settings endpoint.
 */
function useMySettings() {
    const [ isLoading, setIsLoading ] = useState( true );
    const [ apiKey, setApiKey ]       = useState( '' );
    const [ endpoint, setEndpoint ]   = useState( '' );

    const isConnected = ! isLoading && apiKey !== '';

    // Load settings on mount.
    const loadSettings = useCallback( async () => {
        try {
            const data = await apiFetch( {
                path: `/wp/v2/settings?_fields=${ API_KEY_OPTION },${ ENDPOINT_OPTION }`,
            } );
            setApiKey( data[ API_KEY_OPTION ] || '' );
            setEndpoint( data[ ENDPOINT_OPTION ] || '' );
        } catch {
            // Settings might not be accessible.
        } finally {
            setIsLoading( false );
        }
    }, [] );

    useEffect( () => { loadSettings(); }, [ loadSettings ] );

    // Save the API key.
    const saveApiKey = useCallback( async ( newKey ) => {
        const result = await apiFetch( {
            path: `/wp/v2/settings?_fields=${ API_KEY_OPTION }`,
            method: 'POST',
            data: { [ API_KEY_OPTION ]: newKey },
        } );
        const returned = result[ API_KEY_OPTION ] || '';
        // If the server returned the same masked value, the save failed.
        if ( returned === apiKey && newKey !== '' ) {
            throw new Error( __( 'Could not save the API key.', 'my-ai-provider' ) );
        }
        setApiKey( returned );
    }, [ apiKey ] );

    // Remove the API key.
    const removeApiKey = useCallback( async () => {
        await apiFetch( {
            path: `/wp/v2/settings?_fields=${ API_KEY_OPTION }`,
            method: 'POST',
            data: { [ API_KEY_OPTION ]: '' },
        } );
        setApiKey( '' );
    }, [] );

    // Save extra settings (endpoint, etc.).
    const saveEndpoint = useCallback( async ( url ) => {
        await apiFetch( {
            path: '/wp/v2/settings',
            method: 'POST',
            data: { [ ENDPOINT_OPTION ]: url },
        } );
        setEndpoint( url );
    }, [] );

    return {
        isLoading, isConnected, apiKey, endpoint,
        setEndpoint, saveApiKey, removeApiKey, saveEndpoint,
    };
}

/**
 * The render component passed to registerConnector().
 *
 * Receives `slug`, `label`, and `description` props from the Connectors page.
 */
function MyConnector( { slug, label, description } ) {
    const {
        isLoading, isConnected, apiKey, endpoint,
        setEndpoint, saveApiKey, removeApiKey, saveEndpoint,
    } = useMySettings();

    const [ isExpanded, setIsExpanded ] = useState( false );

    // Loading state.
    if ( isLoading ) {
        return el( ConnectorItem, {
            icon: el( MyIcon ),
            name: label,
            description,
            actionArea: el( 'span', { className: 'spinner is-active' } ),
        } );
    }

    // Action button — follow WP core Connectors page conventions.
    const buttonLabel = isConnected
        ? __( 'Edit', 'my-ai-provider' )
        : __( 'Set Up', 'my-ai-provider' );

    const actionButton = el( Button, {
        variant: isConnected ? 'tertiary' : 'secondary',
        size: isConnected ? undefined : 'compact',
        onClick: () => setIsExpanded( ! isExpanded ),
        'aria-expanded': isExpanded,
    }, buttonLabel );

    // Settings panel (shown when expanded).
    const settingsPanel = isExpanded && el( 'div', null,
        el( 'h3', null, __( 'API Key', 'my-ai-provider' ) ),
        el( DefaultConnectorSettings, {
            key: isConnected ? 'connected' : 'disconnected',
            onSave: saveApiKey,
            onRemove: removeApiKey,
            initialValue: apiKey,
            readOnly: isConnected,
            helpUrl: 'https://my-ai-service.com/keys',
            helpLabel: __( 'Get API key', 'my-ai-provider' ),
        } ),
        el( 'hr' ),
        el( TextControl, {
            label: __( 'API Endpoint', 'my-ai-provider' ),
            value: endpoint,
            onChange: setEndpoint,
            placeholder: 'https://api.my-ai-service.com/v1',
        } ),
        el( Button, {
            variant: 'primary',
            __next40pxDefaultSize: true,
            onClick: () => saveEndpoint( endpoint ),
        }, __( 'Save Settings', 'my-ai-provider' ) ),
    );

    return el( ConnectorItem, {
        icon: el( MyIcon ),
        name: label,
        description,
        actionArea: actionButton,
    }, settingsPanel );
}

/**
 * Provider icon (40 × 40).
 *
 * Use a Gutenberg icon (e.g. the cloud icon) for a consistent look,
 * or provide your own SVG at 40 × 40 with viewBox '0 0 24 24'.
 */
function MyIcon() {
    return el( 'svg', {
        width: 40, height: 40, viewBox: '0 0 24 24',
        xmlns: 'http://www.w3.org/2000/svg',
        'aria-hidden': 'true',
    },
        el( 'path', {
            d: 'M17.3 10.1c0-2.5-2.1-4.4-4.8-4.4-2.2 0-4.1 1.4-4.6 3.3h-.2C5.7 9 4 10.7 4 12.8c0 2.1 1.7 3.8 3.7 3.8h9c1.8 0 3.2-1.5 3.2-3.3.1-1.6-1.1-2.9-2.6-3.2zm-.5 5.1h-9c-1.2 0-2.2-1.1-2.2-2.3s1-2.4 2.2-2.4h1.3l.3-1.1c.4-1.3 1.7-2.2 3.2-2.2 1.8 0 3.3 1.3 3.3 2.9v1.3l1.3.2c.8.1 1.4.9 1.4 1.8-.1 1-.9 1.8-1.8 1.8z',
        } ),
    );
}

// ── Register ────────────────────────────────────────────────────
// In Beta 3, core auto-registers providers with slug '{type}/{id}'.
// Use the same format so your custom connector replaces the default one.
// The 'type' is derived from your ProviderTypeEnum (e.g. 'ai-provider')
// and the 'id' from your Provider class's slug.
registerConnector( 'ai_provider/my_ai_provider', {
    label: __( 'My AI Service', 'my-ai-provider' ),
    description: __( 'Text generation with My AI Service.', 'my-ai-provider' ),
    render: MyConnector,
} );
```

> **Note on the slug format:** In Beta 2 a simple slug like `'my_ai_provider'`
> was sufficient. Beta 3 changed the internal store to use `{type}/{id}`
> slugs (e.g. `ai_provider/my_ai_provider`). If your slug doesn't match,
> the Redux store treats your connector and the auto-registered one as two
> separate entries, and you'll end up with duplicates — or worse, the default
> generic connector wins.

### Understanding the key JS APIs

| API                                                       | What it does                                                                                          |
| --------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `registerConnector( slug, { label, description, render })` | Adds your connector to the Connectors page. The `render` function receives `slug`, `label`, `description` as props. |
| `ConnectorItem`                                           | UI component for a connector row. Props: `icon`, `name`, `description`, `actionArea`, `children` (expanded panel). |
| `DefaultConnectorSettings`                                | Reusable API-key input. Props: `onSave`, `onRemove`, `initialValue`, `readOnly`, `helpUrl`, `helpLabel`. |

### 5e. Prevent Core from Overriding Your Connector (Beta 3)

Starting in Beta 3, WordPress reads every registered AI Client provider and
pre-populates the Connectors page with a **generic `ApiKeyConnector`** for
each. This data is embedded in a `<script id="wp-script-module-data-…">` tag
as JSON.

If your plugin registers a custom connector UI, the core-generated entry
competes with yours in the Redux store. To prevent this, filter the JSON data
and remove your provider before the page renders:

```php
/**
 * Remove our provider from the auto-generated connector data
 * so our custom JS connector is the only one registered.
 *
 * Hook both filter names to cover both page variants.
 */
function filter_connector_script_data( array $data ): array {
    if ( isset( $data['defaultConnectors'] ) && is_array( $data['defaultConnectors'] ) ) {
        $data['defaultConnectors'] = array_values(
            array_filter(
                $data['defaultConnectors'],
                fn( $c ) => ( $c['id'] ?? '' ) !== 'my_ai_provider'
            )
        );
    }
    return $data;
}
add_filter(
    'script_module_data_options-connectors-wp-admin',
    __NAMESPACE__ . '\\filter_connector_script_data'
);
add_filter(
    'script_module_data_connectors-wp-admin',
    __NAMESPACE__ . '\\filter_connector_script_data'
);
```

> **Why two filters?** The filter name derives from the page file. Beta 3's
> primary page is `options-connectors/page-wp-admin.php` → filter
> `script_module_data_options-connectors-wp-admin`. The fallback page
> `connectors/page-wp-admin.php` uses `script_module_data_connectors-wp-admin`.
> Hooking both keeps you safe across beta versions.

---

## Step 6 — Wire Up Authentication

After settings are registered, you need to pass the stored API key to the
AI Client so it can authenticate requests. Add this to your main plugin file:

```php
use MyAiProvider\Settings\ConnectorSettings;

/**
 * Configure authentication after WP loads credentials.
 */
function setup_authentication(): void {
    if ( ! class_exists( AiClient::class ) ) {
        return;
    }

    $api_key = ConnectorSettings::get_real_api_key();

    // Optionally fall back to an environment variable.
    if ( empty( $api_key ) ) {
        $env_key = getenv( 'MY_AI_API_KEY' );
        if ( false !== $env_key && '' !== $env_key ) {
            $api_key = $env_key;
        }
    }

    if ( ! empty( $api_key ) ) {
        $registry = AiClient::defaultRegistry();

        // Option A: Use the default Bearer token authentication.
        // The SDK does this automatically if you don't call
        // setProviderRequestAuthentication(). Just pass the key
        // to the credentials store and you're done.

        // Option B: Use a custom authentication class.
        $registry->setProviderRequestAuthentication(
            'my_ai_provider',  // Must match the provider slug (underscores, no hyphens).
            new \MyAiProvider\Http\MyRequestAuthentication( $api_key )
        );
    }
}
// Run after core connector key binding (priority 20).
add_action( 'init', __NAMESPACE__ . '\\setup_authentication', 30 );
```

---

## Complete File Listing

Here's everything wired together in `my-ai-provider.php`:

```php
<?php
/**
 * Plugin Name: My AI Provider
 * Description: Custom AI provider for WordPress 7.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 * Text Domain: my-ai-provider
 */

namespace MyAiProvider;

use WordPress\AiClient\AiClient;
use MyAiProvider\Provider\MyProvider;
use MyAiProvider\Settings\ConnectorSettings;

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

define( 'MY_AI_PROVIDER_VERSION', '1.0.0' );
define( 'MY_AI_PROVIDER_FILE', __FILE__ );

require_once __DIR__ . '/src/autoload.php';

// 1. Register the provider with the AI Client.
function register_provider(): void {
    if ( ! class_exists( AiClient::class ) ) {
        return;
    }
    $registry = AiClient::defaultRegistry();
    if ( ! $registry->hasProvider( MyProvider::class ) ) {
        $registry->registerProvider( MyProvider::class );
    }
}
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

// 2. Set up authentication.
function setup_authentication(): void {
    if ( ! class_exists( AiClient::class ) ) {
        return;
    }
    $api_key = ConnectorSettings::get_real_api_key();
    if ( empty( $api_key ) ) {
        $env = getenv( 'MY_AI_API_KEY' );
        if ( false !== $env && '' !== $env ) {
            $api_key = $env;
        }
    }
    if ( ! empty( $api_key ) ) {
        AiClient::defaultRegistry()->setProviderRequestAuthentication(
            'my_ai_provider',
            new \MyAiProvider\Http\MyRequestAuthentication( $api_key )
        );
    }
}
add_action( 'init', __NAMESPACE__ . '\\setup_authentication', 30 );

// 3. Register connector settings.
add_action( 'init', [ ConnectorSettings::class, 'register' ] );

// 4. Register the connector JS module.
function register_connector_module(): void {
    wp_register_script_module(
        'my-ai-provider/connectors',
        plugins_url( 'build/connectors.js', MY_AI_PROVIDER_FILE ),
        [
            [
                'id'     => '@wordpress/connectors',
                'import' => 'dynamic',
            ],
        ],
        MY_AI_PROVIDER_VERSION
    );
}
add_action( 'init', __NAMESPACE__ . '\\register_connector_module' );

// 5. Enqueue on the Connectors page only (hook both page variants).
function enqueue_connector_module(): void {
    wp_enqueue_script_module( 'my-ai-provider/connectors' );
}
add_action( 'options-connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );
add_action( 'connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );

// 6. (Beta 3) Filter out our provider from core's auto-generated connectors.
function filter_connector_script_data( array $data ): array {
    if ( isset( $data['defaultConnectors'] ) && is_array( $data['defaultConnectors'] ) ) {
        $data['defaultConnectors'] = array_values(
            array_filter(
                $data['defaultConnectors'],
                fn( $c ) => ( $c['id'] ?? '' ) !== 'my_ai_provider'
            )
        );
    }
    return $data;
}
add_filter(
    'script_module_data_options-connectors-wp-admin',
    __NAMESPACE__ . '\\filter_connector_script_data'
);
add_filter(
    'script_module_data_connectors-wp-admin',
    __NAMESPACE__ . '\\filter_connector_script_data'
);
```

---

## Testing Your Provider

### Verify it appears on the Connectors page

1. Activate the plugin.
2. Go to **Settings → Connectors**.
3. Your provider should appear in the list with a "Set Up" button.

### Test the AI Client integration

```php
// In a custom page, WP-CLI command, or test:
use WordPress\AiClient\AiClient;

$result = AiClient::prompt( 'Explain gravity in one sentence.' )
    ->usingProvider( 'my_ai_provider' )   // Your provider slug (underscores only).
    ->usingModel( 'my-model-large' )      // Your model ID.
    ->generateTextResult();

echo $result->getText();
```

### Testing the Connectors JavaScript (Vitest)

The connector module imports `@wordpress/connectors` — a WP 7.0 script module
that does not exist as an npm package.  To test it with Vitest you need:

1. **A mock module** at `tests/js/__mocks__/@wordpress/connectors.js`
2. **A Vite alias** so the import resolves at transform time
3. **`window.wp` globals** set up before the module loads

#### 1. Mock module (`tests/js/__mocks__/@wordpress/connectors.js`)

```js
export const __experimentalRegisterConnector = vi.fn();
export const __experimentalConnectorItem = ( { children } ) => children ?? null;
export const __experimentalDefaultConnectorSettings = ( { children } ) => children ?? null;
```

#### 2. Vitest config (`vitest.config.js`)

```js
import { defineConfig } from 'vitest/config';
import path from 'path';

export default defineConfig( {
    resolve: {
        alias: {
            '@wordpress/connectors': path.resolve(
                __dirname,
                'tests/js/__mocks__/@wordpress/connectors.js'
            ),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        include: [ 'tests/js/**/*.test.js' ],
        setupFiles: [ 'tests/js/setup-globals.js' ],
    },
} );
```

The `resolve.alias` is essential — without it Vite's import-analysis plugin
tries to resolve the bare specifier `@wordpress/connectors` before `vi.mock()`
runs and the build fails.

#### 3. Global setup (`tests/js/setup-globals.js`)

Provide `window.wp` mocks for the classic-script packages the connector uses:

```js
import React from 'react';

window.wp = {
    apiFetch: vi.fn( () => Promise.resolve( {} ) ),
    element: {
        useState: React.useState,
        useEffect: React.useEffect,
        useCallback: React.useCallback,
        createElement: React.createElement,
    },
    i18n: { __: vi.fn( ( str ) => str ) },
    components: {
        Button( { children, ...props } ) {
            return React.createElement( 'button', {
                'data-variant': props.variant,
                'data-size': props.size,
                'data-next40px': props.__next40pxDefaultSize || undefined,
                disabled: props.disabled,
                onClick: props.onClick,
            }, children );
        },
        TextControl( props ) {
            return React.createElement( 'div', null,
                React.createElement( 'label', null, props.label ),
                React.createElement( 'input', {
                    type: 'text', value: props.value || '',
                    onChange: ( e ) => props.onChange?.( e.target.value ),
                } )
            );
        },
        CheckboxControl( props ) {
            return React.createElement( 'label', null,
                React.createElement( 'input', {
                    type: 'checkbox', checked: props.checked || false,
                    onChange: () => props.onChange?.( ! props.checked ),
                } ),
                props.label
            );
        },
    },
};
```

#### 4. Key testing pattern — load the module once

Vite caches modules, so `import('../../src/js/connectors.js')` only executes
the top-level `registerConnector()` call the **first time**.  Use `beforeAll`
to load the module and save the registered config, then only reset `apiFetch`
between tests:

```js
import { __experimentalRegisterConnector as mockRegisterConnector }
    from '@wordpress/connectors';

let registeredConfig;

describe( 'My Connector', () => {
    beforeAll( async () => {
        window.wp.apiFetch.mockResolvedValue( {} );
        await import( '../../src/js/connectors.js' );
        registeredConfig = mockRegisterConnector.mock.calls[ 0 ][ 1 ];
    } );

    beforeEach( () => {
        window.wp.apiFetch.mockReset();
        window.wp.apiFetch.mockResolvedValue( {} );
    } );

    it( 'should register with expected slug', () => {
        expect( mockRegisterConnector.mock.calls[ 0 ][ 0 ] ).toBe( 'ai_provider/my_ai_provider' );
    } );
} );
```

Run `npm run test` (one-off) or `npm run test:watch` (interactive mode).

### Debugging tips

- **Provider not appearing on Connectors page?** First, check that your
  provider ID uses only lowercase letters, digits, and underscores — **no
  hyphens**. `WP_Connector_Registry::register()` silently rejects IDs that
  don't match `/^[a-z0-9_]+$/`, so `my-provider` will fail while
  `my_provider` works. If the ID is correct, check the browser's Network
  tab for the script module import map (`<script type="importmap">`). Your
  module should be listed. If it's missing, you likely have an unregistered
  dependency — see [§5a](#5a-important-script-modules-vs-classic-scripts).
  Also verify you're hooking the correct page action — Beta 3 uses
  `options-connectors-wp-admin_init`, not `connectors-wp-admin_init`.

- **Custom UI replaced by a generic API-key input?** Core's auto-registration
  is overwriting your connector. Add the `script_module_data_*` filter
  described in [§5e](#5e-prevent-core-from-overriding-your-connector-beta-3).

- **Settings not saving?** Make sure your `register_setting()` calls use
  `'connectors'` as the group and `'show_in_rest' => true`.

- **API requests failing?** Check that `setup_authentication()` runs after
    core connector key binding in WP 7.0 Beta 6 (use `init` priority 30).

- **"No models found that support text_generation for this prompt"?** This
  means the SDK's `ModelRequirements::areMetBy()` rejected every model. Common
  causes:
  1. Your `listModelMetadata()` returns an empty array (check API key source).
  2. A caller used `->usingWebSearch()`, `->usingFunctionDeclarations()`, or
     another option that your model doesn't declare in its `SupportedOption`
     list. Every option the caller sets becomes a `RequiredOption` — if your
     model doesn't advertise it, matching fails silently.
  3. The `outputModalities` option is missing. `PromptBuilder::generateTextResult()`
     always adds `outputModalities=[text]` before model lookup.
  4. A caller attached an image or document via `->with_file()`, which adds
     `inputModalities=[text, image]` (or `[text, document]`) to the
     requirements. If your model only declares `inputModalities=[[text]]`,
     the match fails. Declare all modality combinations your model supports
     (see the `textGenerationOptions()` example in Step 2b).

  **Tip:** Declare all options your model can handle — pass `null` as the
  second argument to `SupportedOption` to accept any value.

---

## Gotchas and Tips

### Script module dependencies — the silent killer

The WP 7 Script Modules API (`wp_register_script_module`) will **silently
drop your module** if any dependency is not registered as a script module.
There is no console error — the `<script type="module">` tag simply never
appears on the page.

**Rule of thumb:** Only list `@wordpress/connectors` as a dependency. Use
`window.wp.*` for everything else.

### API key masking pattern

WordPress core masks keys by showing bullet characters (`•`) plus the last
4 characters. Follow the same convention so users have a consistent experience.

### Settings option naming

Prefix your option names with `connectors_ai_` followed by your provider slug
to avoid collisions with other plugins. Example:
`connectors_ai_my_ai_provider_api_key`. The slug must use underscores
(matching your provider ID).

### Environment variable fallback

Support environment variables (e.g. `MY_AI_API_KEY`) as a fallback so users
can configure the provider in `wp-config.php` or deployment environments
without storing keys in the database.

### The connectors page hooks

The Connectors admin page fires a page-specific action you can use to enqueue
your JS module — this avoids loading it on every admin page.

| Beta   | Page file                        | Hook                                    |
| ------ | -------------------------------- | --------------------------------------- |
| Beta 2 | `connectors/page-wp-admin.php`   | `connectors-wp-admin_init`              |
| Beta 3 | `options-connectors/page-wp-admin.php` | `options-connectors-wp-admin_init` |

Hook **both** to stay compatible across versions.

### OpenAI-compatible APIs

If your AI service uses the same chat/completions format as OpenAI (many do),
extend `AbstractOpenAiCompatibleTextGenerationModel` and override only
`createRequest()` if your URL structure differs. You get full parameter
building, response parsing, streaming, and tool-calling support for free.

### Provider type

Choose the right `ProviderTypeEnum`:

| Type       | Meaning |
| ---------- | ------- |
| `cloud()`  | Remote API (most common) |
| `server()` | Self-hosted server (e.g. Ollama, llama.cpp) |
| `client()` | Runs in the browser (e.g. WebLLM) |

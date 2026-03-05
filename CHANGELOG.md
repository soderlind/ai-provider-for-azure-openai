# Changelog

All notable changes to this project will be documented in this file.

## 1.2.0

### Fixed

- Connector settings page now works on WordPress 7.0 beta 3 — hooks both `options-connectors-wp-admin_init` and `connectors-wp-admin_init` to cover the renamed page
- Custom connector UI no longer overwritten by core's auto-registered generic ApiKeyConnector — filters `script_module_data_*` to remove the duplicate entry
- Connector slug changed to `ai-provider/azure-openai` to match Beta 3's `{type}/{id}` format

### Changed

- Updated `docs/how-to-add-ai-provider.md` with WordPress 7.0 Beta 3 differences (hook changes, slug format, data filter, new §5e section)

## 1.1.2

### Fixed

- Fatal error on WordPress 7.0 beta 3 when the "AI Experiments" plugin is active — its bundled php-ai-client v0.3.1 (via Jetpack autoloader) overrides core's v1.2.0, missing `getAuthenticationMethod()`

### Added

- `has_ai_client_version_conflict()` guard that detects incompatible php-ai-client versions at runtime
- Admin notice prompting users to deactivate the conflicting plugin

## 1.1.1

### Added

- Environment variable fallback for capabilities (`AZURE_OPENAI_CAPABILITIES`) — accepts a comma-separated list (e.g. `text_generation,chat_history`)

## 1.1.0

### Added

- Connectors page integration (Settings → Connectors) for API key, endpoint, deployment, and capabilities
- `Connector_Settings` class for REST-visible individual options
- wp-scripts build pipeline for JavaScript (ESM script module output)
- Vitest 4.x test infrastructure for the Connectors JavaScript
- GitHub Actions build step (Node.js 20, `npm ci && npm run build`) in release workflows
- i18n pipeline (`make-pot`, `update-po`, `make-mo`, `make-json`, `make-php`)
- How-to guide for building custom AI providers (`docs/how-to-add-ai-provider.md`)

### Changed

- Connector icon changed from Azure brand SVG to Gutenberg cloud icon
- Button labels: "Connect" → "Set Up", "Manage" → "Edit"
- Button variants follow WP core Connectors page conventions (secondary+compact / tertiary)
- Added `__next40pxDefaultSize` prop to all `Button` and `TextControl` components
- Added `__nextHasNoMarginBottom` prop to all `TextControl` components
- `AzureApiKeyRequestAuthentication` now extends `ApiKeyRequestAuthentication` (fixes `instanceof` validation in the SDK's `ProviderRegistry`)
- Simplified `Settings_Manager` to read from connector options with environment variable fallback

### Removed

- Legacy Settings → Azure OpenAI admin page

### Fixed

- Fatal `InvalidArgumentException` when the SDK validated the authentication object type
- `fetchDeployments()` read API key from legacy `wp_ai_client_provider_credentials` instead of `Connector_Settings` — caused "No models found" when deployment ID is not set
- Missing `webSearch` supported option on text generation models — caused model rejection when callers use `->usingWebSearch()`
- Multimodal input modalities now declare `[text, image]`, `[text, image, audio]`, `[text, document]`, and `[text, image, document]` combinations — fixes "No models found" when callers attach images or documents via `with_file()`

## 1.0.0

- Initial release
- Text generation support (GPT models)
- Image generation support (DALL-E models)
- Embedding generation support (text-embedding models)
- Text-to-speech support (tts-1, tts-1-hd models)
- Settings page with environment variable fallback
- Configurable capabilities per deployment

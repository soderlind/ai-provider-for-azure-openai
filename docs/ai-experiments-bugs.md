# AI Experiments Plugin — Compatibility Issues for Third-Party AI Providers

Issues encountered while developing a third-party AI provider plugin (`ai-provider-for-azure-openai`) against WordPress 7.0's built-in AI Client SDK.

All issues stem from the AI Experiments plugin (`ai`) bundling SDK v0.3.1 and loading it via the Jetpack autoloader, which **overrides** the newer ~0.4.x SDK bundled in WordPress core (`wp-includes/php-ai-client/`).

## Environment

- WordPress 7.0-beta2
- AI Experiments plugin (bundles `wordpress/php-ai-client` v0.3.1)
- AI Provider for Azure OpenAI (third-party provider plugin)

---

## 1. Jetpack Autoloader Overrides Core SDK Classes

**Problem:** The AI Experiments plugin uses a Jetpack autoloader that calls `spl_autoload_register()` with `prepend: true`. This causes its bundled v0.3.1 SDK to be loaded **instead of** the newer ~0.4.x SDK shipped in `wp-includes/php-ai-client/`.

**Impact:** Third-party provider plugins that develop and test against the core SDK encounter runtime failures because different (older) class implementations are loaded.

**Expected:** The core SDK classes in `wp-includes/` should take precedence, or the AI Experiments plugin should not bundle an older version that overrides core.

---

## 2. `TextGenerationModelInterface` Adds `streamGenerateTextResult()` in v0.3.1

**Problem:** The v0.3.1 `TextGenerationModelInterface` adds a new method:

```php
public function streamGenerateTextResult(array $prompt): Generator;
```

This method does **not** exist in the core ~0.4.x interface at `wp-includes/php-ai-client/src/Providers/Models/TextGeneration/Contracts/TextGenerationModelInterface.php`.

**Impact:** Any third-party model class that `implements TextGenerationModelInterface` and is developed against the core SDK will trigger a **PHP Fatal error** at runtime:

```
PHP Fatal error: Class ... contains 1 abstract method and must therefore be
declared abstract or implement the remaining methods
(...TextGenerationModelInterface::streamGenerateTextResult)
```

This fatal is silently caught by `isProviderConfigured()` (which wraps `listModelMetadata()` in a try/catch), causing the provider to be **silently excluded** from model discovery. The user sees the opaque error: *"No models found that support text_generation for this prompt."*

**Workaround:** Extend `AbstractOpenAiCompatibleTextGenerationModel` (which implements the streaming stub) instead of implementing `TextGenerationModelInterface` directly.

**Expected:** The interface in v0.3.1 should not add abstract methods that don't exist in the core SDK. If streaming is added, it should be in a separate `StreamingTextGenerationModelInterface` or similar, following the Interface Segregation Principle.

---

## 3. `ProviderRegistry::registerProvider()` Missing HTTP Transporter Auto-Discovery

**Problem:** The core ~0.4.x `ProviderRegistry::registerProvider()` includes a fallback that auto-discovers an HTTP transporter via `HttpTransporterFactory::createTransporter()` when no transporter is set:

```php
// Core ~0.4.x registerProvider() — nested try/catch with fallback
try {
    $httpTransporter = $this->getHttpTransporter();
} catch (RuntimeException $e) {
    try {
        $this->setHttpTransporter(HttpTransporterFactory::createTransporter());
        $httpTransporter = $this->getHttpTransporter();
    } catch (DiscoveryNotFoundException $e) {
        // OK, will be set later
    }
}
```

The v0.3.1 version **removes this fallback entirely** — it just silently catches the exception and moves on:

```php
// v0.3.1 registerProvider() — single try/catch, no fallback
try {
    $httpTransporter = $this->getHttpTransporter();
    $this->setHttpTransporterForProvider($className, $httpTransporter);
} catch (RuntimeException $e) {
    // Silently ignored. No auto-discovery.
}
```

**Impact:** When a third-party provider registers via `$registry->registerProvider(MyProvider::class)`, the HTTP transporter is never set. Any subsequent text generation call fails with:

```
HttpTransporterInterface instance not set. Make sure you use the AiClient class for all requests.
```

**Workaround:** Third-party plugins must manually bootstrap the transporter before registering:

```php
try {
    $registry->getHttpTransporter();
} catch ( \RuntimeException $e ) {
    $registry->setHttpTransporter(
        HttpTransporterFactory::createTransporter()
    );
}
$registry->registerProvider( MyProvider::class );
```

**Expected:** `registerProvider()` should auto-discover the transporter (matching core behavior), or the transporter should be set on the registry during WordPress bootstrap before plugins register providers.

---

## 4. Silent Provider Exclusion Hides Root Causes

**Problem:** When a provider's model class fails to load (e.g., due to issue #2), the `isProviderConfigured()` method catches the exception and silently excludes the provider from model candidate discovery. The user only sees:

```
No models found that support text_generation for this prompt.
```

**Impact:** Debugging is extremely difficult because:
- No error is logged
- No admin notice is shown
- The actual PHP Fatal is only visible in `debug.log` if `WP_DEBUG_LOG` is enabled
- The error message gives no indication that the provider failed to load

**Expected:** Provider loading failures should be logged (at minimum) and ideally surfaced in the admin UI (e.g., Site Health or an admin notice).

---

## 5. Scoped vs. Un-scoped Dependencies

**Problem:** The core SDK uses scoped (prefixed) dependencies:
```php
use WordPress\AiClientDependencies\Http\Discovery\Psr18ClientDiscovery;
```

The v0.3.1 SDK uses un-scoped originals:
```php
use Http\Discovery\Psr18ClientDiscovery;
```

Since the Jetpack autoloader loads v0.3.1's `HttpTransporterFactory`, it references un-scoped `Http\Discovery\*` classes. These may or may not be available depending on which autoloader wins for that namespace.

**Impact:** Potential class-not-found errors or unexpected behavior when the scoped and un-scoped discovery strategies resolve to different HTTP clients.

**Expected:** The SDK version loaded at runtime should use dependencies consistent with the WordPress environment it's running in.

---

## Workarounds Implemented in This Plugin

The following workarounds are applied **only when the AI Experiments plugin is active** (detected via `is_ai_experiments_active()` in `plugin.php`). They can be safely removed once the AI Experiments plugin is updated to ship a compatible SDK or is no longer needed.

### W1. Extend `AbstractOpenAiCompatibleTextGenerationModel` (Issue #2)

**File:** `src/Models/AzureOpenAiTextGenerationModel.php`

Instead of extending `AbstractApiBasedModel` and implementing `TextGenerationModelInterface` directly (as in the core SDK examples), the text generation model extends `AbstractOpenAiCompatibleTextGenerationModel`.

This base class:
- Provides a full OpenAI-compatible `generateTextResult()` implementation (parameter building, message formatting, response parsing, tool calls)
- Includes a `streamGenerateTextResult()` stub that throws `RuntimeException('Streaming is not yet implemented.')`, satisfying the v0.3.1 interface

Our class only needs to implement `createRequest()` to map paths to Azure's deployment URL format, and `getDeploymentId()` to resolve the configured deployment.

**This is also better design** — less code, reuses tested OpenAI-compatible logic — so it is applied unconditionally rather than gated behind the feature flag. It works correctly with both the core ~0.4.x SDK and the v0.3.1 SDK.

### W2. Explicit HTTP Transporter Bootstrap (Issue #3)

**File:** `plugin.php` → `register_provider()`

Before registering the provider, the plugin checks if the HTTP transporter is already set on the `ProviderRegistry`. If not, it manually creates one via `HttpTransporterFactory::createTransporter()`. This mirrors what core's ~0.4.x `registerProvider()` does internally but which v0.3.1 omits.

```php
if ( is_ai_experiments_active() ) {
    try {
        $registry->getHttpTransporter();
    } catch ( \RuntimeException $e ) {
        try {
            $registry->setHttpTransporter(
                HttpTransporterFactory::createTransporter()
            );
        } catch ( \Throwable $e ) {
            // Discovery not yet available; transporter will be set later by core.
        }
    }
}
```

This workaround is **gated behind `is_ai_experiments_active()`** and is skipped when only the core SDK is loaded.

### W3. Full Supported Options Declaration (Issue #2, related)

**File:** `src/Metadata/AzureOpenAiModelMetadataDirectory.php` → `buildSupportedOptionsForCapabilities()`

The v0.3.1 SDK's `ModelRequirements::areMetBy()` converts every non-null `ModelConfig` property into a `RequiredOption`. If a caller sets `temperature`, `systemInstruction`, `candidateCount`, or `outputModalities`, those become requirements the model metadata must declare support for.

The metadata directory declares all 17 supported options for text generation models:

`systemInstruction`, `candidateCount`, `maxTokens`, `temperature`, `topP`, `topK`, `stopSequences`, `presencePenalty`, `frequencyPenalty`, `logprobs`, `topLogprobs`, `outputMimeType`, `outputSchema`, `functionDeclarations`, `customOptions`, `inputModalities`, `outputModalities`

Without this, any prompt using even basic options like `temperature` or `systemInstruction` would result in "No models found" because the model was rejected during capability matching.

**This is correct behavior** regardless of AI Experiments, applied unconditionally.

---

## Summary

| # | Issue | Severity | User-Visible Error | Workaround |
|---|-------|----------|--------------------|------------|
| 1 | Jetpack autoloader overrides core SDK | High | Various (enables all below) | — |
| 2 | Interface adds abstract method | Critical | "No models found" / PHP Fatal | W1 (unconditional) |
| 3 | Missing HTTP transporter auto-discovery | Critical | "HttpTransporterInterface not set" | W2 (gated) |
| 4 | Silent provider exclusion | Medium | "No models found" (misleading) | — |
| 5 | Scoped vs. un-scoped deps | Low | Potential class-not-found | — |

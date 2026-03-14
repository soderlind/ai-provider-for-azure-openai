# Core Trac Ticket: `_wp_connectors_init()` should sanitize provider IDs

## Summary

`_wp_connectors_init()` passes AI Client provider IDs directly to
`WP_Connector_Registry::register()`, which rejects IDs containing hyphens.
Third-party providers using hyphens in their ID (e.g. `azure-openai`) are
silently dropped from the connector registry, causing downstream features
(AI Experiments) to report missing credentials even though the provider
works correctly at the AiClient level.

## Steps to Reproduce

1. Register an AI Client provider with a hyphenated ID:
   ```php
   new ProviderMetadata(
       'my-custom-provider',  // contains a hyphen
       'My Custom Provider',
       ProviderTypeEnum::cloud(),
       'https://example.com/keys',
       RequestAuthenticationMethod::apiKey()
   );
   ```
2. Activate the provider plugin.
3. Go to **Settings → Connectors** — the provider does **not** appear.
4. Activate AI Experiments — the "no valid AI Connector" warning is shown.
5. Check debug log — a `_doing_it_wrong` notice from
   `WP_Connector_Registry::register()` is present:
   > Connector ID must contain only lowercase alphanumeric characters and underscores.

## Expected Behavior

Provider IDs with hyphens should either:

1. **Be automatically sanitized** (hyphens → underscores) by
   `_wp_connectors_init()` before passing to the registry, or
2. **The registry should accept hyphens** in connector IDs (relax the regex
   to `/^[a-z0-9_-]+$/`).

Option 1 is preferred because it maintains the existing connector ID format
while being transparent to provider authors.

## Proposed Patch

In `wp-includes/connectors.php`, inside `_wp_connectors_init()`, sanitize
the connector ID before use:

```php
foreach ( $ai_registry->getRegisteredProviderIds() as $connector_id ) {
    // Sanitize: WP_Connector_Registry only allows [a-z0-9_].
    $connector_id = str_replace( '-', '_', $connector_id );

    $provider_class_name = $ai_registry->getProviderClassName( $connector_id );
    // ... rest of the loop
}
```

**Note:** `getProviderClassName()` also needs to accept the original
(unsanitized) ID since the AiClient registry stores providers under the
original key. The sanitization should only apply to the connector registry
side.

A more robust approach:

```php
foreach ( $ai_registry->getRegisteredProviderIds() as $provider_id ) {
    // Connector IDs only allow [a-z0-9_]; provider IDs may use hyphens.
    $connector_id        = str_replace( '-', '_', $provider_id );
    $provider_class_name = $ai_registry->getProviderClassName( $provider_id );
    // ... use $connector_id for registry->register(), $provider_id for AiClient lookups
}
```

## Impact

- All three built-in providers (`anthropic`, `google`, `openai`) use
  single-word IDs and are unaffected.
- Any third-party provider using a hyphenated ID is silently broken.
- The `_doing_it_wrong` notice is only visible with `WP_DEBUG` enabled
  and is easy to miss.

## Component

Connectors API / AI Client

## Version

7.0-beta5

## Related

- `wp-includes/class-wp-connector-registry.php` — ID validation regex
- `wp-includes/connectors.php` — `_wp_connectors_init()` function
- AI Experiments plugin `includes/helpers.php` — `has_ai_credentials()` check

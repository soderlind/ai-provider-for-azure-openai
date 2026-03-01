/**
 * Mock for @wordpress/connectors script module.
 *
 * Vitest resolves this via the alias in vitest.config.js so that
 * Vite's import-analysis plugin can find the module before vi.mock()
 * runs.  Individual tests can still override behaviour with vi.mock().
 *
 * Exports use the __experimental* names that the source code imports.
 */

export const __experimentalRegisterConnector = vi.fn();

export const __experimentalConnectorItem = ( { children } ) => children ?? null;

export const __experimentalDefaultConnectorSettings = ( { children } ) => children ?? null;

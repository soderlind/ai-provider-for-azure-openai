<?php
/**
 * PSR-4 Autoloader for the Azure OpenAI AI Provider.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

spl_autoload_register(
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- Standard autoloader convention.
	static function ( string $class ): void {
		$prefix   = 'WordPress\\AzureOpenAiAiProvider\\';
		$base_dir = __DIR__ . '/';

		// Check if the class uses the namespace prefix.
		$len = strlen( $prefix );
		if ( strncmp( $class, $prefix, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// Convert namespace separators to directory separators.
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

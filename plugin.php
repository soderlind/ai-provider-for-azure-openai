<?php
/**
 * Plugin Name: AI Provider for Azure OpenAI
 * Plugin URI: https://github.com/soderlind/ai-provider-for-azure-openai
 * Description: AI Provider for Azure OpenAI for the WordPress AI Client.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: Per Soderlind
 * Author URI: https://soderlind.no
 * License: GPL-2.0-or-later
 * Text Domain: ai-provider-for-azure-openai
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\HttpTransporterFactory;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Http\AzureApiKeyRequestAuthentication;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Define plugin constants.
define( 'AZURE_OPENAI_PROVIDER_VERSION', '1.0.0' );
define( 'AZURE_OPENAI_PROVIDER_FILE', __FILE__ );
define( 'AZURE_OPENAI_PROVIDER_DIR', plugin_dir_path( __FILE__ ) );

// Load autoloader.
require_once __DIR__ . '/src/autoload.php';

/**
 * Check whether the AI Experiments plugin is active.
 *
 * Used to conditionally apply workarounds for SDK incompatibilities
 * introduced by the AI Experiments plugin's Jetpack autoloader, which
 * overrides core's ~0.4.x SDK with its bundled v0.3.1 copy.
 *
 * @see docs/ai-experiments-bugs.md
 *
 * @return bool True when the AI Experiments plugin is active.
 */
function is_ai_experiments_active(): bool {
	$active_plugins = (array) get_option( 'active_plugins', array() );
	return in_array( 'ai/ai.php', $active_plugins, true );
}

/**
 * Register the Azure OpenAI provider with the AI Client.
 * Must run early so wp-ai-client can detect it for the settings page.
 *
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	/*
	 * Workaround: AI Experiments plugin ships SDK v0.3.1 via Jetpack
	 * autoloader, whose ProviderRegistry::registerProvider() does not
	 * auto-discover the HTTP transporter (core ~0.4.x does).
	 *
	 * @see docs/ai-experiments-bugs.md — Issue #3
	 */
	if ( is_ai_experiments_active() ) {
		try {
			$registry->getHttpTransporter();
		} catch (\RuntimeException $e) {
			try {
				$registry->setHttpTransporter(
					HttpTransporterFactory::createTransporter()
				);
			} catch (\Throwable $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Discovery not yet available; transporter will be set later by core.
			}
		}
	}

	if ( ! $registry->hasProvider( AzureOpenAiProvider::class) ) {
		$registry->registerProvider( AzureOpenAiProvider::class);
	}
}
// Register provider early so wp-ai-client detects it for settings page.
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

/**
 * Set up Azure-specific authentication after wp-ai-client has loaded credentials.
 *
 * @return void
 */
function setup_authentication(): void {
	if ( ! class_exists( AiClient::class) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	// Get API key from wp-ai-client's credential storage.
	$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
	$api_key     = $credentials[ 'azure-openai' ] ?? '';

	// Fallback to environment variable.
	if ( empty( $api_key ) ) {
		$env_key = getenv( 'AZURE_OPENAI_API_KEY' );
		if ( false !== $env_key && '' !== $env_key ) {
			$api_key = $env_key;
		}
	}

	if ( ! empty( $api_key ) ) {
		// Override with Azure-specific authentication (uses 'api-key' header instead of 'Authorization: Bearer').
		$registry->setProviderRequestAuthentication(
			'azure-openai',
			new AzureApiKeyRequestAuthentication( $api_key )
		);
	}
}
// Run AFTER wp-ai-client (priority 10) to read credentials they stored.
add_action( 'init', __NAMESPACE__ . '\\setup_authentication', 15 );

/**
 * Initialize the settings manager for Azure-specific settings (endpoint, api-version, deployment-id).
 *
 * @return void
 */
function init_settings(): void {
	Settings_Manager::get_instance()->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init_settings' );

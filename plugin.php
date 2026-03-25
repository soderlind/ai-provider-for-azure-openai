<?php
/**
 * Plugin Name: AI Provider for Azure OpenAI
 * Plugin URI: https://github.com/soderlind/ai-provider-for-azure-openai
 * Description: AI Provider for Azure OpenAI for the WordPress AI Client.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 1.5.0
 * Author: Per Soderlind
 * Author URI: https://soderlind.no
 * License: GPL-2.0-or-later
 * Text Domain: ai-provider-for-azure-openai
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Http\AzureApiKeyRequestAuthentication;
use WordPress\AzureOpenAiAiProvider\Settings\Connector_Settings;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Define plugin constants.
define( 'AZURE_OPENAI_PROVIDER_VERSION', '1.5.0' );
define( 'AZURE_OPENAI_PROVIDER_FILE', __FILE__ );
define( 'AZURE_OPENAI_PROVIDER_DIR', plugin_dir_path( __FILE__ ) );

// Load autoloader.
require_once __DIR__ . '/src/autoload.php';

/**
 * Detect whether the loaded ProviderMetadata class is missing methods
 * required by WordPress 7.0, which happens when the old "AI Experiments"
 * plugin ships an outdated php-ai-client via the Jetpack autoloader.
 *
 * @return bool True if the conflict is detected.
 */
function has_ai_client_version_conflict(): bool {
	// Trigger autoloading so we test the class that would actually be used.
	if ( ! class_exists( \WordPress\AiClient\Providers\DTO\ProviderMetadata::class ) ) {
		return false;
	}
	return ! method_exists(
		\WordPress\AiClient\Providers\DTO\ProviderMetadata::class,
		'getAuthenticationMethod'
	);
}

/**
 * Show an admin notice when a conflicting php-ai-client version is detected.
 *
 * @return void
 */
function show_ai_client_conflict_notice(): void {
	if ( ! has_ai_client_version_conflict() ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__(
			'AI Provider for Azure OpenAI: A conflicting AI client library was detected. Please deactivate the "AI Experiments" plugin — its outdated library overrides the version built into WordPress 7.0.',
			'ai-provider-for-azure-openai'
		)
	);
}
add_action( 'admin_notices', __NAMESPACE__ . '\\show_ai_client_conflict_notice' );
add_action( 'network_admin_notices', __NAMESPACE__ . '\\show_ai_client_conflict_notice' );

/**
 * Register the Azure OpenAI provider with the AI Client.
 * Must run early so wp-ai-client can detect it for the settings page.
 *
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	// Bail out when the old AI Experiments plugin is active to avoid a fatal
	// error caused by its outdated php-ai-client (missing getAuthenticationMethod).
	if ( has_ai_client_version_conflict() ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( ! $registry->hasProvider( AzureOpenAiProvider::class ) ) {
		$registry->registerProvider( AzureOpenAiProvider::class );
	}
}
// Register provider early so wp-ai-client detects it for settings page.
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

/**
 * Set up Azure-specific authentication after wp-ai-client has loaded credentials.
 *
 * Reads the connector option (unmasked) with environment variable fallback.
 *
 * @return void
 */
function setup_authentication(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	if ( has_ai_client_version_conflict() ) {
		return;
	}

	$registry = AiClient::defaultRegistry();
	$api_key  = Connector_Settings::get_real_api_key();

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
			'azure_openai',
			new AzureApiKeyRequestAuthentication( $api_key )
		);
	}
}
// Run after core connector key binding (priority 20) so Azure's `api-key`
// header auth overrides the generic bearer-token auth object.
add_action( 'init', __NAMESPACE__ . '\\setup_authentication', 30 );

/**
 * Initialize the settings manager for Azure-specific settings (endpoint, api-version, deployment-id).
 *
 * @return void
 */
function init_settings(): void {
	Settings_Manager::get_instance()->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init_settings' );

/**
 * Register the connector JavaScript module on the Connectors admin page.
 *
 * Uses the script module system so the browser can resolve @wordpress/* imports
 * via the import map that WP 7.0 emits.
 *
 * @return void
 */
function register_connector_module(): void {
	/*
	 * Only @wordpress/connectors is a script module in WP 7.0.
	 * The remaining dependencies (api-fetch, element, i18n, components)
	 * are classic scripts loaded via the boot system prerequisites,
	 * so they are accessed from window.wp.* in the JS file.
	 */
	wp_register_script_module(
		'ai-provider-for-azure-openai/connectors',
		plugins_url( 'build/connectors.js', AZURE_OPENAI_PROVIDER_FILE ),
		array(
			array(
				'id'     => '@wordpress/connectors',
				'import' => 'dynamic',
			),
		),
		AZURE_OPENAI_PROVIDER_VERSION
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_connector_module' );

/**
 * Enqueue the connector module on the Connectors admin page.
 *
 * WordPress 7.0 beta 3 ships two connectors page variants:
 * - options-connectors.php  → fires 'options-connectors-wp-admin_init'
 * - connectors.php (plugin) → fires 'connectors-wp-admin_init'
 * Hook into both so the module loads regardless of which page is active.
 *
 * @return void
 */
function enqueue_connector_module(): void {
	wp_enqueue_script_module( 'ai-provider-for-azure-openai/connectors' );
}
add_action( 'options-connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );
add_action( 'connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );

/**
 * Remove our provider from the connector JSON data sent to JavaScript.
 *
 * Core's registerDefaultConnectors() reads this data and registers a generic
 * ApiKeyConnector for every provider. Because that function runs AFTER our
 * script module (it's loaded via a dynamic import chain), it would overwrite
 * our custom connector registration. Removing the entry from the data
 * prevents the conflict entirely.
 *
 * @param array $data Script module data.
 * @return array Filtered data.
 */
function filter_connector_script_data( array $data ): array {
	if ( isset( $data['connectors']['azure_openai'] ) ) {
		unset( $data['connectors']['azure_openai'] );
	}
	return $data;
}
add_filter( 'script_module_data_options-connectors-wp-admin', __NAMESPACE__ . '\\filter_connector_script_data', 20 );
add_filter( 'script_module_data_connectors-wp-admin', __NAMESPACE__ . '\\filter_connector_script_data', 20 );

/**
 * Run one-time migration from legacy settings to connector options.
 *
 * Migrates:
 *  - Serialized Settings_Manager options → individual connector options.
 *  - wp_ai_client_provider_credentials['azure-openai' or 'azure_openai'] → connector API key option.
 *
 * @return void
 */
function maybe_migrate_settings(): void {
	$migrated_key = 'azure_openai_connector_migrated';

	if ( get_option( $migrated_key ) ) {
		return;
	}

	// Migrate legacy serialized settings.
	$legacy = get_option( Settings_Manager::OPTION_NAME, array() );

	if ( ! empty( $legacy ) ) {
		if ( ! empty( $legacy['endpoint'] ) && ! get_option( Connector_Settings::OPTION_ENDPOINT ) ) {
			update_option( Connector_Settings::OPTION_ENDPOINT, $legacy['endpoint'] );
		}
		if ( ! empty( $legacy['api_version'] ) && ! get_option( Connector_Settings::OPTION_API_VERSION ) ) {
			update_option( Connector_Settings::OPTION_API_VERSION, $legacy['api_version'] );
		}
		if ( ! empty( $legacy['deployment_id'] ) && ! get_option( Connector_Settings::OPTION_DEPLOYMENT_ID ) ) {
			update_option( Connector_Settings::OPTION_DEPLOYMENT_ID, $legacy['deployment_id'] );
		}
		if ( ! empty( $legacy['capabilities'] ) && ! get_option( Connector_Settings::OPTION_CAPABILITIES ) ) {
			update_option( Connector_Settings::OPTION_CAPABILITIES, $legacy['capabilities'] );
		}
	}

	// Migrate API key from wp-ai-client credentials.
	$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
	$api_key     = $credentials['azure_openai'] ?? $credentials['azure-openai'] ?? '';

	if ( ! empty( $api_key ) && ! get_option( Connector_Settings::OPTION_API_KEY ) ) {
		update_option( Connector_Settings::OPTION_API_KEY, $api_key );
	}

	update_option( $migrated_key, true );
}
add_action( 'admin_init', __NAMESPACE__ . '\\maybe_migrate_settings' );

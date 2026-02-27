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
 * Register the Azure OpenAI provider with the AI Client.
 *
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( AzureOpenAiProvider::class) ) {
		return;
	}

	$registry->registerProvider( AzureOpenAiProvider::class);

	// Set up authentication with credentials from settings/env vars.
	$settings = Settings_Manager::get_instance();
	$api_key  = $settings->get_api_key();

	if ( ! empty( $api_key ) ) {
		$registry->setProviderRequestAuthentication(
			'azure-openai',
			new AzureApiKeyRequestAuthentication( $api_key )
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

/**
 * Initialize the settings manager.
 *
 * @return void
 */
function init_settings(): void {
	Settings_Manager::get_instance()->init();
}
add_action( 'admin_init', __NAMESPACE__ . '\\init_settings' );

/**
 * Add settings page to admin menu.
 *
 * @return void
 */
function add_settings_page(): void {
	Settings_Manager::get_instance()->add_settings_page();
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_settings_page' );

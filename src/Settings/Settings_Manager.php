<?php
/**
 * Settings Manager for Azure OpenAI Provider.
 *
 * Reads from individual connector options with environment variable fallback.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Settings;

/**
 * Settings Manager class.
 *
 * Handles plugin settings storage and retrieval with environment variable fallbacks.
 */
class Settings_Manager {

	/**
	 * Legacy option name kept for migration purposes only.
	 */
	const OPTION_NAME = 'wp_ai_provider_azure_openai_settings';

	/**
	 * Default API version.
	 */
	const DEFAULT_API_VERSION = '2024-02-15-preview';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self The instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {}

	/**
	 * Initialize settings hooks.
	 *
	 * Registers connector settings (REST-visible individual options).
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( Connector_Settings::class, 'register' ) );
	}

	// ------------------------------------------------------------------
	// Getters
	// ------------------------------------------------------------------

	/**
	 * Resolve a setting value from the connector option with env fallback.
	 *
	 * @param string $connector_option The connector option name.
	 * @param string $env_name         Environment variable name for fallback.
	 * @param string $default          Default value when all sources are empty.
	 * @return string Resolved value.
	 */
	private function resolve( string $connector_option, string $env_name = '', string $default = '' ): string { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
		$value = (string) get_option( $connector_option, '' );

		if ( '' === $value && '' !== $env_name ) {
			$env_value = getenv( $env_name );
			if ( false !== $env_value && '' !== $env_value ) {
				$value = $env_value;
			}
		}

		if ( '' === $value ) {
			$value = $default;
		}

		return $value;
	}

	/**
	 * Get the endpoint URL with environment variable fallback.
	 *
	 * @return string The endpoint URL.
	 */
	public function get_endpoint(): string {
		return $this->resolve(
			Connector_Settings::OPTION_ENDPOINT,
			'AZURE_OPENAI_ENDPOINT'
		);
	}

	/**
	 * Get the API version with environment variable fallback.
	 *
	 * @return string The API version.
	 */
	public function get_api_version(): string {
		return $this->resolve(
			Connector_Settings::OPTION_API_VERSION,
			'AZURE_OPENAI_API_VERSION',
			self::DEFAULT_API_VERSION
		);
	}

	/**
	 * Get the deployment ID with environment variable fallback.
	 *
	 * @return string The deployment ID.
	 */
	public function get_deployment_id(): string {
		return $this->resolve(
			Connector_Settings::OPTION_DEPLOYMENT_ID,
			'AZURE_OPENAI_DEPLOYMENT_ID'
		);
	}

	/**
	 * Get the capabilities setting.
	 *
	 * @return array The enabled capabilities.
	 */
	public function get_capabilities(): array {
		$value = get_option( Connector_Settings::OPTION_CAPABILITIES, array() );

		// Default to text_generation + chat_history if not set.
		if ( empty( $value ) ) {
			$value = array( 'text_generation', 'chat_history' );
		}

		return $value;
	}

	/**
	 * Check if all required settings are configured.
	 *
	 * @return bool True if configured.
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_endpoint() );
	}

	/**
	 * Get a list of missing required settings.
	 *
	 * @return array<string> Human-readable labels of missing settings.
	 */
	public function get_missing_settings(): array {
		$missing = array();

		if ( empty( $this->get_endpoint() ) ) {
			$missing[] = __( 'Endpoint URL', 'ai-provider-for-azure-openai' );
		}

		$api_key = Connector_Settings::get_real_api_key();

		if ( empty( $api_key ) ) {
			$env_key = getenv( 'AZURE_OPENAI_API_KEY' );
			if ( false === $env_key || '' === $env_key ) {
				$missing[] = __( 'API Key (set in Connectors settings)', 'ai-provider-for-azure-openai' );
			}
		}

		return $missing;
	}
}

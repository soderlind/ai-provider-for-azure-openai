<?php
/**
 * Connector Settings for Azure OpenAI Provider (WP >= 7.0).
 *
 * Registers individual REST-visible settings under the 'connectors' group
 * so they appear on the Connectors admin page and the /wp/v2/settings endpoint.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Connector Settings class.
 *
 * Mirrors core's _wp_register_default_connector_settings() pattern for
 * the Azure OpenAI provider: individual options, API key masking, REST
 * visibility, and sanitize callbacks.
 */
class Connector_Settings {

	/**
	 * Option name for the API key.
	 */
	const OPTION_API_KEY = 'connectors_ai_azure_openai_api_key';

	/**
	 * Option name for the endpoint URL.
	 */
	const OPTION_ENDPOINT = 'connectors_ai_azure_openai_endpoint';

	/**
	 * Option name for the API version.
	 */
	const OPTION_API_VERSION = 'connectors_ai_azure_openai_api_version';

	/**
	 * Option name for the deployment ID.
	 */
	const OPTION_DEPLOYMENT_ID = 'connectors_ai_azure_openai_deployment_id';

	/**
	 * Option name for the capabilities array.
	 */
	const OPTION_CAPABILITIES = 'connectors_ai_azure_openai_capabilities';

	/**
	 * Register all connector settings.
	 *
	 * Should be called on `init` when WP >= 7.0.
	 *
	 * @return void
	 */
	public static function register(): void {
		// API key — masked, sanitized, REST-visible.
		register_setting(
			'connectors',
			self::OPTION_API_KEY,
			array(
				'type'              => 'string',
				'label'             => __( 'Azure OpenAI API Key', 'ai-provider-for-azure-openai' ),
				'description'       => __( 'API key for the Azure OpenAI AI provider.', 'ai-provider-for-azure-openai' ),
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_api_key' ),
			)
		);
		add_filter( 'option_' . self::OPTION_API_KEY, array( __CLASS__, 'mask_api_key' ) );

		// Endpoint URL.
		register_setting(
			'connectors',
			self::OPTION_ENDPOINT,
			array(
				'type'              => 'string',
				'label'             => __( 'Azure OpenAI Endpoint URL', 'ai-provider-for-azure-openai' ),
				'description'       => __( 'Your Azure OpenAI endpoint URL (e.g., https://your-resource.openai.azure.com).', 'ai-provider-for-azure-openai' ),
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		// API version.
		register_setting(
			'connectors',
			self::OPTION_API_VERSION,
			array(
				'type'              => 'string',
				'label'             => __( 'Azure OpenAI API Version', 'ai-provider-for-azure-openai' ),
				'description'       => __( 'Azure OpenAI API version string.', 'ai-provider-for-azure-openai' ),
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Deployment ID.
		register_setting(
			'connectors',
			self::OPTION_DEPLOYMENT_ID,
			array(
				'type'              => 'string',
				'label'             => __( 'Azure OpenAI Deployment ID', 'ai-provider-for-azure-openai' ),
				'description'       => __( 'The name of your Azure OpenAI deployment.', 'ai-provider-for-azure-openai' ),
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Capabilities (array of strings).
		register_setting(
			'connectors',
			self::OPTION_CAPABILITIES,
			array(
				'type'              => 'array',
				'label'             => __( 'Azure OpenAI Capabilities', 'ai-provider-for-azure-openai' ),
				'description'       => __( 'Capabilities supported by the Azure OpenAI deployment.', 'ai-provider-for-azure-openai' ),
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
							'enum' => array(
								'text_generation',
								'image_generation',
								'chat_history',
								'embedding_generation',
								'text_to_speech_conversion',
							),
						),
					),
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize_capabilities' ),
			)
		);
	}

	/**
	 * Sanitize the API key.
	 *
	 * @param string $value The raw API key.
	 * @return string The sanitized API key.
	 */
	public static function sanitize_api_key( $value ): string {
		return sanitize_text_field( $value );
	}

	/**
	 * Mask an API key for display, showing only the last 4 characters.
	 *
	 * Reuses core's masking convention (bullet characters + last 4 chars).
	 *
	 * @param string $key The API key.
	 * @return string The masked key.
	 */
	public static function mask_api_key( $key ): string {
		if ( ! is_string( $key ) || strlen( $key ) <= 4 ) {
			return is_string( $key ) ? $key : '';
		}

		return str_repeat( "\u{2022}", min( strlen( $key ) - 4, 16 ) ) . substr( $key, -4 );
	}

	/**
	 * Retrieve the real (unmasked) API key from the database.
	 *
	 * Temporarily removes the masking filter, reads the option, then re-adds it.
	 *
	 * @return string The raw API key.
	 */
	public static function get_real_api_key(): string {
		remove_filter( 'option_' . self::OPTION_API_KEY, array( __CLASS__, 'mask_api_key' ) );
		$value = get_option( self::OPTION_API_KEY, '' );
		add_filter( 'option_' . self::OPTION_API_KEY, array( __CLASS__, 'mask_api_key' ) );

		return (string) $value;
	}

	/**
	 * Sanitize capabilities array.
	 *
	 * @param mixed $value The raw capabilities value.
	 * @return array The sanitized capabilities.
	 */
	public static function sanitize_capabilities( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = array(
			'text_generation',
			'image_generation',
			'chat_history',
			'embedding_generation',
			'text_to_speech_conversion',
		);

		return array_values( array_intersect( $value, $allowed ) );
	}
}

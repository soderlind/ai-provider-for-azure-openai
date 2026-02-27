<?php
/**
 * Settings Manager for Azure OpenAI Provider.
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
	 * Option name for storing settings.
	 */
	const OPTION_NAME = 'wp_ai_provider_azure_openai_settings';

	/**
	 * Option group for settings registration.
	 */
	const OPTION_GROUP = 'azure-openai-provider-settings';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'azure-openai-provider';

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
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private ?array $settings = null;

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
	 * Initialize settings registration.
	 *
	 * @return void
	 */
	public function init(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'azure_openai_credentials',
			__( 'Azure OpenAI Credentials', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_section_description' ),
			self::PAGE_SLUG
		);

		$this->add_settings_fields();
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Azure OpenAI Settings', 'ai-provider-for-azure-openai' ),
			__( 'Azure OpenAI', 'ai-provider-for-azure-openai' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Add settings fields.
	 *
	 * @return void
	 */
	private function add_settings_fields(): void {
		add_settings_field(
			'azure_openai_api_key',
			__( 'API Key', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE_SLUG,
			'azure_openai_credentials',
			array( 'label_for' => 'azure_openai_api_key' )
		);

		add_settings_field(
			'azure_openai_endpoint',
			__( 'Endpoint URL', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_endpoint_field' ),
			self::PAGE_SLUG,
			'azure_openai_credentials',
			array( 'label_for' => 'azure_openai_endpoint' )
		);

		add_settings_field(
			'azure_openai_api_version',
			__( 'API Version', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_api_version_field' ),
			self::PAGE_SLUG,
			'azure_openai_credentials',
			array( 'label_for' => 'azure_openai_api_version' )
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array The settings array.
	 */
	public function get_settings(): array {
		if ( null === $this->settings ) {
			$this->settings = get_option( self::OPTION_NAME, array() );
		}
		return $this->settings;
	}

	/**
	 * Get the API key with environment variable fallback.
	 *
	 * @return string The API key.
	 */
	public function get_api_key(): string {
		$settings = $this->get_settings();
		$value    = $settings[ 'api_key' ] ?? '';

		if ( empty( $value ) ) {
			$env_value = getenv( 'AZURE_OPENAI_API_KEY' );
			if ( false !== $env_value && '' !== $env_value ) {
				$value = $env_value;
			}
		}

		return $value;
	}

	/**
	 * Get the endpoint URL with environment variable fallback.
	 *
	 * @return string The endpoint URL.
	 */
	public function get_endpoint(): string {
		$settings = $this->get_settings();
		$value    = $settings[ 'endpoint' ] ?? '';

		if ( empty( $value ) ) {
			$env_value = getenv( 'AZURE_OPENAI_ENDPOINT' );
			if ( false !== $env_value && '' !== $env_value ) {
				$value = $env_value;
			}
		}

		return $value;
	}

	/**
	 * Get the API version with environment variable fallback.
	 *
	 * @return string The API version.
	 */
	public function get_api_version(): string {
		$settings = $this->get_settings();
		$value    = $settings[ 'api_version' ] ?? '';

		if ( empty( $value ) ) {
			$env_value = getenv( 'AZURE_OPENAI_API_VERSION' );
			if ( false !== $env_value && '' !== $env_value ) {
				$value = $env_value;
			}
		}

		// Use default if still empty.
		if ( empty( $value ) ) {
			$value = self::DEFAULT_API_VERSION;
		}

		return $value;
	}

	/**
	 * Check if all required settings are configured.
	 *
	 * @return bool True if configured.
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_api_key() ) && ! empty( $this->get_endpoint() );
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param array $input The input settings.
	 * @return array The sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		$sanitized = array();

		if ( isset( $input[ 'api_key' ] ) ) {
			$sanitized[ 'api_key' ] = sanitize_text_field( $input[ 'api_key' ] );
		}

		if ( isset( $input[ 'endpoint' ] ) ) {
			$sanitized[ 'endpoint' ] = esc_url_raw( $input[ 'endpoint' ] );
		}

		if ( isset( $input[ 'api_version' ] ) ) {
			$sanitized[ 'api_version' ] = sanitize_text_field( $input[ 'api_version' ] );
		}

		// Clear cached settings.
		$this->settings = null;

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for settings saved message.
		if ( isset( $_GET[ 'settings-updated' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error(
				self::OPTION_NAME,
				'settings_updated',
				__( 'Settings saved.', 'ai-provider-for-azure-openai' ),
				'updated'
			);
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( self::OPTION_NAME ); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'ai-provider-for-azure-openai' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the section description.
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		?>
		<p>
			<?php
			printf(
				/* translators: %s: Azure Portal URL */
				esc_html__( 'Configure your Azure OpenAI credentials. You can get these from the %s.', 'ai-provider-for-azure-openai' ),
				'<a href="https://portal.azure.com/#view/Microsoft_Azure_ProjectOxford/CognitiveServicesHub/~/OpenAI" target="_blank" rel="noopener noreferrer">' .
				esc_html__( 'Azure Portal', 'ai-provider-for-azure-openai' ) .
				'</a>'
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Settings can also be configured via environment variables: AZURE_OPENAI_API_KEY, AZURE_OPENAI_ENDPOINT, AZURE_OPENAI_API_VERSION', 'ai-provider-for-azure-openai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the API key field.
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$settings   = $this->get_settings();
		$value      = $settings[ 'api_key' ] ?? '';
		$has_env    = false !== getenv( 'AZURE_OPENAI_API_KEY' ) && '' !== getenv( 'AZURE_OPENAI_API_KEY' );
		$show_value = ! empty( $value ) ? str_repeat( '*', 20 ) . substr( $value, -4 ) : '';

		?>
		<input type="password" id="azure_openai_api_key" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]"
			value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off" />
		<?php if ( $has_env && empty( $value ) ) : ?>
			<p class="description">
				<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
				<?php esc_html_e( 'Using value from AZURE_OPENAI_API_KEY environment variable.', 'ai-provider-for-azure-openai' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Your Azure OpenAI API key. Found in Azure Portal under your OpenAI resource > Keys and Endpoint.', 'ai-provider-for-azure-openai' ); ?>
			</p>
		<?php endif; ?>
	<?php
	}

	/**
	 * Render the endpoint field.
	 *
	 * @return void
	 */
	public function render_endpoint_field(): void {
		$settings = $this->get_settings();
		$value    = $settings[ 'endpoint' ] ?? '';
		$has_env  = false !== getenv( 'AZURE_OPENAI_ENDPOINT' ) && '' !== getenv( 'AZURE_OPENAI_ENDPOINT' );

		?>
		<input type="url" id="azure_openai_endpoint" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[endpoint]"
			value="<?php echo esc_attr( $value ); ?>" class="regular-text"
			placeholder="https://your-resource.openai.azure.com" />
		<?php if ( $has_env && empty( $value ) ) : ?>
			<p class="description">
				<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
				<?php esc_html_e( 'Using value from AZURE_OPENAI_ENDPOINT environment variable.', 'ai-provider-for-azure-openai' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Your Azure OpenAI endpoint URL (e.g., https://your-resource.openai.azure.com).', 'ai-provider-for-azure-openai' ); ?>
			</p>
		<?php endif; ?>
	<?php
	}

	/**
	 * Render the API version field.
	 *
	 * @return void
	 */
	public function render_api_version_field(): void {
		$settings = $this->get_settings();
		$value    = $settings[ 'api_version' ] ?? '';
		$has_env  = false !== getenv( 'AZURE_OPENAI_API_VERSION' ) && '' !== getenv( 'AZURE_OPENAI_API_VERSION' );

		?>
		<input type="text" id="azure_openai_api_version" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_version]"
			value="<?php echo esc_attr( $value ); ?>" class="regular-text"
			placeholder="<?php echo esc_attr( self::DEFAULT_API_VERSION ); ?>" />
		<?php if ( $has_env && empty( $value ) ) : ?>
			<p class="description">
				<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
				<?php esc_html_e( 'Using value from AZURE_OPENAI_API_VERSION environment variable.', 'ai-provider-for-azure-openai' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: default API version */
					esc_html__( 'Azure OpenAI API version (default: %s).', 'ai-provider-for-azure-openai' ),
					esc_html( self::DEFAULT_API_VERSION )
				);
				?>
				<a href="https://learn.microsoft.com/azure/ai-services/openai/reference" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View available versions', 'ai-provider-for-azure-openai' ); ?>
				</a>
			</p>
		<?php endif; ?>
	<?php
	}
}

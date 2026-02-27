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
	 * Initialize settings - hooks into admin_menu and admin_init.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_missing_settings_notice' ) );
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
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
			'',
			array( $this, 'render_section_description' ),
			self::PAGE_SLUG
		);

		$this->add_settings_fields();
	}

	/**
	 * Add settings fields.
	 *
	 * @return void
	 */
	private function add_settings_fields(): void {
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

		add_settings_field(
			'azure_openai_deployment_id',
			__( 'Deployment ID', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_deployment_id_field' ),
			self::PAGE_SLUG,
			'azure_openai_credentials',
			array( 'label_for' => 'azure_openai_deployment_id' )
		);

		add_settings_field(
			'azure_openai_capabilities',
			__( 'Capabilities', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_capabilities_field' ),
			self::PAGE_SLUG,
			'azure_openai_credentials'
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
	 * Get the deployment ID with environment variable fallback.
	 *
	 * @return string The deployment ID.
	 */
	public function get_deployment_id(): string {
		$settings = $this->get_settings();
		$value    = $settings[ 'deployment_id' ] ?? '';

		if ( empty( $value ) ) {
			$env_value = getenv( 'AZURE_OPENAI_DEPLOYMENT_ID' );
			if ( false !== $env_value && '' !== $env_value ) {
				$value = $env_value;
			}
		}

		return $value;
	}

	/**
	 * Get the capabilities setting.
	 *
	 * @return array The enabled capabilities.
	 */
	public function get_capabilities(): array {
		$settings = $this->get_settings();
		$value    = $settings[ 'capabilities' ] ?? array();

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

		// Check for API key in wp-ai-client credentials or env.
		$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
		$api_key     = $credentials[ 'azure-openai' ] ?? '';
		if ( empty( $api_key ) ) {
			$env_key = getenv( 'AZURE_OPENAI_API_KEY' );
			if ( false === $env_key || '' === $env_key ) {
				$missing[] = __( 'API Key (set in AI Client settings)', 'ai-provider-for-azure-openai' );
			}
		}

		return $missing;
	}

	/**
	 * Show an admin notice when required settings are missing.
	 *
	 * Only displays to users who can manage options and only on admin pages
	 * (not on the plugin's own settings page, where the fields are visible).
	 *
	 * @return void
	 */
	public function maybe_show_missing_settings_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't show on our own settings page — the fields are right there.
		$screen = get_current_screen();
		if ( $screen && 'settings_page_' . self::PAGE_SLUG === $screen->id ) {
			return;
		}

		$missing = $this->get_missing_settings();

		if ( empty( $missing ) ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s %s <a href="%s">%s</a></p></div>',
			esc_html__( 'AI Provider for Azure OpenAI:', 'ai-provider-for-azure-openai' ),
			esc_html__( 'Missing required settings —', 'ai-provider-for-azure-openai' ),
			esc_html( implode( ', ', $missing ) ) . '.',
			esc_url( $settings_url ),
			esc_html__( 'Configure now', 'ai-provider-for-azure-openai' )
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param array $input The input settings.
	 * @return array The sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		$sanitized = array();

		if ( isset( $input[ 'endpoint' ] ) ) {
			$sanitized[ 'endpoint' ] = esc_url_raw( $input[ 'endpoint' ] );
		}

		if ( isset( $input[ 'api_version' ] ) ) {
			$sanitized[ 'api_version' ] = sanitize_text_field( $input[ 'api_version' ] );
		}

		if ( isset( $input[ 'deployment_id' ] ) ) {
			$sanitized[ 'deployment_id' ] = sanitize_text_field( $input[ 'deployment_id' ] );
		}

		// Sanitize capabilities (array of checkbox values).
		if ( isset( $input[ 'capabilities' ] ) && is_array( $input[ 'capabilities' ] ) ) {
			$allowed                   = array( 'text_generation', 'image_generation', 'chat_history', 'embedding_generation', 'text_to_speech_conversion' );
			$sanitized[ 'capabilities' ] = array_intersect( $input[ 'capabilities' ], $allowed );
		} else {
			$sanitized[ 'capabilities' ] = array();
		}

		// Clear cached settings.
		$this->settings = null;

		return $sanitized;
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Azure OpenAI Provider', 'ai-provider-for-azure-openai' ),
			__( 'Azure OpenAI', 'ai-provider-for-azure-openai' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
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

			<hr />
			<h2><?php esc_html_e( 'API Key', 'ai-provider-for-azure-openai' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: link to wp-ai-client settings */
					esc_html__( 'The API key is configured in the %s settings page.', 'ai-provider-for-azure-openai' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=wp-ai-client' ) ) . '">' .
					esc_html__( 'AI Client', 'ai-provider-for-azure-openai' ) .
					'</a>'
				);
				?>
			</p>
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
				esc_html__( 'Configure your Azure OpenAI settings. You can get these from the %s.', 'ai-provider-for-azure-openai' ),
				'<a href="https://portal.azure.com/#view/Microsoft_Azure_ProjectOxford/CognitiveServicesHub/~/OpenAI" target="_blank" rel="noopener noreferrer">' .
				esc_html__( 'Azure Portal', 'ai-provider-for-azure-openai' ) .
				'</a>'
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Settings can also be configured via environment variables: AZURE_OPENAI_ENDPOINT, AZURE_OPENAI_API_VERSION, AZURE_OPENAI_DEPLOYMENT_ID', 'ai-provider-for-azure-openai' ); ?>
		</p>
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

	/**
	 * Render the deployment ID field.
	 *
	 * @return void
	 */
	public function render_deployment_id_field(): void {
		$settings = $this->get_settings();
		$value    = $settings[ 'deployment_id' ] ?? '';
		$has_env  = false !== getenv( 'AZURE_OPENAI_DEPLOYMENT_ID' ) && '' !== getenv( 'AZURE_OPENAI_DEPLOYMENT_ID' );

		?>
		<input type="text" id="azure_openai_deployment_id" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[deployment_id]"
			value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="gpt-4o" />
		<?php if ( $has_env && empty( $value ) ) : ?>
			<p class="description">
				<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
				<?php esc_html_e( 'Using value from AZURE_OPENAI_DEPLOYMENT_ID environment variable.', 'ai-provider-for-azure-openai' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'The name of your Azure OpenAI deployment (e.g., gpt-4o, my-gpt-deployment). This is the deployment name you created in Azure Portal.', 'ai-provider-for-azure-openai' ); ?>
			</p>
		<?php endif; ?>
	<?php
	}

	/**
	 * Render the capabilities field.
	 *
	 * @return void
	 */
	public function render_capabilities_field(): void {
		$capabilities = $this->get_capabilities();

		$options = array(
			'text_generation'           => __( 'Text Generation (GPT models)', 'ai-provider-for-azure-openai' ),
			'chat_history'              => __( 'Chat History (conversation context)', 'ai-provider-for-azure-openai' ),
			'image_generation'          => __( 'Image Generation (DALL-E models)', 'ai-provider-for-azure-openai' ),
			'embedding_generation'      => __( 'Embedding Generation (text-embedding models)', 'ai-provider-for-azure-openai' ),
			'text_to_speech_conversion' => __( 'Text-to-Speech (tts-1, tts-1-hd models)', 'ai-provider-for-azure-openai' ),
		);

		?>
		<fieldset>
			<?php foreach ( $options as $key => $label ) : ?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[capabilities][]"
						value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $capabilities, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label><br />
			<?php endforeach; ?>
		</fieldset>
		<p class="description">
			<?php esc_html_e( 'Select the capabilities supported by your Azure OpenAI deployment. This depends on the model deployed (e.g., GPT-4 supports text generation, DALL-E supports image generation).', 'ai-provider-for-azure-openai' ); ?>
		</p>
		<?php
	}
}

<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

/*
 * Define supports_connectors() in the plugin namespace FIRST.
 *
 * The real implementation was removed when WP < 7.0 support was dropped.
 * The Settings_Manager no longer calls this function, but it is kept
 * here so that any lingering references do not cause fatal errors
 * during tests.
 *
 * Must be in the first namespace block because PHP requires namespace
 * declarations before any other statements.
 */
namespace WordPress\AzureOpenAiAiProvider {
}

// Global namespace: autoloaders and the base test case.
namespace {

	// Composer autoloader.
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';

	// Plugin autoloader.
	require_once dirname( __DIR__ ) . '/src/autoload.php';

	use Brain\Monkey;

	/**
	 * Base test case for Brain Monkey tests.
	 */
	abstract class AzureOpenAiTestCase extends \PHPUnit\Framework\TestCase {

		/**
		 * Set up Brain Monkey before each test.
		 *
		 * @return void
		 */
		protected function setUp(): void {
			parent::setUp();
			Monkey\setUp();

			// Define common WordPress functions.
			$this->define_wp_functions();
		}

		/**
		 * Tear down Brain Monkey after each test.
		 *
		 * @return void
		 */
		protected function tearDown(): void {
			Monkey\tearDown();
			parent::tearDown();

			// Reset singleton for Settings_Manager.
			$this->reset_settings_manager_singleton();
		}

		/**
		 * Define common WordPress functions that are always needed.
		 *
		 * @return void
		 */
		protected function define_wp_functions(): void {
			// Translation functions - return input unchanged.
			Monkey\Functions\stubs(
				array(
					'__'           => static function ( $text, $domain = 'default' ) {
						return $text;
					},
					'_e'           => static function ( $text, $domain = 'default' ) {
						echo $text;
					},
					'esc_html__'   => static function ( $text, $domain = 'default' ) {
						return $text;
					},
					'esc_html_e'   => static function ( $text, $domain = 'default' ) {
						echo $text;
					},
					'esc_attr__'   => static function ( $text, $domain = 'default' ) {
						return $text;
					},
					'esc_html'     => static function ( $text ) {
						return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
					},
					'esc_attr'     => static function ( $text ) {
						return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
					},
					'esc_url'      => static function ( $url ) {
						return filter_var( $url, FILTER_SANITIZE_URL );
					},
					'esc_url_raw'  => static function ( $url ) {
						return filter_var( $url, FILTER_SANITIZE_URL );
					},
					'wp_kses_post' => static function ( $data ) {
						return $data;
					},
				)
			);

			// Sanitization functions.
			Monkey\Functions\stubs(
				array(
					'sanitize_text_field' => static function ( $str ) {
						return trim( strip_tags( $str ) );
					},
					'absint'              => static function ( $val ) {
						return abs( intval( $val ) );
					},
				)
			);

			// WordPress info function — defaults to WP 7.0.
			Monkey\Functions\stubs(
				array(
					'get_bloginfo' => static function ( $show = '' ) {
						return 'version' === $show ? '7.0' : '';
					},
				)
			);
		}

		/**
		 * Reset the Settings_Manager singleton for test isolation.
		 *
		 * @return void
		 */
		protected function reset_settings_manager_singleton(): void {
			$reflection = new \ReflectionClass( \WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager::class );
			$property   = $reflection->getProperty( 'instance' );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}

		/**
		 * Set up environment variable for testing.
		 *
		 * @param string $name  Environment variable name.
		 * @param string $value Environment variable value.
		 * @return void
		 */
		protected function set_env( string $name, string $value ): void {
			putenv( "{$name}={$value}" );
		}

		/**
		 * Clear environment variable after testing.
		 *
		 * @param string $name Environment variable name.
		 * @return void
		 */
		protected function clear_env( string $name ): void {
			putenv( $name );
		}
	}
} // End global namespace.

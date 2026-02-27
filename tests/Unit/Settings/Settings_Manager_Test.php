<?php
/**
 * Tests for Settings_Manager class.
 *
 * @package WordPress\AzureOpenAiAiProvider\Tests
 */

namespace WordPress\AzureOpenAiAiProvider\Tests\Unit\Settings;

use AzureOpenAiTestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * Settings_Manager test class.
 */
class Settings_Manager_Test extends AzureOpenAiTestCase {

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		// Reset singleton before each test.
		$this->reset_settings_manager_singleton();
		// Clear any env vars.
		putenv( 'AZURE_OPENAI_ENDPOINT' );
		putenv( 'AZURE_OPENAI_API_VERSION' );
		putenv( 'AZURE_OPENAI_DEPLOYMENT_ID' );
	}

	/**
	 * Test that get_instance returns singleton.
	 *
	 * @return void
	 */
	public function test_get_instance_returns_singleton(): void {
		$instance1 = Settings_Manager::get_instance();
		$instance2 = Settings_Manager::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test get_endpoint returns value from settings.
	 *
	 * @return void
	 */
	public function test_get_endpoint_from_settings(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn( array( 'endpoint' => 'https://my-resource.openai.azure.com' ) );

		$manager  = Settings_Manager::get_instance();
		$endpoint = $manager->get_endpoint();

		$this->assertSame( 'https://my-resource.openai.azure.com', $endpoint );
	}

	/**
	 * Test get_endpoint falls back to environment variable.
	 *
	 * @return void
	 */
	public function test_get_endpoint_falls_back_to_env(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn( array() );

		$this->set_env( 'AZURE_OPENAI_ENDPOINT', 'https://env-resource.openai.azure.com' );

		$manager  = Settings_Manager::get_instance();
		$endpoint = $manager->get_endpoint();

		$this->assertSame( 'https://env-resource.openai.azure.com', $endpoint );

		$this->clear_env( 'AZURE_OPENAI_ENDPOINT' );
	}

	/**
	 * Test get_api_version returns value from settings.
	 *
	 * @return void
	 */
	public function test_get_api_version_from_settings(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn( array( 'api_version' => '2024-06-01' ) );

		$manager     = Settings_Manager::get_instance();
		$api_version = $manager->get_api_version();

		$this->assertSame( '2024-06-01', $api_version );
	}

	/**
	 * Test get_api_version falls back to environment variable.
	 *
	 * @return void
	 */
	public function test_get_api_version_falls_back_to_env(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn( array() );

		$this->set_env( 'AZURE_OPENAI_API_VERSION', '2024-03-01' );

		$manager     = Settings_Manager::get_instance();
		$api_version = $manager->get_api_version();

		$this->assertSame( '2024-03-01', $api_version );

		$this->clear_env( 'AZURE_OPENAI_API_VERSION' );
	}

	/**
	 * Test get_api_version returns default when not configured.
	 *
	 * @return void
	 */
	public function test_get_api_version_returns_default(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn( array() );

		$this->clear_env( 'AZURE_OPENAI_API_VERSION' );

		$manager     = Settings_Manager::get_instance();
		$api_version = $manager->get_api_version();

		$this->assertSame( Settings_Manager::DEFAULT_API_VERSION, $api_version );
	}

	/**
	 * Test is_configured returns true when endpoint is set.
	 *
	 * @return void
	 */
	public function test_is_configured_returns_true_when_configured(): void {
		// get_settings() caches results, so get_option is only called once.
		Functions\expect( 'get_option' )
			->once()
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint' => 'https://test.openai.azure.com',
				)
			);

		$manager = Settings_Manager::get_instance();

		$this->assertTrue( $manager->is_configured() );
	}

	/**
	 * Test is_configured returns false when endpoint is missing.
	 *
	 * @return void
	 */
	public function test_is_configured_returns_false_when_endpoint_missing(): void {
		// Clear any env vars that might provide a fallback.
		putenv( 'AZURE_OPENAI_ENDPOINT' );

		Functions\expect( 'get_option' )
			->once()
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn( array() );

		$manager = Settings_Manager::get_instance();

		$this->assertFalse( $manager->is_configured() );
	}

	/**
	 * Test sanitize_settings sanitizes input properly.
	 *
	 * @return void
	 */
	public function test_sanitize_settings(): void {
		$manager = Settings_Manager::get_instance();

		$input = array(
			'endpoint'      => 'https://my-resource.openai.azure.com',
			'api_version'   => '2024-02-15-preview',
			'deployment_id' => 'gpt-4o',
		);

		$sanitized = $manager->sanitize_settings( $input );

		$this->assertSame( 'https://my-resource.openai.azure.com', $sanitized['endpoint'] );
		$this->assertSame( '2024-02-15-preview', $sanitized['api_version'] );
		$this->assertSame( 'gpt-4o', $sanitized['deployment_id'] );
	}

	/**
	 * Test settings priority: DB settings take precedence over env vars.
	 *
	 * @return void
	 */
	public function test_db_settings_take_precedence_over_env(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn( array( 'endpoint' => 'https://db-resource.openai.azure.com' ) );

		$this->set_env( 'AZURE_OPENAI_ENDPOINT', 'https://env-resource.openai.azure.com' );

		$manager  = Settings_Manager::get_instance();
		$endpoint = $manager->get_endpoint();

		// DB value should be returned, not env.
		$this->assertSame( 'https://db-resource.openai.azure.com', $endpoint );

		$this->clear_env( 'AZURE_OPENAI_ENDPOINT' );
	}
}

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
use WordPress\AzureOpenAiAiProvider\Settings\Connector_Settings;
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
		putenv( 'AZURE_OPENAI_CAPABILITIES' );
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
	 * Test get_endpoint returns value from connector option.
	 *
	 * @return void
	 */
	public function test_get_endpoint_from_connector_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_ENDPOINT, '' )
			->andReturn( 'https://my-resource.openai.azure.com' );

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
			->with( Connector_Settings::OPTION_ENDPOINT, '' )
			->andReturn( '' );

		$this->set_env( 'AZURE_OPENAI_ENDPOINT', 'https://env-resource.openai.azure.com' );

		$manager  = Settings_Manager::get_instance();
		$endpoint = $manager->get_endpoint();

		$this->assertSame( 'https://env-resource.openai.azure.com', $endpoint );

		$this->clear_env( 'AZURE_OPENAI_ENDPOINT' );
	}

	/**
	 * Test get_api_version returns value from connector option.
	 *
	 * @return void
	 */
	public function test_get_api_version_from_connector_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_API_VERSION, '' )
			->andReturn( '2024-06-01' );

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
			->with( Connector_Settings::OPTION_API_VERSION, '' )
			->andReturn( '' );

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
			->with( Connector_Settings::OPTION_API_VERSION, '' )
			->andReturn( '' );

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
		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_ENDPOINT, '' )
			->andReturn( 'https://test.openai.azure.com' );

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
			->with( Connector_Settings::OPTION_ENDPOINT, '' )
			->andReturn( '' );

		$manager = Settings_Manager::get_instance();

		$this->assertFalse( $manager->is_configured() );
	}

	/**
	 * Test settings priority: DB option takes precedence over env vars.
	 *
	 * @return void
	 */
	public function test_db_settings_take_precedence_over_env(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_ENDPOINT, '' )
			->andReturn( 'https://db-resource.openai.azure.com' );

		$this->set_env( 'AZURE_OPENAI_ENDPOINT', 'https://env-resource.openai.azure.com' );

		$manager  = Settings_Manager::get_instance();
		$endpoint = $manager->get_endpoint();

		// DB value should be returned, not env.
		$this->assertSame( 'https://db-resource.openai.azure.com', $endpoint );

		$this->clear_env( 'AZURE_OPENAI_ENDPOINT' );
	}

	// ------------------------------------------------------------------
	// get_capabilities
	// ------------------------------------------------------------------

	/**
	 * Test get_capabilities returns value from connector option.
	 *
	 * @return void
	 */
	public function test_get_capabilities_from_connector_option(): void {
		$caps = array( 'text_generation', 'image_generation' );

		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_CAPABILITIES, array() )
			->andReturn( $caps );

		$manager = Settings_Manager::get_instance();

		$this->assertSame( $caps, $manager->get_capabilities() );
	}

	/**
	 * Test get_capabilities falls back to environment variable.
	 *
	 * @return void
	 */
	public function test_get_capabilities_falls_back_to_env(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_CAPABILITIES, array() )
			->andReturn( array() );

		$this->set_env( 'AZURE_OPENAI_CAPABILITIES', 'text_generation,image_generation,chat_history' );

		$manager = Settings_Manager::get_instance();

		$this->assertSame(
			array( 'text_generation', 'image_generation', 'chat_history' ),
			$manager->get_capabilities()
		);

		$this->clear_env( 'AZURE_OPENAI_CAPABILITIES' );
	}

	/**
	 * Test get_capabilities env var filters invalid values.
	 *
	 * @return void
	 */
	public function test_get_capabilities_env_filters_invalid(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_CAPABILITIES, array() )
			->andReturn( array() );

		$this->set_env( 'AZURE_OPENAI_CAPABILITIES', 'text_generation,bogus_cap,chat_history' );

		$manager = Settings_Manager::get_instance();

		$this->assertSame(
			array( 'text_generation', 'chat_history' ),
			$manager->get_capabilities()
		);

		$this->clear_env( 'AZURE_OPENAI_CAPABILITIES' );
	}

	/**
	 * Test get_capabilities env var trims whitespace around values.
	 *
	 * @return void
	 */
	public function test_get_capabilities_env_trims_whitespace(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_CAPABILITIES, array() )
			->andReturn( array() );

		$this->set_env( 'AZURE_OPENAI_CAPABILITIES', ' text_generation , chat_history ' );

		$manager = Settings_Manager::get_instance();

		$this->assertSame(
			array( 'text_generation', 'chat_history' ),
			$manager->get_capabilities()
		);

		$this->clear_env( 'AZURE_OPENAI_CAPABILITIES' );
	}

	/**
	 * Test get_capabilities returns default when not configured anywhere.
	 *
	 * @return void
	 */
	public function test_get_capabilities_returns_default(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_CAPABILITIES, array() )
			->andReturn( array() );

		$this->clear_env( 'AZURE_OPENAI_CAPABILITIES' );

		$manager = Settings_Manager::get_instance();

		$this->assertSame(
			array( 'text_generation', 'chat_history' ),
			$manager->get_capabilities()
		);
	}

	/**
	 * Test get_capabilities DB option takes precedence over env var.
	 *
	 * @return void
	 */
	public function test_get_capabilities_db_takes_precedence_over_env(): void {
		$db_caps = array( 'image_generation' );

		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_CAPABILITIES, array() )
			->andReturn( $db_caps );

		$this->set_env( 'AZURE_OPENAI_CAPABILITIES', 'text_generation,chat_history' );

		$manager = Settings_Manager::get_instance();

		$this->assertSame( $db_caps, $manager->get_capabilities() );

		$this->clear_env( 'AZURE_OPENAI_CAPABILITIES' );
	}
}

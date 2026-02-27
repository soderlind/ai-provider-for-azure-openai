<?php
/**
 * Tests for AzureOpenAiProvider class.
 *
 * @package WordPress\AzureOpenAiAiProvider\Tests
 */

namespace WordPress\AzureOpenAiAiProvider\Tests\Unit\Provider;

use AzureOpenAiTestCase;
use Brain\Monkey\Functions;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * AzureOpenAiProvider test class.
 */
class AzureOpenAiProvider_Test extends AzureOpenAiTestCase {

	/**
	 * Test deploymentUrl builds correct Azure URL format.
	 *
	 * @return void
	 */
	public function test_deployment_url_builds_correct_format(): void {
		// Mock settings to return test values.
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint'    => 'https://my-resource.openai.azure.com',
					'api_version' => '2024-02-15-preview',
				)
			);

		$url = AzureOpenAiProvider::deploymentUrl( 'gpt-4o', 'chat/completions' );

		$expected = 'https://my-resource.openai.azure.com/openai/deployments/gpt-4o/chat/completions?api-version=2024-02-15-preview';

		$this->assertSame( $expected, $url );
	}

	/**
	 * Test deploymentUrl handles trailing slash in endpoint.
	 *
	 * @return void
	 */
	public function test_deployment_url_handles_trailing_slash(): void {
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint'    => 'https://my-resource.openai.azure.com/',
					'api_version' => '2024-06-01',
				)
			);

		$url = AzureOpenAiProvider::deploymentUrl( 'gpt-35-turbo', 'chat/completions' );

		// Should not have double slashes.
		$this->assertStringNotContainsString( '//', substr( $url, 8 ) ); // Skip https://
		$this->assertStringContainsString( '/openai/deployments/gpt-35-turbo/', $url );
	}

	/**
	 * Test url method builds correct format for deployments listing.
	 *
	 * @return void
	 */
	public function test_url_builds_deployments_list_url(): void {
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint'    => 'https://test-resource.openai.azure.com',
					'api_version' => '2024-02-15-preview',
				)
			);

		$url = AzureOpenAiProvider::url( 'deployments' );

		$expected = 'https://test-resource.openai.azure.com/openai/deployments?api-version=2024-02-15-preview';

		$this->assertSame( $expected, $url );
	}

	/**
	 * Test deploymentUrl returns empty string when endpoint not configured.
	 *
	 * @return void
	 */
	public function test_deployment_url_returns_empty_when_not_configured(): void {
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn( array() );

		$this->clear_env( 'AZURE_OPENAI_ENDPOINT' );

		$url = AzureOpenAiProvider::deploymentUrl( 'gpt-4', 'chat/completions' );

		$this->assertSame( '', $url );
	}

	/**
	 * Test deploymentUrl works with different paths.
	 *
	 * @return void
	 */
	public function test_deployment_url_works_with_different_paths(): void {
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint'    => 'https://my-resource.openai.azure.com',
					'api_version' => '2024-02-15-preview',
				)
			);

		// Test chat completions.
		$chat_url = AzureOpenAiProvider::deploymentUrl( 'gpt-4o', 'chat/completions' );
		$this->assertStringContainsString( '/chat/completions?', $chat_url );

		// Reset singleton for next call.
		$this->reset_settings_manager_singleton();

		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint'    => 'https://my-resource.openai.azure.com',
					'api_version' => '2024-02-15-preview',
				)
			);

		// Test image generations.
		$image_url = AzureOpenAiProvider::deploymentUrl( 'dall-e-3', 'images/generations' );
		$this->assertStringContainsString( '/images/generations?', $image_url );
	}

	/**
	 * Test API version is included in URL query string.
	 *
	 * @return void
	 */
	public function test_api_version_included_in_query_string(): void {
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint'    => 'https://my-resource.openai.azure.com',
					'api_version' => '2024-06-01',
				)
			);

		$url = AzureOpenAiProvider::deploymentUrl( 'gpt-4', 'chat/completions' );

		$this->assertStringContainsString( 'api-version=2024-06-01', $url );
	}

	/**
	 * Test deploymentUrl uses default API version when not set.
	 *
	 * @return void
	 */
	public function test_deployment_url_uses_default_api_version(): void {
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint' => 'https://my-resource.openai.azure.com',
					// No api_version set.
				)
			);

		$this->clear_env( 'AZURE_OPENAI_API_VERSION' );

		$url = AzureOpenAiProvider::deploymentUrl( 'gpt-4', 'chat/completions' );

		$this->assertStringContainsString( 'api-version=' . Settings_Manager::DEFAULT_API_VERSION, $url );
	}
}

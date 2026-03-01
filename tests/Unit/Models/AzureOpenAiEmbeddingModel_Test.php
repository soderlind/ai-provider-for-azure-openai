<?php
/**
 * Tests for AzureOpenAiEmbeddingModel class.
 *
 * @package WordPress\AzureOpenAiAiProvider\Tests
 */

namespace WordPress\AzureOpenAiAiProvider\Tests\Unit\Models;

use AzureOpenAiTestCase;
use Brain\Monkey\Functions;
use WordPress\AzureOpenAiAiProvider\Models\AzureOpenAiEmbeddingModel;
use WordPress\AzureOpenAiAiProvider\Settings\Connector_Settings;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * AzureOpenAiEmbeddingModel test class.
 */
class AzureOpenAiEmbeddingModel_Test extends AzureOpenAiTestCase {

	/**
	 * Test that getDeploymentId uses settings deployment ID when available.
	 *
	 * @return void
	 */
	public function test_get_deployment_id_from_settings(): void {
		Functions\expect( 'get_option' )
			->with( Connector_Settings::OPTION_DEPLOYMENT_ID, '' )
			->andReturn( 'my-embedding-deployment' );

		$model = $this->createEmbeddingModel( 'text-embedding-3-small' );

		$reflection = new \ReflectionMethod( $model, 'getDeploymentId' );
		$reflection->setAccessible( true );

		$this->assertSame( 'my-embedding-deployment', $reflection->invoke( $model ) );
	}

	/**
	 * Test that getDeploymentId falls back to model metadata ID.
	 *
	 * @return void
	 */
	public function test_get_deployment_id_falls_back_to_model_id(): void {
		Functions\expect( 'get_option' )
			->with( Connector_Settings::OPTION_DEPLOYMENT_ID, '' )
			->andReturn( '' );

		$this->clear_env( 'AZURE_OPENAI_DEPLOYMENT_ID' );

		$model = $this->createEmbeddingModel( 'text-embedding-ada-002' );

		$reflection = new \ReflectionMethod( $model, 'getDeploymentId' );
		$reflection->setAccessible( true );

		$this->assertSame( 'text-embedding-ada-002', $reflection->invoke( $model ) );
	}

	/**
	 * Test prepareEmbeddingParams with string input.
	 *
	 * @return void
	 */
	public function test_prepare_embedding_params_with_string(): void {
		$model = $this->createEmbeddingModel( 'text-embedding-3-small' );

		$reflection = new \ReflectionMethod( $model, 'prepareEmbeddingParams' );
		$reflection->setAccessible( true );

		$params = $reflection->invoke( $model, 'Hello world' );

		$this->assertSame( 'Hello world', $params[ 'input' ] );
		$this->assertSame( 'float', $params[ 'encoding_format' ] );
	}

	/**
	 * Test prepareEmbeddingParams with array input.
	 *
	 * @return void
	 */
	public function test_prepare_embedding_params_with_array(): void {
		$model = $this->createEmbeddingModel( 'text-embedding-3-small' );

		$reflection = new \ReflectionMethod( $model, 'prepareEmbeddingParams' );
		$reflection->setAccessible( true );

		$input  = array( 'Hello', 'World' );
		$params = $reflection->invoke( $model, $input );

		$this->assertSame( $input, $params[ 'input' ] );
		$this->assertSame( 'float', $params[ 'encoding_format' ] );
	}

	/**
	 * Create an AzureOpenAiEmbeddingModel instance for testing.
	 *
	 * @param string $model_id The model ID.
	 * @return AzureOpenAiEmbeddingModel The model instance.
	 */
	private function createEmbeddingModel( string $model_id ): AzureOpenAiEmbeddingModel {
		$model_metadata = $this->createMock(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata::class
		);
		$model_metadata->method( 'getId' )->willReturn( $model_id );

		$provider_metadata = $this->createMock(
			\WordPress\AiClient\Providers\DTO\ProviderMetadata::class
		);

		return new AzureOpenAiEmbeddingModel( $model_metadata, $provider_metadata );
	}
}

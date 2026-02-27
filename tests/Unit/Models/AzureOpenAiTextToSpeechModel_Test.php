<?php
/**
 * Tests for AzureOpenAiTextToSpeechModel class.
 *
 * @package WordPress\AzureOpenAiAiProvider\Tests
 */

namespace WordPress\AzureOpenAiAiProvider\Tests\Unit\Models;

use AzureOpenAiTestCase;
use Brain\Monkey\Functions;
use WordPress\AzureOpenAiAiProvider\Models\AzureOpenAiTextToSpeechModel;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * AzureOpenAiTextToSpeechModel test class.
 */
class AzureOpenAiTextToSpeechModel_Test extends AzureOpenAiTestCase {

	/**
	 * Test that getDeploymentId uses settings deployment ID when available.
	 *
	 * @return void
	 */
	public function test_get_deployment_id_from_settings(): void {
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint'      => 'https://my-resource.openai.azure.com',
					'api_version'   => '2024-02-15-preview',
					'deployment_id' => 'my-tts-deployment',
				)
			);

		$model = $this->createTtsModel( 'tts-1' );

		$reflection = new \ReflectionMethod( $model, 'getDeploymentId' );
		$reflection->setAccessible( true );

		$this->assertSame( 'my-tts-deployment', $reflection->invoke( $model ) );
	}

	/**
	 * Test that getDeploymentId falls back to model metadata ID.
	 *
	 * @return void
	 */
	public function test_get_deployment_id_falls_back_to_model_id(): void {
		Functions\expect( 'get_option' )
			->with( Settings_Manager::OPTION_NAME, array() )
			->andReturn(
				array(
					'endpoint'    => 'https://my-resource.openai.azure.com',
					'api_version' => '2024-02-15-preview',
				)
			);

		$this->clear_env( 'AZURE_OPENAI_DEPLOYMENT_ID' );

		$model = $this->createTtsModel( 'tts-1-hd' );

		$reflection = new \ReflectionMethod( $model, 'getDeploymentId' );
		$reflection->setAccessible( true );

		$this->assertSame( 'tts-1-hd', $reflection->invoke( $model ) );
	}

	/**
	 * Test extractTextFromPrompt with string items.
	 *
	 * @return void
	 */
	public function test_extract_text_from_string_prompt(): void {
		$model = $this->createTtsModel( 'tts-1' );

		$reflection = new \ReflectionMethod( $model, 'extractTextFromPrompt' );
		$reflection->setAccessible( true );

		$prompt = array( 'Hello world', 'How are you?' );
		$text   = $reflection->invoke( $model, $prompt );

		$this->assertSame( 'Hello world How are you?', $text );
	}

	/**
	 * Test extractTextFromPrompt with array items containing 'content'.
	 *
	 * @return void
	 */
	public function test_extract_text_from_array_prompt(): void {
		$model = $this->createTtsModel( 'tts-1' );

		$reflection = new \ReflectionMethod( $model, 'extractTextFromPrompt' );
		$reflection->setAccessible( true );

		$prompt = array(
			array( 'role' => 'user', 'content' => 'Convert this to speech' ),
		);
		$text   = $reflection->invoke( $model, $prompt );

		$this->assertSame( 'Convert this to speech', $text );
	}

	/**
	 * Test prepareTtsParams builds correct default parameters.
	 *
	 * @return void
	 */
	public function test_prepare_tts_params_defaults(): void {
		$model = $this->createTtsModel( 'tts-1' );

		$reflection = new \ReflectionMethod( $model, 'prepareTtsParams' );
		$reflection->setAccessible( true );

		$params = $reflection->invoke( $model, 'Hello world' );

		$this->assertSame( 'tts-1', $params[ 'model' ] );
		$this->assertSame( 'Hello world', $params[ 'input' ] );
		$this->assertSame( 'alloy', $params[ 'voice' ] );
		$this->assertSame( 'mp3', $params[ 'response_format' ] );
		$this->assertArrayNotHasKey( 'speed', $params );
	}

	/**
	 * Test that available voices constant is defined correctly.
	 *
	 * @return void
	 */
	public function test_available_voices(): void {
		$expected = array( 'alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer' );
		$this->assertSame( $expected, AzureOpenAiTextToSpeechModel::AVAILABLE_VOICES );
	}

	/**
	 * Test default voice constant.
	 *
	 * @return void
	 */
	public function test_default_voice(): void {
		$this->assertSame( 'alloy', AzureOpenAiTextToSpeechModel::DEFAULT_VOICE );
	}

	/**
	 * Test default response format constant.
	 *
	 * @return void
	 */
	public function test_default_response_format(): void {
		$this->assertSame( 'mp3', AzureOpenAiTextToSpeechModel::DEFAULT_RESPONSE_FORMAT );
	}

	/**
	 * Test that TTS model implements TextToSpeechConversionModelInterface.
	 *
	 * @return void
	 */
	public function test_implements_tts_interface(): void {
		$model = $this->createTtsModel( 'tts-1' );

		$this->assertInstanceOf(
			\WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts\TextToSpeechConversionModelInterface::class,
			$model
		);
	}

	/**
	 * Create an AzureOpenAiTextToSpeechModel instance for testing.
	 *
	 * @param string $model_id The model ID.
	 * @return AzureOpenAiTextToSpeechModel The model instance.
	 */
	private function createTtsModel( string $model_id ): AzureOpenAiTextToSpeechModel {
		$model_metadata = $this->createMock(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata::class
		);
		$model_metadata->method( 'getId' )->willReturn( $model_id );

		$provider_metadata = $this->createMock(
			\WordPress\AiClient\Providers\DTO\ProviderMetadata::class
		);

		return new AzureOpenAiTextToSpeechModel( $model_metadata, $provider_metadata );
	}
}

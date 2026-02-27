<?php
/**
 * Azure OpenAI Text-to-Speech Model.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Models;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts\TextToSpeechConversionModelInterface;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * Azure OpenAI Text-to-Speech Model class.
 *
 * Implements text-to-speech conversion using Azure OpenAI's audio/speech API.
 * Supports TTS models such as tts-1 and tts-1-hd.
 */
class AzureOpenAiTextToSpeechModel extends AbstractApiBasedModel implements TextToSpeechConversionModelInterface {

	/**
	 * Default voice to use for speech synthesis.
	 */
	const DEFAULT_VOICE = 'alloy';

	/**
	 * Default response format for audio output.
	 */
	const DEFAULT_RESPONSE_FORMAT = 'mp3';

	/**
	 * Available voices for Azure OpenAI TTS.
	 */
	const AVAILABLE_VOICES = array( 'alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer' );

	/**
	 * Get the deployment ID to use.
	 *
	 * Uses the configured deployment ID from settings if available,
	 * otherwise falls back to the model metadata ID.
	 *
	 * @return string The deployment ID.
	 */
	protected function getDeploymentId(): string {
		$settings      = Settings_Manager::get_instance();
		$deployment_id = $settings->get_deployment_id();

		if ( ! empty( $deployment_id ) ) {
			return $deployment_id;
		}

		return $this->metadata()->getId();
	}

	/**
	 * Convert text to speech.
	 *
	 * @param array $prompt Array of messages containing the text to convert to speech.
	 * @return GenerativeAiResult Result containing generated speech audio.
	 */
	final public function convertTextToSpeechResult( array $prompt ): GenerativeAiResult {
		$http_transporter = $this->getHttpTransporter();
		$text             = $this->extractTextFromPrompt( $prompt );
		$params           = $this->prepareTtsParams( $text );

		// Build the Azure-specific URL: {endpoint}/openai/deployments/{deployment}/audio/speech?api-version={version}.
		$url = AzureOpenAiProvider::deploymentUrl( $this->getDeploymentId(), 'audio/speech' );

		$request = new Request(
			HttpMethodEnum::POST(),
			$url,
			array( 'Content-Type' => 'application/json' ),
			$params,
			$this->getRequestOptions()
		);

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $http_transporter->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToGenerativeAiResult( $response );
	}

	/**
	 * Extract text content from the prompt messages.
	 *
	 * @param array $prompt The prompt messages.
	 * @return string The concatenated text content.
	 */
	protected function extractTextFromPrompt( array $prompt ): string {
		$texts = array();

		foreach ( $prompt as $item ) {
			if ( is_string( $item ) ) {
				$texts[] = $item;
			} elseif ( is_object( $item ) && method_exists( $item, 'getParts' ) ) {
				// Handle Message DTO objects.
				foreach ( $item->getParts() as $part ) {
					if ( method_exists( $part, 'getText' ) ) {
						$text = $part->getText();
						if ( ! empty( $text ) ) {
							$texts[] = $text;
						}
					}
				}
			} elseif ( is_array( $item ) && isset( $item[ 'content' ] ) ) {
				$texts[] = $item[ 'content' ];
			}
		}

		return implode( ' ', $texts );
	}

	/**
	 * Prepare the parameters for the TTS request.
	 *
	 * @param string $text The text to convert to speech.
	 * @return array The request parameters.
	 */
	protected function prepareTtsParams( string $text ): array {
		$config = $this->getConfig();

		$params = array(
			'model' => $this->metadata()->getId(),
			'input' => $text,
			'voice' => self::DEFAULT_VOICE,
		);

		// Use the voice from config if available.
		$voice = $config->getOutputSpeechVoice();
		if ( ! empty( $voice ) && in_array( $voice, self::AVAILABLE_VOICES, true ) ) {
			$params[ 'voice' ] = $voice;
		}

		// Allow response format and speed overrides via custom options.
		$custom = $config->getCustomOptions();

		if ( isset( $custom[ 'response_format' ] ) ) {
			$params[ 'response_format' ] = sanitize_text_field( $custom[ 'response_format' ] );
		} else {
			$params[ 'response_format' ] = self::DEFAULT_RESPONSE_FORMAT;
		}

		// Allow speed override (0.25 to 4.0).
		if ( isset( $custom[ 'speed' ] ) ) {
			$speed = (float) $custom[ 'speed' ];
			if ( $speed >= 0.25 && $speed <= 4.0 ) {
				$params[ 'speed' ] = $speed;
			}
		}

		return $params;
	}

	/**
	 * Parse the API response into a GenerativeAiResult.
	 *
	 * The TTS API returns raw audio bytes, not JSON.
	 *
	 * @param \WordPress\AiClient\Providers\Http\Response $response The API response.
	 * @return GenerativeAiResult The parsed result.
	 */
	protected function parseResponseToGenerativeAiResult( $response ): GenerativeAiResult {
		$data = $response->getData();

		// The audio/speech endpoint returns raw audio binary data.
		// If getData() returns the raw body, base64 encode it for transport.
		$audio_data = '';
		if ( is_string( $data ) ) {
			$audio_data = base64_encode( $data );
		} elseif ( is_array( $data ) && isset( $data[ 'body' ] ) ) {
			$audio_data = base64_encode( $data[ 'body' ] );
		}

		return new GenerativeAiResult(
			'', // No text content for audio.
			null,
			array(),
			array(
				'audio_base64'    => $audio_data,
				'response_format' => self::DEFAULT_RESPONSE_FORMAT,
				'raw'             => is_array( $data ) ? $data : array(),
			)
		);
	}
}

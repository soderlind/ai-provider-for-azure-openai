<?php
/**
 * Azure OpenAI Image Generation Model.
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
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;

/**
 * Azure OpenAI Image Generation Model class.
 *
 * Implements image generation using Azure OpenAI's DALL-E API.
 */
class AzureOpenAiImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface {

	/**
	 * Generate an image based on the provided prompt.
	 *
	 * @param string $prompt The text prompt for image generation.
	 * @return GenerativeAiResult The generation result.
	 */
	final public function generateImageResult( string $prompt ): GenerativeAiResult {
		$http_transporter = $this->getHttpTransporter();
		$params           = $this->prepareGenerateImageParams( $prompt );

		// Build the Azure-specific URL: {endpoint}/openai/deployments/{model}/images/generations?api-version={version}
		$url = AzureOpenAiProvider::deploymentUrl( $this->metadata()->getId(), 'images/generations' );

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
	 * Prepare the parameters for the image generation request.
	 *
	 * @param string $prompt The text prompt.
	 * @return array The request parameters.
	 */
	protected function prepareGenerateImageParams( string $prompt ): array {
		$config = $this->getConfig();

		$params = array(
			'prompt' => $prompt,
			'n'      => 1,
		);

		// Add size if specified in config.
		$size = $config->getImageSize();
		if ( ! empty( $size ) ) {
			$params[ 'size' ] = $size;
		} else {
			$params[ 'size' ] = '1024x1024'; // Default size.
		}

		// Add quality if specified (for DALL-E 3).
		$quality = $config->getImageQuality();
		if ( ! empty( $quality ) ) {
			$params[ 'quality' ] = $quality;
		}

		// Add style if specified (for DALL-E 3).
		$style = $config->getImageStyle();
		if ( ! empty( $style ) ) {
			$params[ 'style' ] = $style;
		}

		// Response format (url or b64_json).
		$response_format = $config->getImageResponseFormat();
		if ( ! empty( $response_format ) ) {
			$params[ 'response_format' ] = $response_format;
		} else {
			$params[ 'response_format' ] = 'url';
		}

		return $params;
	}

	/**
	 * Parse the API response into a GenerativeAiResult.
	 *
	 * @param \WordPress\AiClient\Providers\Http\Response $response The API response.
	 * @return GenerativeAiResult The parsed result.
	 */
	protected function parseResponseToGenerativeAiResult( $response ): GenerativeAiResult {
		$data = $response->getData();

		$images = array();

		if ( isset( $data[ 'data' ] ) && is_array( $data[ 'data' ] ) ) {
			foreach ( $data[ 'data' ] as $image_data ) {
				if ( isset( $image_data[ 'url' ] ) ) {
					$images[] = array(
						'url'            => $image_data[ 'url' ],
						'revised_prompt' => $image_data[ 'revised_prompt' ] ?? null,
					);
				} elseif ( isset( $image_data[ 'b64_json' ] ) ) {
					$images[] = array(
						'b64_json'       => $image_data[ 'b64_json' ],
						'revised_prompt' => $image_data[ 'revised_prompt' ] ?? null,
					);
				}
			}
		}

		return new GenerativeAiResult(
			'', // No text content for images.
			null,
			array(),
			array(
				'images' => $images,
				'raw'    => $data,
			)
		);
	}
}

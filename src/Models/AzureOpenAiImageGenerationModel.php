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
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * Azure OpenAI Image Generation Model class.
 *
 * Implements image generation using Azure OpenAI's DALL-E API.
 */
class AzureOpenAiImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface {

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
	 * Generate an image based on the provided prompt.
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the image generation prompt.
	 * @return GenerativeAiResult The generation result.
	 */
	final public function generateImageResult( array $prompt ): GenerativeAiResult {
		// Extract text from message parts.
		$text_prompt = '';
		foreach ( $prompt as $message ) {
			foreach ( $message->getParts() as $part ) {
				$text = $part->getText();
				if ( null !== $text ) {
					$text_prompt .= $text;
				}
			}
		}

		$http_transporter = $this->getHttpTransporter();
		$params           = $this->prepareGenerateImageParams( $text_prompt );

		// Build the Azure-specific URL: {endpoint}/openai/deployments/{deployment}/images/generations?api-version={version}..
		$url = AzureOpenAiProvider::deploymentUrl( $this->getDeploymentId(), 'images/generations' );

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

		$mime_type = isset( $params['output_format'] ) && is_string( $params['output_format'] )
			? "image/{$params['output_format']}"
			: 'image/png';

		return $this->parseResponseToGenerativeAiResult( $response, $mime_type );
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

		$candidate_count = $config->getCandidateCount();
		if ( null !== $candidate_count ) {
			$params['n'] = $candidate_count;
		}

		// gpt-image-1 does not support response_format; use output_format instead.
		// DALL-E models use response_format for url vs b64_json.
		$deployment_id    = $this->getDeploymentId();
		$is_gpt_image     = ( strpos( strtolower( $deployment_id ), 'gpt-image' ) !== false );
		$output_file_type = $config->getOutputFileType();

		if ( $is_gpt_image ) {
			// gpt-image-1 always returns base64; output_format controls image type.
			$output_mime_type = $config->getOutputMimeType();
			if ( null !== $output_mime_type ) {
				$params['output_format'] = preg_replace( '/^image\//', '', $output_mime_type );
			}
		} else {
			// DALL-E models use response_format.
			if ( null !== $output_file_type ) {
				$params['response_format'] = $output_file_type->isRemote() ? 'url' : 'b64_json';
			} else {
				$params['response_format'] = 'b64_json';
			}
		}

		$output_media_orientation = $config->getOutputMediaOrientation();
		$output_media_aspect_ratio = $config->getOutputMediaAspectRatio();
		if ( null !== $output_media_orientation || null !== $output_media_aspect_ratio ) {
			$params['size'] = $this->prepareSizeParam( $output_media_orientation, $output_media_aspect_ratio );
		} else {
			$params['size'] = '1024x1024';
		}

		// Custom options allow developers to pass provider-specific parameters.
		$custom_options = $config->getCustomOptions();
		foreach ( $custom_options as $key => $value ) {
			if ( ! isset( $params[ $key ] ) ) {
				$params[ $key ] = $value;
			}
		}

		return $params;
	}

	/**
	 * Prepare the size parameter from orientation/aspect ratio.
	 *
	 * @param \WordPress\AiClient\Files\Enums\MediaOrientationEnum|null $orientation The media orientation.
	 * @param string|null                                                $aspect_ratio The aspect ratio (e.g. '3:2').
	 * @return string The size string (e.g. '1024x1024').
	 */
	protected function prepareSizeParam( $orientation, ?string $aspect_ratio ): string {
		if ( null !== $aspect_ratio ) {
			$sizes = array(
				'1:1' => '1024x1024',
				'3:2' => '1536x1024',
				'7:4' => '1792x1024',
				'2:3' => '1024x1536',
				'4:7' => '1024x1792',
			);
			return $sizes[ $aspect_ratio ] ?? '1024x1024';
		}

		if ( null !== $orientation ) {
			if ( $orientation->isLandscape() ) {
				return '1536x1024';
			}
			if ( $orientation->isPortrait() ) {
				return '1024x1536';
			}
		}

		return '1024x1024';
	}

	/**
	 * Parse the API response into a GenerativeAiResult.
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The API response.
	 * @param string                                           $expected_mime_type The expected MIME type.
	 * @return GenerativeAiResult The parsed result.
	 */
	protected function parseResponseToGenerativeAiResult( $response, string $expected_mime_type = 'image/png' ): GenerativeAiResult {
		$data = $response->getData();

		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) || empty( $data['data'] ) ) {
			throw new \RuntimeException( 'Azure OpenAI image response missing data array.' );
		}

		$candidates = array();
		foreach ( $data['data'] as $choice_data ) {
			if ( isset( $choice_data['url'] ) && is_string( $choice_data['url'] ) ) {
				$image_file = new File( $choice_data['url'], $expected_mime_type );
			} elseif ( isset( $choice_data['b64_json'] ) && is_string( $choice_data['b64_json'] ) ) {
				$image_file = new File( $choice_data['b64_json'], $expected_mime_type );
			} else {
				continue;
			}

			$parts      = array( new MessagePart( $image_file ) );
			$message    = new Message( MessageRoleEnum::model(), $parts );
			$candidates[] = new Candidate( $message, FinishReasonEnum::stop() );
		}

		$id = isset( $data['id'] ) && is_string( $data['id'] ) ? $data['id'] : '';

		if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$usage      = $data['usage'];
			$token_usage = new TokenUsage(
				$usage['input_tokens'] ?? 0,
				$usage['output_tokens'] ?? 0,
				$usage['total_tokens'] ?? 0
			);
		} else {
			$token_usage = new TokenUsage( 0, 0, 0 );
		}

		$additional = $data;
		unset( $additional['id'], $additional['data'], $additional['usage'] );

		return new GenerativeAiResult(
			$id,
			$candidates,
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			$additional
		);
	}
}

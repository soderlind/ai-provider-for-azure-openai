<?php
/**
 * Azure OpenAI Image Generation Model.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * Azure OpenAI Image Generation Model class.
 *
 * Extends the OpenAI-compatible base class, overriding only the request
 * construction to use Azure-specific deployment URLs.
 *
 * The base class provides the full OpenAI image generation implementation:
 * prompt extraction, parameter building, response parsing, and candidate creation.
 */
class AzureOpenAiImageGenerationModel extends AbstractOpenAiCompatibleImageGenerationModel {

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
	 * Prepares the parameters for the image generation API request.
	 *
	 * Overrides the base class to remove parameters that are unnecessary or
	 * incompatible with Azure OpenAI's deployment-based routing:
	 * - 'model': Azure identifies the model via the deployment URL, not the body.
	 *   gpt-image-1 validates this field and rejects deployment names.
	 * - 'response_format': Azure does not require it for DALL-E, and gpt-image-1
	 *   actively rejects it with a 400 Bad Request error.
	 *
	 * @param array $prompt The prompt messages.
	 * @return array The parameters for the API request.
	 */
	protected function prepareGenerateImageParams( array $prompt ): array {
		$params = parent::prepareGenerateImageParams( $prompt );

		unset( $params['model'], $params['response_format'] );

		return $params;
	}

	/**
	 * Create a request for the Azure OpenAI API.
	 *
	 * Overrides the path to use Azure's deployment-based URL structure:
	 * {endpoint}/openai/deployments/{deployment}/{path}?api-version={version}
	 *
	 * @param HttpMethodEnum $method  The HTTP method.
	 * @param string         $path    The API path (e.g., 'images/generations').
	 * @param array          $headers Optional headers.
	 * @param mixed          $data    Optional request data.
	 * @return Request The request object.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$url = AzureOpenAiProvider::deploymentUrl( $this->getDeploymentId(), $path );

		return new Request(
			$method,
			$url,
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}

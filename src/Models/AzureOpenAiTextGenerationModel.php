<?php
/**
 * Azure OpenAI Text Generation Model.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * Azure OpenAI Text Generation Model class.
 *
 * Extends the OpenAI-compatible base class, overriding only the request
 * construction to use Azure-specific deployment URLs.
 *
 * The base class provides the full OpenAI chat/completions implementation:
 * parameter building, message formatting, response parsing, and tool calls.
 *
 * Note: This also satisfies the v0.3.1 SDK's `streamGenerateTextResult()`
 * requirement when the AI Experiments plugin is active (see docs/ai-experiments-bugs.md — Issue #2).
 */
class AzureOpenAiTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

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
	 * Create a request for the Azure OpenAI API.
	 *
	 * Overrides the path to use Azure's deployment-based URL structure:
	 * {endpoint}/openai/deployments/{deployment}/{path}?api-version={version}
	 *
	 * @param HttpMethodEnum $method  The HTTP method.
	 * @param string         $path    The API path (e.g., 'chat/completions').
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

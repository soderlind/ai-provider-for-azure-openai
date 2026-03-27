<?php
/**
 * Azure OpenAI Embedding Model.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Models;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * Azure OpenAI Embedding Model class.
 *
 * Implements embedding generation using Azure OpenAI's embeddings API.
 *
 * Note: The WP AI Client does not yet provide an EmbeddingGenerationModelInterface.
 * Once the upstream library adds the interface, this class should implement it.
 * The `generateEmbeddingResult()` method follows the same pattern as other model
 * classes and is ready to satisfy the interface when it becomes available.
 */
class AzureOpenAiEmbeddingModel extends AbstractApiBasedModel {

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
	 * Generate embeddings for the provided input.
	 *
	 * @param string|array<string> $input The text(s) to generate embeddings for.
	 * @return GenerativeAiResult The generation result containing embeddings.
	 */
	final public function generateEmbeddingResult( $input ): GenerativeAiResult {
		$http_transporter = $this->getHttpTransporter();
		$params           = $this->prepareEmbeddingParams( $input );

		// Build the Azure-specific URL: {endpoint}/openai/deployments/{deployment}/embeddings?api-version={version}.
		$url = AzureOpenAiProvider::deploymentUrl( $this->getDeploymentId(), 'embeddings' );

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
	 * Prepare the parameters for the embeddings request.
	 *
	 * @param string|array<string> $input The text(s) to embed.
	 * @return array The request parameters.
	 */
	protected function prepareEmbeddingParams( $input ): array {
		$params = array(
			'input' => $input,
		);

		$config = $this->getConfig();

		// Add encoding format if specified (float or base64).
		$output_mime = $config->getOutputMimeType();
		if ( 'application/base64' === $output_mime ) {
			$params['encoding_format'] = 'base64';
		} else {
			$params['encoding_format'] = 'float';
		}

		return $params;
	}

	/**
	 * Parse the API response into a GenerativeAiResult.
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The API response.
	 * @return GenerativeAiResult The parsed result.
	 */
	protected function parseResponseToGenerativeAiResult( $response ): GenerativeAiResult {
		$data = $response->getData();

		$embeddings = array();

		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			foreach ( $data['data'] as $embedding_data ) {
				$embeddings[] = array(
					'index'     => $embedding_data['index'] ?? 0,
					'embedding' => $embedding_data['embedding'] ?? array(),
				);
			}
		}

		// Extract usage data.
		$prompt_tokens     = 0;
		$completion_tokens = 0;
		$total_tokens      = 0;
		if ( isset( $data['usage'] ) ) {
			$prompt_tokens = $data['usage']['prompt_tokens'] ?? 0;
			$total_tokens  = $data['usage']['total_tokens'] ?? 0;
		}

		// Build a candidate with the embedding data as JSON text.
		$embedding_json = wp_json_encode( $embeddings );
		$message        = new Message(
			MessageRoleEnum::model(),
			array( new MessagePart( $embedding_json ?: '[]' ) )
		);
		$candidate = new Candidate( $message, FinishReasonEnum::stop() );

		return new GenerativeAiResult(
			'',
			array( $candidate ),
			new TokenUsage( $prompt_tokens, $completion_tokens, $total_tokens ),
			$this->providerMetadata(),
			$this->metadata(),
			array(
				'embeddings' => $embeddings,
				'model'      => $data['model'] ?? '',
			)
		);
	}
}

<?php
/**
 * Azure OpenAI Model Metadata Directory.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Metadata;

use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * Azure OpenAI Model Metadata Directory class.
 *
 * Provides model discovery and capability mapping for Azure OpenAI deployments.
 * Unlike standard OpenAI, Azure requires models to be pre-deployed, so this class
 * fetches deployments from the Azure OpenAI resource.
 */
class AzureOpenAiModelMetadataDirectory implements ModelMetadataDirectoryInterface {

	/**
	 * Cached model metadata list.
	 *
	 * @var array|null
	 */
	protected ?array $cached_models = null;

	/**
	 * Get all available model metadata.
	 *
	 * @return array<ModelMetadata> The list of model metadata.
	 */
	public function getAll(): array {
		if ( null !== $this->cached_models ) {
			return $this->cached_models;
		}

		$this->cached_models = $this->fetchDeployments();
		return $this->cached_models;
	}

	/**
	 * Get model metadata by ID.
	 *
	 * @param string $id The model/deployment ID.
	 * @return ModelMetadata|null The model metadata or null if not found.
	 */
	public function get( string $id ): ?ModelMetadata {
		$models = $this->getAll();

		foreach ( $models as $model ) {
			if ( $model->getId() === $id ) {
				return $model;
			}
		}

		return null;
	}

	/**
	 * Check if a model exists by ID.
	 *
	 * @param string $id The model/deployment ID.
	 * @return bool True if the model exists.
	 */
	public function has( string $id ): bool {
		return null !== $this->get( $id );
	}

	/**
	 * Create a request for the Azure API.
	 *
	 * @param HttpMethodEnum $method  The HTTP method.
	 * @param string         $path    The API path.
	 * @param array          $headers Optional headers.
	 * @param mixed          $data    Optional request data.
	 * @return Request The request object.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$url = AzureOpenAiProvider::url( $path );
		return new Request( $method, $url, $headers, $data );
	}

	/**
	 * Fetch deployments from Azure OpenAI.
	 *
	 * @return array<ModelMetadata> The list of model metadata.
	 */
	protected function fetchDeployments(): array {
		$settings = Settings_Manager::get_instance();
		$endpoint = $settings->get_endpoint();
		$api_key  = $settings->get_api_key();

		// If no credentials configured, return empty list.
		if ( empty( $endpoint ) || empty( $api_key ) ) {
			return array();
		}

		// Try to fetch deployments from Azure.
		try {
			$url = AzureOpenAiProvider::url( 'deployments' );

			$response = wp_remote_get(
				$url,
				array(
					'headers' => array(
						'api-key'      => $api_key,
						'Content-Type' => 'application/json',
					),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $this->getStaticModelList();
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code ) {
				return $this->getStaticModelList();
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! isset( $data[ 'data' ] ) || ! is_array( $data[ 'data' ] ) ) {
				return $this->getStaticModelList();
			}

			return $this->parseDeploymentsToModelMetadata( $data[ 'data' ] );

		} catch (\Exception $e) {
			// Fall back to static list on error.
			return $this->getStaticModelList();
		}
	}

	/**
	 * Parse Azure deployments response into model metadata.
	 *
	 * @param array $deployments The deployments data.
	 * @return array<ModelMetadata> The model metadata list.
	 */
	protected function parseDeploymentsToModelMetadata( array $deployments ): array {
		$models = array();

		foreach ( $deployments as $deployment ) {
			$deployment_id   = $deployment[ 'id' ] ?? '';
			$model_name      = $deployment[ 'model' ] ?? $deployment_id;
			$deployment_name = $deployment[ 'id' ] ?? $deployment[ 'name' ] ?? '';

			if ( empty( $deployment_name ) ) {
				continue;
			}

			$capabilities = $this->getCapabilitiesForModel( $model_name );

			$models[] = new ModelMetadata(
				$deployment_name,
				$deployment_name,
				$capabilities,
				array(
					'model'  => $model_name,
					'status' => $deployment[ 'status' ] ?? 'unknown',
				)
			);
		}

		return $models;
	}

	/**
	 * Get a static list of common Azure OpenAI models.
	 *
	 * Used as fallback when deployments API is not accessible.
	 *
	 * @return array<ModelMetadata> The model metadata list.
	 */
	protected function getStaticModelList(): array {
		$common_models = array(
			// GPT-4 variants.
			'gpt-4'            => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			'gpt-4-turbo'      => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			'gpt-4o'           => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			'gpt-4o-mini'      => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			// GPT-3.5 variants.
			'gpt-35-turbo'     => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			'gpt-35-turbo-16k' => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			// Image generation.
			'dall-e-3'         => array( CapabilityEnum::imageGeneration() ),
			'dall-e-2'         => array( CapabilityEnum::imageGeneration() ),
		);

		$models = array();

		foreach ( $common_models as $model_id => $capabilities ) {
			$models[] = new ModelMetadata(
				$model_id,
				$model_id,
				$capabilities,
				array( 'static' => true )
			);
		}

		return $models;
	}

	/**
	 * Determine capabilities based on the model name.
	 *
	 * @param string $model_name The model name.
	 * @return array<CapabilityEnum> The capabilities.
	 */
	protected function getCapabilitiesForModel( string $model_name ): array {
		$model_lower = strtolower( $model_name );

		// Image generation models.
		if ( strpos( $model_lower, 'dall-e' ) !== false ) {
			return array( CapabilityEnum::imageGeneration() );
		}

		// Text/chat models.
		if (
			strpos( $model_lower, 'gpt' ) !== false ||
			strpos( $model_lower, 'text-' ) !== false ||
			strpos( $model_lower, 'chat' ) !== false
		) {
			return array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() );
		}

		// Embedding models.
		if ( strpos( $model_lower, 'embedding' ) !== false || strpos( $model_lower, 'ada' ) !== false ) {
			return array( CapabilityEnum::embedding() );
		}

		// Default to text generation.
		return array( CapabilityEnum::textGeneration() );
	}
}

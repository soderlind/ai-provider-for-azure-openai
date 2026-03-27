<?php
/**
 * Azure OpenAI Model Metadata Directory.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Metadata;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;
use WordPress\AzureOpenAiAiProvider\Settings\Connector_Settings;
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
	 * Lists all available model metadata.
	 *
	 * @return list<ModelMetadata> Array of model metadata.
	 */
	public function listModelMetadata(): array {
		if ( null !== $this->cached_models ) {
			return $this->cached_models;
		}

		// Check if user has configured a specific deployment with capabilities.
		$settings      = Settings_Manager::get_instance();
		$deployment_id = $settings->get_deployment_id();

		if ( ! empty( $deployment_id ) ) {
			// Use user-configured deployment with their specified capabilities.
			$this->cached_models = $this->getConfiguredDeployment();
			return $this->cached_models;
		}

		// Otherwise, try to fetch from Azure or use static list.
		$this->cached_models = $this->fetchDeployments();
		return $this->cached_models;
	}

	/**
	 * Get the user-configured deployment as model metadata.
	 *
	 * @return array<ModelMetadata> The model metadata list.
	 */
	protected function getConfiguredDeployment(): array {
		$settings      = Settings_Manager::get_instance();
		$deployment_id = $settings->get_deployment_id();
		$capabilities  = $settings->get_capabilities();

		if ( empty( $deployment_id ) ) {
			return array();
		}

		// Convert settings capability strings to CapabilityEnum.
		$capability_enums = array();
		foreach ( $capabilities as $cap ) {
			switch ( $cap ) {
				case 'text_generation':
					$capability_enums[] = CapabilityEnum::textGeneration();
					break;
				case 'image_generation':
					$capability_enums[] = CapabilityEnum::imageGeneration();
					break;
				case 'chat_history':
					$capability_enums[] = CapabilityEnum::chatHistory();
					break;
				case 'embedding_generation':
					$capability_enums[] = CapabilityEnum::embeddingGeneration();
					break;
				case 'text_to_speech_conversion':
					$capability_enums[] = CapabilityEnum::textToSpeechConversion();
					break;
			}
		}

		// Default to text generation + chat history if nothing selected.
		if ( empty( $capability_enums ) ) {
			$capability_enums[] = CapabilityEnum::textGeneration();
			$capability_enums[] = CapabilityEnum::chatHistory();
		}

		// Text generation models always support chat history on Azure OpenAI.
		$has_text = false;
		$has_chat = false;
		foreach ( $capability_enums as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text = true;
			}
			if ( $cap->isChatHistory() ) {
				$has_chat = true;
			}
		}
		if ( $has_text && ! $has_chat ) {
			$capability_enums[] = CapabilityEnum::chatHistory();
		}

		// Build supported options based on capabilities.
		$supported_options = $this->buildSupportedOptionsForCapabilities( $capability_enums );

		return array(
			new ModelMetadata(
				$deployment_id,
				$deployment_id,
				$capability_enums,
				$supported_options
			),
		);
	}

	/**
	 * Build supported options based on the model capabilities.
	 *
	 * @param array<CapabilityEnum> $capabilities The capabilities.
	 * @return list<SupportedOption> The supported options.
	 */
	protected function buildSupportedOptionsForCapabilities( array $capabilities ): array {
		$options = array();

		// Check for text-related capabilities.
		$has_text_generation  = false;
		$has_image_generation = false;

		foreach ( $capabilities as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text_generation = true;
			}
			if ( $cap->isImageGeneration() ) {
				$has_image_generation = true;
			}
		}

		// Text generation models: declare the full set of supported options so that
		// ModelRequirements::areMetBy() matches when callers set temperature, system
		// instruction, candidate count, etc.  Passing null means "any value accepted".
		// NOTE: outputModalities is critical — PromptBuilder::generateTextResult() calls
		// includeOutputModalities(text) before model lookup, so without it the model is rejected.
		if ( $has_text_generation ) {
			// Modern Azure OpenAI models (GPT-4o, GPT-4.1, etc.) support vision input.
			// Declare all supported input modality combinations so the SDK's
			// ModelRequirements::areMetBy() matches when callers attach images
			// or documents via with_file().
			$input_modalities  = array(
				array( ModalityEnum::text() ),
				array( ModalityEnum::text(), ModalityEnum::image() ),
				array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::audio() ),
				array( ModalityEnum::text(), ModalityEnum::document() ),
				array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::document() ),
			);
			$output_modalities = array( array( ModalityEnum::text() ) );
			$options[]         = new SupportedOption( OptionEnum::inputModalities(), $input_modalities );
			$options[]         = new SupportedOption( OptionEnum::outputModalities(), $output_modalities );
			$options[]         = new SupportedOption( OptionEnum::systemInstruction() );
			$options[]         = new SupportedOption( OptionEnum::candidateCount() );
			$options[]         = new SupportedOption( OptionEnum::maxTokens() );
			$options[]         = new SupportedOption( OptionEnum::temperature() );
			$options[]         = new SupportedOption( OptionEnum::topP() );
			$options[]         = new SupportedOption( OptionEnum::topK() );
			$options[]         = new SupportedOption( OptionEnum::stopSequences() );
			$options[]         = new SupportedOption( OptionEnum::presencePenalty() );
			$options[]         = new SupportedOption( OptionEnum::frequencyPenalty() );
			$options[]         = new SupportedOption( OptionEnum::logprobs() );
			$options[]         = new SupportedOption( OptionEnum::topLogprobs() );
			$options[]         = new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) );
			$options[]         = new SupportedOption( OptionEnum::outputSchema() );
			$options[]         = new SupportedOption( OptionEnum::functionDeclarations() );
			$options[]         = new SupportedOption( OptionEnum::webSearch() );
			$options[]         = new SupportedOption( OptionEnum::customOptions() );
		}

		// Image generation models: declare input (text) and output (image) modalities
		// so the SDK's ModelRequirements::areMetBy() matches when callers request
		// image output via includeOutputModalities(image).
		if ( $has_image_generation && ! $has_text_generation ) {
			$input_modalities  = array( array( ModalityEnum::text() ) );
			$output_modalities = array( array( ModalityEnum::image() ) );
			$options[]         = new SupportedOption( OptionEnum::inputModalities(), $input_modalities );
			$options[]         = new SupportedOption( OptionEnum::outputModalities(), $output_modalities );
			$options[]         = new SupportedOption( OptionEnum::outputFileType() );
			$options[]         = new SupportedOption( OptionEnum::customOptions() );
		}

		return $options;
	}

	/**
	 * Gets metadata for a specific model.
	 *
	 * @param string $modelId Model identifier.
	 * @return ModelMetadata Model metadata.
	 * @throws InvalidArgumentException If model metadata not found.
	 */
	public function getModelMetadata( string $modelId ): ModelMetadata { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Interface-defined parameter name.
		$models = $this->listModelMetadata();

		foreach ( $models as $model ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			if ( $model->getId() === $modelId ) {
				return $model;
			}
		}

		throw new InvalidArgumentException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			sprintf( 'Model metadata not found for model ID: %s', $modelId )
		);
	}

	/**
	 * Checks if metadata exists for a specific model.
	 *
	 * @param string $modelId Model identifier.
	 * @return bool True if metadata exists, false otherwise.
	 */
	public function hasModelMetadata( string $modelId ): bool { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Interface-defined parameter name.
		try {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			$this->getModelMetadata( $modelId );
			return true;
		} catch ( InvalidArgumentException $e ) {
			return false;
		}
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

		// Read API key from connector settings (the canonical source).
		$api_key = Connector_Settings::get_real_api_key();

		// Fallback to environment variable.
		if ( empty( $api_key ) ) {
			$env_key = getenv( 'AZURE_OPENAI_API_KEY' );
			if ( false !== $env_key && '' !== $env_key ) {
				$api_key = $env_key;
			}
		}

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

			if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
				return $this->getStaticModelList();
			}

			return $this->parseDeploymentsToModelMetadata( $data['data'] );

		} catch ( \Exception $e ) {
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
			$deployment_id   = $deployment['id'] ?? '';
			$model_name      = $deployment['model'] ?? $deployment_id;
			$deployment_name = $deployment['id'] ?? $deployment['name'] ?? '';

			if ( empty( $deployment_name ) ) {
				continue;
			}

			$capabilities      = $this->getCapabilitiesForModel( $model_name );
			$supported_options = $this->buildSupportedOptionsForCapabilities( $capabilities );

			$models[] = new ModelMetadata(
				$deployment_name,
				$deployment_name,
				$capabilities,
				$supported_options
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
			'gpt-4'                  => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			'gpt-4-turbo'            => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			'gpt-4o'                 => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			'gpt-4o-mini'            => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			// GPT-3.5 variants.
			'gpt-35-turbo'           => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			'gpt-35-turbo-16k'       => array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			// Image generation.
			'dall-e-3'               => array( CapabilityEnum::imageGeneration() ),
			'dall-e-2'               => array( CapabilityEnum::imageGeneration() ),
			'gpt-image-1'            => array( CapabilityEnum::imageGeneration() ),
			// Embedding models.
			'text-embedding-ada-002' => array( CapabilityEnum::embeddingGeneration() ),
			'text-embedding-3-small' => array( CapabilityEnum::embeddingGeneration() ),
			'text-embedding-3-large' => array( CapabilityEnum::embeddingGeneration() ),
			// Text-to-speech models.
			'tts-1'                  => array( CapabilityEnum::textToSpeechConversion() ),
			'tts-1-hd'               => array( CapabilityEnum::textToSpeechConversion() ),
		);

		$models = array();

		foreach ( $common_models as $model_id => $capabilities ) {
			$supported_options = $this->buildSupportedOptionsForCapabilities( $capabilities );

			$models[] = new ModelMetadata(
				$model_id,
				$model_id,
				$capabilities,
				$supported_options
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
		if ( strpos( $model_lower, 'dall-e' ) !== false || strpos( $model_lower, 'gpt-image' ) !== false ) {
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
			return array( CapabilityEnum::embeddingGeneration() );
		}

		// Text-to-speech models.
		if ( strpos( $model_lower, 'tts' ) !== false ) {
			return array( CapabilityEnum::textToSpeechConversion() );
		}

		// Default to text generation.
		return array( CapabilityEnum::textGeneration() );
	}
}

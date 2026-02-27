<?php
/**
 * Azure OpenAI Provider.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Provider;

use RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AzureOpenAiAiProvider\Metadata\AzureOpenAiModelMetadataDirectory;
use WordPress\AzureOpenAiAiProvider\Models\AzureOpenAiTextGenerationModel;
use WordPress\AzureOpenAiAiProvider\Models\AzureOpenAiImageGenerationModel;
use WordPress\AzureOpenAiAiProvider\Models\AzureOpenAiEmbeddingModel;
use WordPress\AzureOpenAiAiProvider\Models\AzureOpenAiTextToSpeechModel;
use WordPress\AzureOpenAiAiProvider\Settings\Settings_Manager;

/**
 * Azure OpenAI Provider class.
 *
 * Provides integration with Azure OpenAI Service for text and image generation.
 */
class AzureOpenAiProvider extends AbstractApiProvider {

	/**
	 * Get the base URL for the Azure OpenAI API.
	 *
	 * @return string The base URL.
	 */
	protected static function baseUrl(): string {
		$settings = Settings_Manager::get_instance();
		$endpoint = $settings->get_endpoint();

		if ( empty( $endpoint ) ) {
			return '';
		}

		return rtrim( $endpoint, '/' );
	}

	/**
	 * Build the full URL for an API endpoint.
	 *
	 * Azure OpenAI uses a different URL structure than standard OpenAI:
	 * {endpoint}/openai/deployments/{deployment}/chat/completions?api-version={version}
	 *
	 * @param string $path The API path (e.g., 'chat/completions').
	 * @return string The full URL.
	 */
	public static function url( string $path = '' ): string {
		$base_url = static::baseUrl();

		if ( empty( $base_url ) ) {
			return '';
		}

		$settings    = Settings_Manager::get_instance();
		$api_version = $settings->get_api_version();

		// For paths that don't include deployments (like listing deployments).
		if ( strpos( $path, 'deployments' ) === 0 ) {
			return $base_url . '/openai/' . $path . '?api-version=' . $api_version;
		}

		// For model-specific paths, the deployment name is added by the model class.
		return $base_url . '/openai/' . $path;
	}

	/**
	 * Build a deployment-specific URL.
	 *
	 * @param string $deployment The deployment/model name.
	 * @param string $path       The API path (e.g., 'chat/completions').
	 * @return string The full URL.
	 */
	public static function deploymentUrl( string $deployment, string $path ): string {
		$base_url = static::baseUrl();

		if ( empty( $base_url ) ) {
			return '';
		}

		$settings    = Settings_Manager::get_instance();
		$api_version = $settings->get_api_version();

		return $base_url . '/openai/deployments/' . $deployment . '/' . $path . '?api-version=' . $api_version;
	}

	/**
	 * Create a model instance based on its capabilities.
	 *
	 * @param ModelMetadata    $model_metadata    The model metadata.
	 * @param ProviderMetadata $provider_metadata The provider metadata.
	 * @return ModelInterface The model instance.
	 * @throws RuntimeException If the model capabilities are not supported.
	 */
	protected static function createModel( ModelMetadata $model_metadata, ProviderMetadata $provider_metadata ): ModelInterface {
		$capabilities = $model_metadata->getSupportedCapabilities();

		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new AzureOpenAiTextGenerationModel( $model_metadata, $provider_metadata );
			}
			if ( $capability->isImageGeneration() ) {
				return new AzureOpenAiImageGenerationModel( $model_metadata, $provider_metadata );
			}
			if ( $capability->isEmbeddingGeneration() ) {
				return new AzureOpenAiEmbeddingModel( $model_metadata, $provider_metadata );
			}
			if ( $capability->isTextToSpeechConversion() ) {
				return new AzureOpenAiTextToSpeechModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException(
			sprintf(
				/* translators: %s: model ID */
				esc_html__( 'Unsupported model capabilities for model: %s', 'ai-provider-for-azure-openai' ),
				esc_html( $model_metadata->getId() )
			)
		);
	}

	/**
	 * Create the provider metadata.
	 *
	 * @return ProviderMetadata The provider metadata.
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'azure-openai',
			__( 'Azure OpenAI', 'ai-provider-for-azure-openai' ),
			ProviderTypeEnum::cloud(),
			'https://portal.azure.com/#view/Microsoft_Azure_ProjectOxford/CognitiveServicesHub/~/OpenAI',
			RequestAuthenticationMethod::apiKey()
		);
	}

	/**
	 * Create the provider availability checker.
	 *
	 * @return ProviderAvailabilityInterface The availability checker.
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability( static::modelMetadataDirectory() );
	}

	/**
	 * Create the model metadata directory.
	 *
	 * @return ModelMetadataDirectoryInterface The model metadata directory.
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new AzureOpenAiModelMetadataDirectory();
	}
}

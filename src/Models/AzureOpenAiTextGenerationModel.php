<?php
/**
 * Azure OpenAI Text Generation Model.
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
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AzureOpenAiAiProvider\Provider\AzureOpenAiProvider;

/**
 * Azure OpenAI Text Generation Model class.
 *
 * Implements text generation using Azure OpenAI's chat completions API.
 */
class AzureOpenAiTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface {

	/**
	 * Generate text based on the provided prompt.
	 *
	 * @param array $prompt The prompt data (messages array).
	 * @return GenerativeAiResult The generation result.
	 */
	final public function generateTextResult( array $prompt ): GenerativeAiResult {
		$http_transporter = $this->getHttpTransporter();
		$params           = $this->prepareGenerateTextParams( $prompt );

		// Build the Azure-specific URL: {endpoint}/openai/deployments/{model}/chat/completions?api-version={version}
		$url = AzureOpenAiProvider::deploymentUrl( $this->metadata()->getId(), 'chat/completions' );

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
	 * Prepare the parameters for the text generation request.
	 *
	 * @param array $prompt The prompt data.
	 * @return array The request parameters.
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$config = $this->getConfig();

		// Build messages array from prompt.
		$messages = $this->prepareMessagesParam( $prompt );

		$params = array(
			'messages' => $messages,
		);

		// Add system instruction if provided.
		$system_instruction = $config->getSystemInstruction();
		if ( ! empty( $system_instruction ) ) {
			// Prepend system message.
			array_unshift(
				$params[ 'messages' ],
				array(
					'role'    => 'system',
					'content' => $system_instruction,
				)
			);
		}

		// Add optional parameters.
		$max_tokens = $config->getMaxTokens();
		if ( ! empty( $max_tokens ) ) {
			$params[ 'max_tokens' ] = $max_tokens;
		}

		$temperature = $config->getTemperature();
		if ( null !== $temperature ) {
			$params[ 'temperature' ] = $temperature;
		}

		$top_p = $config->getTopP();
		if ( null !== $top_p ) {
			$params[ 'top_p' ] = $top_p;
		}

		// Handle JSON schema output.
		if ( 'application/json' === $config->getOutputMimeType() ) {
			$output_schema = $config->getOutputSchema();
			if ( ! empty( $output_schema ) ) {
				$params[ 'response_format' ] = array(
					'type'        => 'json_schema',
					'json_schema' => array(
						'name'   => 'response_schema',
						'schema' => $output_schema,
						'strict' => true,
					),
				);
			} else {
				$params[ 'response_format' ] = array(
					'type' => 'json_object',
				);
			}
		}

		// Handle tools (functions).
		$function_declarations = $config->getFunctionDeclarations();
		if ( ! empty( $function_declarations ) ) {
			$params[ 'tools' ] = $this->prepareToolsParam( $function_declarations );
		}

		return $params;
	}

	/**
	 * Prepare the messages parameter from the prompt.
	 *
	 * @param array $prompt The prompt data.
	 * @return array The messages array.
	 */
	protected function prepareMessagesParam( array $prompt ): array {
		$messages = array();

		foreach ( $prompt as $item ) {
			if ( is_string( $item ) ) {
				$messages[] = array(
					'role'    => 'user',
					'content' => $item,
				);
			} elseif ( is_array( $item ) ) {
				$messages[] = $item;
			}
		}

		return $messages;
	}

	/**
	 * Prepare the tools parameter for function calling.
	 *
	 * @param array $function_declarations The function declarations.
	 * @return array The tools array.
	 */
	protected function prepareToolsParam( array $function_declarations ): array {
		$tools = array();

		foreach ( $function_declarations as $function ) {
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $function[ 'name' ] ?? '',
					'description' => $function[ 'description' ] ?? '',
					'parameters'  => $function[ 'parameters' ] ?? array(),
				),
			);
		}

		return $tools;
	}

	/**
	 * Parse the API response into a GenerativeAiResult.
	 *
	 * @param \WordPress\AiClient\Providers\Http\Response $response The API response.
	 * @return GenerativeAiResult The parsed result.
	 */
	protected function parseResponseToGenerativeAiResult( $response ): GenerativeAiResult {
		$data = $response->getData();

		$content       = '';
		$function_call = null;

		if ( isset( $data[ 'choices' ][ 0 ][ 'message' ][ 'content' ] ) ) {
			$content = $data[ 'choices' ][ 0 ][ 'message' ][ 'content' ];
		}

		// Handle function/tool calls.
		if ( isset( $data[ 'choices' ][ 0 ][ 'message' ][ 'tool_calls' ] ) ) {
			$tool_calls    = $data[ 'choices' ][ 0 ][ 'message' ][ 'tool_calls' ];
			$function_call = array();

			foreach ( $tool_calls as $tool_call ) {
				if ( 'function' === $tool_call[ 'type' ] ) {
					$function_call[] = array(
						'id'        => $tool_call[ 'id' ] ?? '',
						'name'      => $tool_call[ 'function' ][ 'name' ] ?? '',
						'arguments' => $tool_call[ 'function' ][ 'arguments' ] ?? '',
					);
				}
			}
		}

		// Extract usage data.
		$usage = array();
		if ( isset( $data[ 'usage' ] ) ) {
			$usage = array(
				'prompt_tokens'     => $data[ 'usage' ][ 'prompt_tokens' ] ?? 0,
				'completion_tokens' => $data[ 'usage' ][ 'completion_tokens' ] ?? 0,
				'total_tokens'      => $data[ 'usage' ][ 'total_tokens' ] ?? 0,
			);
		}

		return new GenerativeAiResult(
			$content,
			$function_call,
			$usage,
			$data
		);
	}
}

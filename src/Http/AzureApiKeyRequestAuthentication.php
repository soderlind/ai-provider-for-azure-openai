<?php
/**
 * Azure API Key Request Authentication.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Http;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Azure API Key Request Authentication class.
 *
 * Implements Azure-specific authentication using the 'api-key' header
 * instead of the standard 'Authorization: Bearer' header.
 */
class AzureApiKeyRequestAuthentication implements RequestAuthenticationInterface {

	/**
	 * The API key for Azure OpenAI.
	 *
	 * @var string
	 */
	protected string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key The Azure OpenAI API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Authenticate a request by adding the Azure API key header.
	 *
	 * @param Request $request The request to authenticate.
	 * @return Request The authenticated request.
	 */
	public function authenticateRequest( Request $request ): Request {
		return $request->withHeader( 'api-key', $this->api_key );
	}

	/**
	 * Get the JSON schema for the authentication data.
	 *
	 * @return array The JSON schema.
	 */
	public static function getJsonSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'apiKey' => array(
					'type'        => 'string',
					'title'       => __( 'API Key', 'ai-provider-for-azure-openai' ),
					'description' => __( 'Your Azure OpenAI API key.', 'ai-provider-for-azure-openai' ),
				),
			),
			'required'   => array( 'apiKey' ),
		);
	}

	/**
	 * Create an instance from an array of data.
	 *
	 * @param array $data The authentication data.
	 * @return self The authentication instance.
	 */
	public static function fromArray( array $data ): self {
		return new self( $data[ 'apiKey' ] ?? '' );
	}

	/**
	 * Convert to array.
	 *
	 * @return array The authentication data.
	 */
	public function toArray(): array {
		return array(
			'apiKey' => $this->api_key,
		);
	}
}

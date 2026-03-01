<?php
/**
 * Azure API Key Request Authentication.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

namespace WordPress\AzureOpenAiAiProvider\Http;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Azure API Key Request Authentication class.
 *
 * Extends the SDK's ApiKeyRequestAuthentication so the ProviderRegistry
 * instanceof check passes (the provider declares apiKey() auth method).
 * Overrides authenticateRequest() to send the 'api-key' header that
 * Azure OpenAI expects instead of 'Authorization: Bearer'.
 */
class AzureApiKeyRequestAuthentication extends ApiKeyRequestAuthentication {

	/**
	 * Authenticate a request by adding the Azure API key header.
	 *
	 * @param Request $request The request to authenticate.
	 * @return Request The authenticated request.
	 */
	public function authenticateRequest( Request $request ): Request {
		return $request->withHeader( 'api-key', $this->getApiKey() );
	}
}

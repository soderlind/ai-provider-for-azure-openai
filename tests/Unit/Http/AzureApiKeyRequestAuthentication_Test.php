<?php
/**
 * Tests for AzureApiKeyRequestAuthentication class.
 *
 * @package WordPress\AzureOpenAiAiProvider\Tests
 */

namespace WordPress\AzureOpenAiAiProvider\Tests\Unit\Http;

use AzureOpenAiTestCase;
use Mockery;
use WordPress\AzureOpenAiAiProvider\Http\AzureApiKeyRequestAuthentication;

/**
 * AzureApiKeyRequestAuthentication test class.
 */
class AzureApiKeyRequestAuthentication_Test extends AzureOpenAiTestCase {

	/**
	 * Test constructor stores API key.
	 *
	 * @return void
	 */
	public function test_constructor_stores_api_key(): void {
		$auth = new AzureApiKeyRequestAuthentication( 'my-test-api-key' );

		$this->assertInstanceOf( AzureApiKeyRequestAuthentication::class, $auth );
	}

	/**
	 * Test authenticateRequest adds api-key header.
	 *
	 * @return void
	 */
	public function test_authenticate_request_adds_api_key_header(): void {
		$api_key = 'test-azure-api-key-123';
		$auth    = new AzureApiKeyRequestAuthentication( $api_key );

		// Create a mock Request object.
		$request = Mockery::mock( 'WordPress\AiClient\Providers\Http\DTO\Request' );

		// Expect withHeader to be called with 'api-key' and the API key value.
		$request->shouldReceive( 'withHeader' )
			->once()
			->with( 'api-key', $api_key )
			->andReturnSelf();

		$result = $auth->authenticateRequest( $request );

		$this->assertSame( $request, $result );
	}

	/**
	 * Test getJsonSchema returns correct schema.
	 *
	 * @return void
	 */
	public function test_get_json_schema_returns_correct_schema(): void {
		$schema = AzureApiKeyRequestAuthentication::getJsonSchema();

		$this->assertIsArray( $schema );
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'apiKey', $schema['properties'] );
		$this->assertSame( 'string', $schema['properties']['apiKey']['type'] );
		$this->assertContains( 'apiKey', $schema['required'] );
	}

	/**
	 * Test fromArray creates instance correctly.
	 *
	 * @return void
	 */
	public function test_from_array_creates_instance(): void {
		$data = array( 'apiKey' => 'array-api-key-456' );
		$auth = AzureApiKeyRequestAuthentication::fromArray( $data );

		$this->assertInstanceOf( AzureApiKeyRequestAuthentication::class, $auth );
	}

	/**
	 * Test fromArray handles missing apiKey gracefully.
	 *
	 * @return void
	 */
	public function test_from_array_handles_missing_key(): void {
		$data = array();
		$auth = AzureApiKeyRequestAuthentication::fromArray( $data );

		$this->assertInstanceOf( AzureApiKeyRequestAuthentication::class, $auth );
	}

	/**
	 * Test toArray returns correct data.
	 *
	 * @return void
	 */
	public function test_to_array_returns_correct_data(): void {
		$api_key = 'test-key-for-array';
		$auth    = new AzureApiKeyRequestAuthentication( $api_key );

		$result = $auth->toArray();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'apiKey', $result );
		$this->assertSame( $api_key, $result['apiKey'] );
	}

	/**
	 * Test that Azure uses api-key header (not Authorization Bearer).
	 *
	 * @return void
	 */
	public function test_uses_api_key_header_not_bearer(): void {
		$api_key = 'test-key';
		$auth    = new AzureApiKeyRequestAuthentication( $api_key );

		$request = Mockery::mock( 'WordPress\AiClient\Providers\Http\DTO\Request' );

		// Verify it uses 'api-key' header specifically.
		$request->shouldReceive( 'withHeader' )
			->once()
			->with( 'api-key', $api_key )
			->andReturnSelf();

		// Should NOT use Authorization header.
		$request->shouldNotReceive( 'withHeader' )
			->with( 'Authorization', Mockery::any() );

		$result = $auth->authenticateRequest( $request );

		// Verify the mock was used correctly.
		$this->assertSame( $request, $result );
	}
}

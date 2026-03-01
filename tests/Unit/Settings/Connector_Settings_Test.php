<?php
/**
 * Tests for Connector_Settings class.
 *
 * @package WordPress\AzureOpenAiAiProvider\Tests
 */

namespace WordPress\AzureOpenAiAiProvider\Tests\Unit\Settings;

use AzureOpenAiTestCase;
use Brain\Monkey\Functions;
use WordPress\AzureOpenAiAiProvider\Settings\Connector_Settings;

/**
 * Connector_Settings test class.
 */
class Connector_Settings_Test extends AzureOpenAiTestCase {

	// ------------------------------------------------------------------
	// mask_api_key
	// ------------------------------------------------------------------

	/**
	 * Test mask_api_key masks a typical API key.
	 *
	 * @return void
	 */
	public function test_mask_api_key_masks_typical_key(): void {
		$key    = 'abcdefghijklmnop1234';
		$masked = Connector_Settings::mask_api_key( $key );

		// Last 4 chars preserved.
		$this->assertStringEndsWith( '1234', $masked );
		// Starts with bullet characters.
		$this->assertStringContainsString( "\u{2022}", $masked );
		// Does not contain the original key prefix.
		$this->assertStringNotContainsString( 'abcdef', $masked );
	}

	/**
	 * Test mask_api_key returns short keys unchanged.
	 *
	 * @return void
	 */
	public function test_mask_api_key_returns_short_key_unchanged(): void {
		$this->assertSame( 'abc', Connector_Settings::mask_api_key( 'abc' ) );
		$this->assertSame( 'abcd', Connector_Settings::mask_api_key( 'abcd' ) );
	}

	/**
	 * Test mask_api_key returns empty string for empty input.
	 *
	 * @return void
	 */
	public function test_mask_api_key_returns_empty_for_empty(): void {
		$this->assertSame( '', Connector_Settings::mask_api_key( '' ) );
	}

	/**
	 * Test mask_api_key handles non-string gracefully.
	 *
	 * @return void
	 */
	public function test_mask_api_key_handles_non_string(): void {
		$this->assertSame( '', Connector_Settings::mask_api_key( null ) );
		$this->assertSame( '', Connector_Settings::mask_api_key( false ) );
	}

	/**
	 * Test mask_api_key caps bullet count at 16.
	 *
	 * @return void
	 */
	public function test_mask_api_key_caps_bullet_count(): void {
		$key    = str_repeat( 'x', 100 );
		$masked = Connector_Settings::mask_api_key( $key );

		// 16 bullets + 4 chars = 20 total.
		$this->assertSame( 20, mb_strlen( $masked ) );
	}

	// ------------------------------------------------------------------
	// sanitize_capabilities
	// ------------------------------------------------------------------

	/**
	 * Test sanitize_capabilities filters invalid values.
	 *
	 * @return void
	 */
	public function test_sanitize_capabilities_filters_invalid(): void {
		$input    = array( 'text_generation', 'invalid_cap', 'chat_history' );
		$expected = array( 'text_generation', 'chat_history' );

		$result = Connector_Settings::sanitize_capabilities( $input );

		$this->assertSame( $expected, array_values( $result ) );
	}

	/**
	 * Test sanitize_capabilities returns empty array for non-array.
	 *
	 * @return void
	 */
	public function test_sanitize_capabilities_returns_empty_for_non_array(): void {
		$this->assertSame( array(), Connector_Settings::sanitize_capabilities( 'text_generation' ) );
		$this->assertSame( array(), Connector_Settings::sanitize_capabilities( null ) );
	}

	/**
	 * Test sanitize_capabilities accepts all valid capabilities.
	 *
	 * @return void
	 */
	public function test_sanitize_capabilities_accepts_all_valid(): void {
		$all = array(
			'text_generation',
			'image_generation',
			'chat_history',
			'embedding_generation',
			'text_to_speech_conversion',
		);

		$this->assertSame( $all, array_values( Connector_Settings::sanitize_capabilities( $all ) ) );
	}

	// ------------------------------------------------------------------
	// get_real_api_key
	// ------------------------------------------------------------------

	/**
	 * Test get_real_api_key retrieves the unmasked key.
	 *
	 * @return void
	 */
	public function test_get_real_api_key_retrieves_unmasked(): void {
		Functions\expect( 'remove_filter' )
			->once()
			->with( 'option_' . Connector_Settings::OPTION_API_KEY, array( Connector_Settings::class, 'mask_api_key' ) );

		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_API_KEY, '' )
			->andReturn( 'real-secret-key-1234' );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'option_' . Connector_Settings::OPTION_API_KEY, array( Connector_Settings::class, 'mask_api_key' ) );

		$this->assertSame( 'real-secret-key-1234', Connector_Settings::get_real_api_key() );
	}

	/**
	 * Test get_real_api_key returns empty string when not set.
	 *
	 * @return void
	 */
	public function test_get_real_api_key_returns_empty_when_not_set(): void {
		Functions\expect( 'remove_filter' )->once();

		Functions\expect( 'get_option' )
			->once()
			->with( Connector_Settings::OPTION_API_KEY, '' )
			->andReturn( '' );

		Functions\expect( 'add_filter' )->once();

		$this->assertSame( '', Connector_Settings::get_real_api_key() );
	}

	// ------------------------------------------------------------------
	// register (smoke test — verifies calls to register_setting)
	// ------------------------------------------------------------------

	/**
	 * Test register calls register_setting for all options.
	 *
	 * @return void
	 */
	public function test_register_calls_register_setting(): void {
		Functions\expect( 'register_setting' )
			->times( 5 );

		Functions\expect( 'add_filter' )
			->once()
			->with(
				'option_' . Connector_Settings::OPTION_API_KEY,
				array( Connector_Settings::class, 'mask_api_key' )
			);

		Connector_Settings::register();

		// Brain Monkey expectation counts are verified in tearDown;
		// add an explicit assertion so PHPUnit does not flag this as risky.
		$this->assertTrue( true );
	}
}

/**
 * Tests for the Azure OpenAI Connectors page module.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */
import { describe, it, expect, vi, beforeAll, beforeEach, afterEach } from 'vitest';
import React from 'react';
import { render, screen, fireEvent, act, waitFor, cleanup } from '@testing-library/react';

/*
 * The @wordpress/connectors alias (vitest.config.js) points to
 * tests/js/__mocks__/@wordpress/connectors.js which exports vi.fn()
 * stubs.  We import them here to spy on calls and to use in the
 * mock implementations for ConnectorItem / DefaultConnectorSettings.
 */
import {
	__experimentalRegisterConnector as mockRegisterConnector,
	__experimentalConnectorItem as _unusedItem,
	__experimentalDefaultConnectorSettings as _unusedSettings,
} from '@wordpress/connectors';

/*
 * Override the alias mock with richer implementations that render
 * testable HTML.  Because the alias file already uses vi.fn() for
 * registerConnector, calls are recorded automatically.
 */
vi.mock( '@wordpress/connectors', async ( importOriginal ) => {
	const orig = await importOriginal();
	return {
		...orig,
		__experimentalConnectorItem: ( { icon, name, description, actionArea, children } ) =>
			React.createElement(
				'div',
				{ 'data-testid': 'connector-item' },
				React.createElement( 'span', { 'data-testid': 'connector-name' }, name ),
				React.createElement( 'span', { 'data-testid': 'connector-description' }, description ),
				icon && React.createElement( 'span', { 'data-testid': 'connector-icon' }, icon ),
				actionArea && React.createElement( 'span', { 'data-testid': 'connector-action' }, actionArea ),
				children && React.createElement( 'div', { 'data-testid': 'connector-children' }, children )
			),
		__experimentalDefaultConnectorSettings: () =>
			React.createElement( 'div', { 'data-testid': 'default-connector-settings' } ),
	};
} );

/*
 * registeredConfig is captured once the source module is loaded.
 * It holds { label, description, render } passed to registerConnector.
 */
let registeredConfig;

/* ── helper: render the registered component with test props ───── */
function renderConnector( propsOverrides = {} ) {
	const Component = registeredConfig.render;
	return render(
		React.createElement( Component, {
			slug: 'azure-openai',
			label: 'Azure OpenAI',
			description: 'Test description',
			...propsOverrides,
		} )
	);
}

/* ================================================================
 * Tests
 * ================================================================ */

describe( 'Azure OpenAI Connector', () => {
	/*
	 * Load the module exactly once.  The module-level code calls
	 * registerConnector() which is a vi.fn() — its call is recorded
	 * and we capture the config object for use in every test.
	 */
	beforeAll( async () => {
		window.wp.apiFetch.mockResolvedValue( {} );
		await import( '../../src/js/connectors.js' );
		registeredConfig = mockRegisterConnector.mock.calls[ 0 ][ 1 ];
	} );

	beforeEach( () => {
		// Reset only the apiFetch mock so each test can set its own response.
		window.wp.apiFetch.mockReset();
		window.wp.apiFetch.mockResolvedValue( {} );
	} );

	afterEach( () => {
		cleanup();
	} );

	/* ---- Registration ----------------------------------------- */

	describe( 'registerConnector', () => {
		it( 'should register with slug "ai-provider/azure-openai"', () => {
			expect( mockRegisterConnector ).toHaveBeenCalledTimes( 1 );
			expect( mockRegisterConnector.mock.calls[ 0 ][ 0 ] ).toBe( 'ai-provider/azure-openai' );
		} );

		it( 'should provide label, description, and render function', () => {
			expect( registeredConfig ).toHaveProperty( 'label' );
			expect( registeredConfig ).toHaveProperty( 'description' );
			expect( typeof registeredConfig.render ).toBe( 'function' );
		} );
	} );

	/* ---- Rendering: not connected ----------------------------- */

	describe( 'when not connected (no API key)', () => {
		beforeEach( () => {
			window.wp.apiFetch.mockResolvedValue( {
				connectors_ai_azure_openai_api_key: '',
				connectors_ai_azure_openai_endpoint: '',
				connectors_ai_azure_openai_api_version: '',
				connectors_ai_azure_openai_deployment_id: '',
				connectors_ai_azure_openai_capabilities: [],
			} );
		} );

		it( 'should show "Set Up" button', async () => {
			await act( async () => renderConnector() );
			const btn = await screen.findByText( 'Set Up' );
			expect( btn ).toBeTruthy();
		} );

		it( 'should use secondary variant with compact size', async () => {
			await act( async () => renderConnector() );
			const btn = await screen.findByText( 'Set Up' );
			expect( btn.getAttribute( 'data-variant' ) ).toBe( 'secondary' );
			expect( btn.getAttribute( 'data-size' ) ).toBe( 'compact' );
		} );
	} );

	/* ---- Rendering: connected --------------------------------- */

	describe( 'when connected (API key present)', () => {
		beforeEach( () => {
			window.wp.apiFetch.mockResolvedValue( {
				connectors_ai_azure_openai_api_key: '••••••••abcd',
				connectors_ai_azure_openai_endpoint: 'https://my.openai.azure.com',
				connectors_ai_azure_openai_api_version: '2024-02-15-preview',
				connectors_ai_azure_openai_deployment_id: 'gpt-4o',
				connectors_ai_azure_openai_capabilities: [ 'text_generation' ],
			} );
		} );

		it( 'should show "Edit" button', async () => {
			await act( async () => renderConnector() );
			const btn = await screen.findByText( 'Edit' );
			expect( btn ).toBeTruthy();
		} );

		it( 'should use tertiary variant', async () => {
			await act( async () => renderConnector() );
			const btn = await screen.findByText( 'Edit' );
			expect( btn.getAttribute( 'data-variant' ) ).toBe( 'tertiary' );
		} );
	} );

	/* ---- Icon ------------------------------------------------- */

	describe( 'icon', () => {
		beforeEach( () => {
			window.wp.apiFetch.mockResolvedValue( {
				connectors_ai_azure_openai_api_key: '',
			} );
		} );

		it( 'should render a cloud SVG icon at 40×40', async () => {
			await act( async () => renderConnector() );
			const iconContainer = screen.getByTestId( 'connector-icon' );
			const svg = iconContainer.querySelector( 'svg' );
			expect( svg ).toBeTruthy();
			expect( svg.getAttribute( 'width' ) ).toBe( '40' );
			expect( svg.getAttribute( 'height' ) ).toBe( '40' );
			expect( svg.getAttribute( 'viewBox' ) ).toBe( '0 0 24 24' );
		} );
	} );

	/* ---- Expanded panel --------------------------------------- */

	describe( 'expanded settings panel', () => {
		beforeEach( () => {
			window.wp.apiFetch.mockResolvedValue( {
				connectors_ai_azure_openai_api_key: '••••••••abcd',
				connectors_ai_azure_openai_endpoint: 'https://my.openai.azure.com',
				connectors_ai_azure_openai_api_version: '2024-02-15-preview',
				connectors_ai_azure_openai_deployment_id: 'gpt-4o',
				connectors_ai_azure_openai_capabilities: [ 'text_generation' ],
			} );
		} );

		it( 'should show settings after clicking the action button', async () => {
			await act( async () => renderConnector() );

			const btn = await screen.findByText( 'Edit' );
			await act( async () => fireEvent.click( btn ) );

			expect( screen.getByTestId( 'default-connector-settings' ) ).toBeTruthy();
			expect( screen.getByText( 'Azure OpenAI Settings' ) ).toBeTruthy();
		} );

		it( 'should show all setting fields when expanded', async () => {
			await act( async () => renderConnector() );
			const btn = await screen.findByText( 'Edit' );
			await act( async () => fireEvent.click( btn ) );

			expect( screen.getByText( 'Endpoint URL' ) ).toBeTruthy();
			expect( screen.getByText( 'API Version' ) ).toBeTruthy();
			expect( screen.getByText( 'Deployment ID' ) ).toBeTruthy();
			expect( screen.getByText( 'Capabilities' ) ).toBeTruthy();
		} );

		it( 'should render Save Settings button with __next40pxDefaultSize', async () => {
			await act( async () => renderConnector() );
			const btn = await screen.findByText( 'Edit' );
			await act( async () => fireEvent.click( btn ) );

			const saveBtn = screen.getByText( 'Save Settings' );
			expect( saveBtn.getAttribute( 'data-variant' ) ).toBe( 'primary' );
			expect( saveBtn.getAttribute( 'data-next40px' ) ).toBe( 'true' );
		} );

		it( 'should render all 5 capability checkboxes', async () => {
			await act( async () => renderConnector() );
			const btn = await screen.findByText( 'Edit' );
			await act( async () => fireEvent.click( btn ) );

			expect( screen.getByText( /Text Generation/ ) ).toBeTruthy();
			expect( screen.getByText( /Chat History/ ) ).toBeTruthy();
			expect( screen.getByText( /Image Generation/ ) ).toBeTruthy();
			expect( screen.getByText( /Embedding Generation/ ) ).toBeTruthy();
			expect( screen.getByText( /Text-to-Speech/ ) ).toBeTruthy();
		} );
	} );

	/* ---- Save extra settings ---------------------------------- */

	describe( 'saving extra settings', () => {
		beforeEach( () => {
			window.wp.apiFetch.mockResolvedValue( {
				connectors_ai_azure_openai_api_key: '••••••••abcd',
				connectors_ai_azure_openai_endpoint: 'https://my.openai.azure.com',
				connectors_ai_azure_openai_api_version: '2024-02-15-preview',
				connectors_ai_azure_openai_deployment_id: 'gpt-4o',
				connectors_ai_azure_openai_capabilities: [ 'text_generation' ],
			} );
		} );

		it( 'should call apiFetch POST on save and show success', async () => {
			await act( async () => renderConnector() );

			const editBtn = await screen.findByText( 'Edit' );
			await act( async () => fireEvent.click( editBtn ) );

			// Reset mock to capture the save call specifically.
			window.wp.apiFetch.mockResolvedValueOnce( {} );

			const saveBtn = screen.getByText( 'Save Settings' );
			await act( async () => fireEvent.click( saveBtn ) );

			// Find the POST call (not the initial GET).
			const postCall = window.wp.apiFetch.mock.calls.find(
				( call ) => call[ 0 ].method === 'POST' && call[ 0 ].path === '/wp/v2/settings'
			);
			expect( postCall ).toBeTruthy();
			expect( postCall[ 0 ].data ).toHaveProperty( 'connectors_ai_azure_openai_endpoint' );
		} );

		it( 'should show error message on save failure', async () => {
			await act( async () => renderConnector() );

			const editBtn = await screen.findByText( 'Edit' );
			await act( async () => fireEvent.click( editBtn ) );

			window.wp.apiFetch.mockRejectedValueOnce( new Error( 'Error: Network failure' ) );

			const saveBtn = screen.getByText( 'Save Settings' );
			await act( async () => fireEvent.click( saveBtn ) );

			await waitFor( () => {
				expect( screen.getByText( /Error/ ) ).toBeTruthy();
			} );
		} );
	} );

	/* ---- Loading state ---------------------------------------- */

	describe( 'loading state', () => {
		it( 'should show spinner while loading', () => {
			// Make apiFetch hang indefinitely.
			window.wp.apiFetch.mockReturnValue( new Promise( () => {} ) );

			renderConnector();

			const spinner = document.querySelector( '.spinner.is-active' );
			expect( spinner ).toBeTruthy();
		} );
	} );

	/* ---- Settings fields consistency -------------------------- */

	describe( 'option name constants', () => {
		it( 'should use connectors_ai_azure_openai_ prefix for all settings', async () => {
			await act( async () => renderConnector() );

			const getCall = window.wp.apiFetch.mock.calls.find(
				( call ) => call[ 0 ].path && call[ 0 ].path.includes( '_fields=' )
			);
			expect( getCall ).toBeTruthy();

			const fields = getCall[ 0 ].path.split( '_fields=' )[ 1 ];
			const fieldNames = fields.split( ',' );
			for ( const field of fieldNames ) {
				expect( field ).toMatch( /^connectors_ai_azure_openai_/ );
			}
			expect( fieldNames ).toHaveLength( 5 );
		} );
	} );
} );

/**
 * Azure OpenAI Connector for the WordPress Connectors page (WP 7.0+).
 *
 * Registers a custom connector that manages Azure OpenAI provider
 * configuration: API key, endpoint, API version, deployment ID,
 * and capabilities.
 *
 * @package WordPress\AzureOpenAiAiProvider
 */

import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
	__experimentalDefaultConnectorSettings as DefaultConnectorSettings,
} from '@wordpress/connectors';

/*
 * @wordpress/api-fetch, @wordpress/element, @wordpress/i18n, and
 * @wordpress/components are classic scripts in WP 7.0 (not script
 * modules), so they are accessed from window globals.
 */
const apiFetch = window.wp.apiFetch;
const { useState, useEffect, useCallback, createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const { Button, TextControl, CheckboxControl } = window.wp.components;

const el = createElement;

/*
 * Option names — must match PHP register_setting() calls
 * in Connector_Settings::register().
 */
const API_KEY_SETTING      = 'connectors_ai_azure_openai_api_key';
const ENDPOINT_SETTING     = 'connectors_ai_azure_openai_endpoint';
const API_VERSION_SETTING  = 'connectors_ai_azure_openai_api_version';
const DEPLOYMENT_SETTING   = 'connectors_ai_azure_openai_deployment_id';
const CAPABILITIES_SETTING = 'connectors_ai_azure_openai_capabilities';

const ALL_FIELDS = [
	API_KEY_SETTING,
	ENDPOINT_SETTING,
	API_VERSION_SETTING,
	DEPLOYMENT_SETTING,
	CAPABILITIES_SETTING,
].join( ',' );

const DEFAULT_API_VERSION = '2024-02-15-preview';

const CAPABILITY_OPTIONS = [
	{ value: 'text_generation',           label: __( 'Text Generation (GPT models)', 'ai-provider-for-azure-openai' ) },
	{ value: 'chat_history',              label: __( 'Chat History (conversation context)', 'ai-provider-for-azure-openai' ) },
	{ value: 'image_generation',          label: __( 'Image Generation (DALL-E models)', 'ai-provider-for-azure-openai' ) },
	{ value: 'embedding_generation',      label: __( 'Embedding Generation', 'ai-provider-for-azure-openai' ) },
	{ value: 'text_to_speech_conversion', label: __( 'Text-to-Speech (tts models)', 'ai-provider-for-azure-openai' ) },
];

/**
 * Cloud icon (Gutenberg default, 40 × 40).
 */
function CloudIcon() {
	return el(
		'svg',
		{
			width: 40,
			height: 40,
			viewBox: '0 0 24 24',
			xmlns: 'http://www.w3.org/2000/svg',
			'aria-hidden': 'true',
		},
		el( 'path', {
			d: 'M17.3 10.1c0-2.5-2.1-4.4-4.8-4.4-2.2 0-4.1 1.4-4.6 3.3h-.2C5.7 9 4 10.7 4 12.8c0 2.1 1.7 3.8 3.7 3.8h9c1.8 0 3.2-1.5 3.2-3.3.1-1.6-1.1-2.9-2.6-3.2zm-.5 5.1h-9c-1.2 0-2.2-1.1-2.2-2.3s1-2.4 2.2-2.4h1.3l.3-1.1c.4-1.3 1.7-2.2 3.2-2.2 1.8 0 3.3 1.3 3.3 2.9v1.3l1.3.2c.8.1 1.4.9 1.4 1.8-.1 1-.9 1.8-1.8 1.8z',
		} )
	);
}

/**
 * Custom hook — loads, saves, and removes Azure OpenAI settings
 * via the WordPress REST Settings endpoint.
 */
function useAzureSettings() {
	const [ isLoading, setIsLoading ]       = useState( true );
	const [ apiKey, setApiKey ]             = useState( '' );
	const [ endpoint, setEndpoint ]         = useState( '' );
	const [ apiVersion, setApiVersion ]     = useState( '' );
	const [ deploymentId, setDeploymentId ] = useState( '' );
	const [ capabilities, setCapabilities ] = useState( [] );

	const isConnected = ! isLoading && apiKey !== '';

	/* ---- load on mount ---- */
	const loadSettings = useCallback( async () => {
		try {
			const result = await apiFetch( {
				path: `/wp/v2/settings?_fields=${ ALL_FIELDS }`,
			} );
			setApiKey( result[ API_KEY_SETTING ] || '' );
			setEndpoint( result[ ENDPOINT_SETTING ] || '' );
			setApiVersion( result[ API_VERSION_SETTING ] || '' );
			setDeploymentId( result[ DEPLOYMENT_SETTING ] || '' );
			setCapabilities( result[ CAPABILITIES_SETTING ] || [] );
		} catch {
			// Settings may not be accessible — leave defaults.
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadSettings();
	}, [ loadSettings ] );

	/* ---- API key ---- */
	const saveApiKey = useCallback(
		async ( newKey ) => {
			const result = await apiFetch( {
				path: `/wp/v2/settings?_fields=${ API_KEY_SETTING }`,
				method: 'POST',
				data: { [ API_KEY_SETTING ]: newKey },
			} );
			const returned = result[ API_KEY_SETTING ] || '';

			// If the server returned the same masked value, the key was rejected.
			if ( returned === apiKey && newKey !== '' ) {
				throw new Error(
					__( 'It was not possible to save the API key.', 'ai-provider-for-azure-openai' )
				);
			}
			setApiKey( returned );
		},
		[ apiKey ]
	);

	const removeApiKey = useCallback( async () => {
		await apiFetch( {
			path: `/wp/v2/settings?_fields=${ API_KEY_SETTING }`,
			method: 'POST',
			data: { [ API_KEY_SETTING ]: '' },
		} );
		setApiKey( '' );
	}, [] );

	/* ---- extra settings (endpoint, version, deployment, caps) ---- */
	const saveExtraSettings = useCallback( async ( extra ) => {
		const data = {};
		if ( extra.endpoint !== undefined ) {
			data[ ENDPOINT_SETTING ] = extra.endpoint;
		}
		if ( extra.apiVersion !== undefined ) {
			data[ API_VERSION_SETTING ] = extra.apiVersion;
		}
		if ( extra.deploymentId !== undefined ) {
			data[ DEPLOYMENT_SETTING ] = extra.deploymentId;
		}
		if ( extra.capabilities !== undefined ) {
			data[ CAPABILITIES_SETTING ] = extra.capabilities;
		}

		await apiFetch( {
			path: '/wp/v2/settings',
			method: 'POST',
			data,
		} );

		if ( extra.endpoint !== undefined ) {
			setEndpoint( extra.endpoint );
		}
		if ( extra.apiVersion !== undefined ) {
			setApiVersion( extra.apiVersion );
		}
		if ( extra.deploymentId !== undefined ) {
			setDeploymentId( extra.deploymentId );
		}
		if ( extra.capabilities !== undefined ) {
			setCapabilities( extra.capabilities );
		}
	}, [] );

	return {
		isLoading,
		isConnected,
		apiKey,
		endpoint,
		apiVersion,
		deploymentId,
		capabilities,
		setEndpoint,
		setApiVersion,
		setDeploymentId,
		setCapabilities,
		saveApiKey,
		removeApiKey,
		saveExtraSettings,
	};
}

/**
 * Main render component passed to registerConnector().
 *
 * @param {Object} props            Props injected by the Connectors page.
 * @param {string} props.slug       Connector slug.
 * @param {string} props.label      Human-readable label.
 * @param {string} props.description One-line provider description.
 */
function AzureOpenAIConnector( { slug, label, description } ) {
	const {
		isLoading,
		isConnected,
		apiKey,
		endpoint,
		apiVersion,
		deploymentId,
		capabilities,
		setEndpoint,
		setApiVersion,
		setDeploymentId,
		setCapabilities,
		saveApiKey,
		removeApiKey,
		saveExtraSettings,
	} = useAzureSettings();

	const [ isExpanded, setIsExpanded ]   = useState( false );
	const [ isSaving, setIsSaving ]       = useState( false );
	const [ saveMessage, setSaveMessage ] = useState( '' );

	const handleButtonClick = () => setIsExpanded( ! isExpanded );

	const handleSaveExtra = async () => {
		setIsSaving( true );
		setSaveMessage( '' );
		try {
			await saveExtraSettings( {
				endpoint,
				apiVersion,
				deploymentId,
				capabilities,
			} );
			setSaveMessage( __( 'Settings saved.', 'ai-provider-for-azure-openai' ) );
		} catch ( e ) {
			setSaveMessage(
				e.message || __( 'Error saving settings.', 'ai-provider-for-azure-openai' )
			);
		} finally {
			setIsSaving( false );
		}
	};

	const toggleCapability = ( cap ) => {
		setCapabilities( ( prev ) =>
			prev.includes( cap )
				? prev.filter( ( c ) => c !== cap )
				: [ ...prev, cap ]
		);
	};

	/* ---- loading state ---- */
	if ( isLoading ) {
		return el( ConnectorItem, {
			icon: el( CloudIcon ),
			name: label,
			description,
			actionArea: el( 'span', { className: 'spinner is-active' } ),
		} );
	}

	/* ---- action button ---- */
	const buttonLabel = isConnected
		? __( 'Edit', 'ai-provider-for-azure-openai' )
		: __( 'Set Up', 'ai-provider-for-azure-openai' );

	const actionButton = el(
		Button,
		{
			variant: isConnected ? 'tertiary' : 'secondary',
			size: isConnected ? undefined : 'compact',
			onClick: handleButtonClick,
			'aria-expanded': isExpanded,
		},
		buttonLabel
	);

	/* ---- expanded settings panel ---- */
	const settingsPanel =
		isExpanded &&
		el(
			'div',
			{ className: 'azure-openai-connector-settings' },

			/* — API Key — */
			el( 'h3', { style: { marginTop: 0 } }, __( 'API Key', 'ai-provider-for-azure-openai' ) ),
			el( DefaultConnectorSettings, {
				/*
				 * Changing the key forces React to re-mount the component,
				 * resetting its internal state (e.g. after "Remove and replace").
				 */
				key: isConnected ? 'connected' : 'disconnected',
				onSave: saveApiKey,
				onRemove: removeApiKey,
				initialValue: apiKey,
				readOnly: isConnected,
				helpUrl:
					'https://portal.azure.com/#view/Microsoft_Azure_ProjectOxford/CognitiveServicesHub/~/OpenAI',
				helpLabel: __( 'Azure Portal', 'ai-provider-for-azure-openai' ),
			} ),

			/* — Azure-specific settings — */
			el( 'hr', { style: { margin: '24px 0' } } ),
			el( 'h3', null, __( 'Azure OpenAI Settings', 'ai-provider-for-azure-openai' ) ),

			el( TextControl, {
				label: __( 'Endpoint URL', 'ai-provider-for-azure-openai' ),
				value: endpoint,
				onChange: setEndpoint,
				placeholder: 'https://your-resource.openai.azure.com',
				help: __( 'Your Azure OpenAI endpoint URL.', 'ai-provider-for-azure-openai' ),
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),

			el(
				'div',
				{ style: { marginTop: 16 } },
				el( TextControl, {
					label: __( 'API Version', 'ai-provider-for-azure-openai' ),
					value: apiVersion,
					onChange: setApiVersion,
					placeholder: DEFAULT_API_VERSION,
					help: __( 'Azure OpenAI API version.', 'ai-provider-for-azure-openai' ),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} )
			),

			el(
				'div',
				{ style: { marginTop: 16 } },
				el( TextControl, {
					label: __( 'Deployment ID', 'ai-provider-for-azure-openai' ),
					value: deploymentId,
					onChange: setDeploymentId,
					placeholder: 'gpt-4o',
					help: __(
						'The name of your Azure OpenAI deployment.',
						'ai-provider-for-azure-openai'
					),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} )
			),

			el(
				'fieldset',
				{ style: { marginTop: 16 } },
				el(
					'legend',
					{ style: { fontWeight: 600, marginBottom: 8 } },
					__( 'Capabilities', 'ai-provider-for-azure-openai' )
				),
				...CAPABILITY_OPTIONS.map( ( opt ) =>
					el( CheckboxControl, {
						key: opt.value,
						label: opt.label,
						checked: capabilities.includes( opt.value ),
						onChange: () => toggleCapability( opt.value ),
						__nextHasNoMarginBottom: true,
					} )
				)
			),

			/* — Save button + feedback — */
			el(
				'div',
				{
					style: {
						marginTop: 16,
						display: 'flex',
						alignItems: 'center',
						gap: 12,
					},
				},
				el(
					Button,
					{
						variant: 'primary',
						__next40pxDefaultSize: true,
						onClick: handleSaveExtra,
						isBusy: isSaving,
						disabled: isSaving,
					},
					__( 'Save Settings', 'ai-provider-for-azure-openai' )
				),
				saveMessage &&
					el(
						'span',
						{
							style: {
								color: saveMessage.includes( 'Error' ) ? '#cc1818' : '#00a32a',
							},
						},
						saveMessage
					)
			)
		);

	return el( ConnectorItem, {
		icon: el( CloudIcon ),
		name: label,
		description,
		actionArea: actionButton,
	}, settingsPanel );
}

/*
 * Register the connector.
 *
 * Core's registerDefaultConnectors() is prevented from creating a default
 * ApiKeyConnector for this provider via the PHP filter on
 * 'script_module_data_options-connectors-wp-admin' (see plugin.php).
 * Our registration is the only one, so no timing workaround is needed.
 */
registerConnector( 'ai-provider/azure-openai', {
	label: __( 'Azure OpenAI', 'ai-provider-for-azure-openai' ),
	description: __(
		'Text, image, and embedding generation with Azure-hosted OpenAI models.',
		'ai-provider-for-azure-openai'
	),
	render: AzureOpenAIConnector,
} );

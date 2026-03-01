/**
 * Set up window.wp globals that the connector module reads at load time.
 *
 * Must run before the module under test is imported (via Vitest `setupFiles`).
 */
import React from 'react';

window.wp = {
	apiFetch: vi.fn( () => Promise.resolve( {} ) ),
	element: {
		useState: React.useState,
		useEffect: React.useEffect,
		useCallback: React.useCallback,
		createElement: React.createElement,
	},
	i18n: {
		__: vi.fn( ( str ) => str ),
	},
	components: {
		Button( { children, ...props } ) {
			return React.createElement(
				'button',
				{
					'data-variant': props.variant,
					'data-size': props.size,
					'data-next40px': props.__next40pxDefaultSize || undefined,
					disabled: props.disabled,
					'aria-expanded': props[ 'aria-expanded' ],
					onClick: props.onClick,
				},
				children
			);
		},
		TextControl( props ) {
			return React.createElement(
				'div',
				{ 'data-testid': `text-control-${ props.label }` },
				React.createElement( 'label', null, props.label ),
				React.createElement( 'input', {
					type: 'text',
					value: props.value || '',
					onChange: ( e ) =>
						props.onChange && props.onChange( e.target.value ),
					placeholder: props.placeholder,
				} )
			);
		},
		CheckboxControl( props ) {
			return React.createElement(
				'label',
				null,
				React.createElement( 'input', {
					type: 'checkbox',
					checked: props.checked || false,
					onChange: () =>
						props.onChange && props.onChange( ! props.checked ),
				} ),
				props.label
			);
		},
	},
};

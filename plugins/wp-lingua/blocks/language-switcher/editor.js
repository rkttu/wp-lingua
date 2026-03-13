( function ( blocks, element, blockEditor, components ) {
	var el             = element.createElement;
	var useBlockProps  = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody      = components.PanelBody;
	var SelectControl  = components.SelectControl;
	var registerBlockType = blocks.registerBlockType;

	registerBlockType( 'lingua/language-switcher', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				'div',
				Object.assign( {}, blockProps, {
					style: Object.assign( {}, blockProps.style, {
						display: 'inline-flex',
						alignItems: 'center',
						gap: '0.35em',
						padding: '0.25em 0.6em',
						background: '#f0f0f0',
						borderRadius: '3px',
						fontSize: '0.8em',
						color: '#666',
						lineHeight: '1.4',
					} ),
				} ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Language Switcher Settings', initialOpen: true },
						el( SelectControl, {
							label: 'Display Style',
							value: attributes.style,
							options: [
								{ label: 'Dropdown', value: 'dropdown' },
								{ label: 'Buttons', value: 'buttons' },
							],
							onChange: function ( value ) {
								setAttributes( { style: value } );
							},
						} )
					)
				),
				el( 'span', null, '\uD83C\uDF10' ),
				el( 'span', null, attributes.style === 'buttons' ? 'Language Switcher' : '\u25BE Language' )
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );

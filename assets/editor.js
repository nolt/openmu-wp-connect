/**
 * Editor side of the OpenMU WP Connect blocks (plain JS, no build step).
 *
 * All three blocks are dynamic: the editor shows the same markup as the site
 * through ServerSideRender, and the sidebar edits the few block settings.
 * Block names, titles and attributes come from each block.json.
 */
( function ( wp ) {
	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, RangeControl, SelectControl, TextControl, Disabled } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	/** Live preview of a block, rendered by its render.php; links inside are not clickable. */
	function preview( name, attributes ) {
		return el( Disabled, null, el( ServerSideRender, { block: name, attributes } ) );
	}

	// Server status: no settings.
	wp.blocks.registerBlockType( 'openmu/server-status', {
		edit: () => el( 'div', useBlockProps(), preview( 'openmu/server-status', {} ) ),
		save: () => null,
	} );

	// Ranking: number of rows and an optional class filter.
	wp.blocks.registerBlockType( 'openmu/ranking', {
		edit: ( { attributes, setAttributes } ) => {
			const classes = ( window.openmuWpConnect && window.openmuWpConnect.classes ) || [];
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Ranking', 'openmu-wp-connect' ) },
						el( RangeControl, {
							label: __( 'Number of characters', 'openmu-wp-connect' ),
							min: 1,
							max: 100,
							value: attributes.limit,
							onChange: ( limit ) => setAttributes( { limit } ),
						} ),
						el( SelectControl, {
							label: __( 'Class', 'openmu-wp-connect' ),
							value: attributes.characterClass,
							options: [ { label: __( 'All classes', 'openmu-wp-connect' ), value: '' } ].concat(
								classes.map( ( c ) => ( { label: c, value: c } ) )
							),
							onChange: ( characterClass ) => setAttributes( { characterClass } ),
						} )
					)
				),
				preview( 'openmu/ranking', attributes )
			);
		},
		save: () => null,
	} );

	// Armory: empty name = visitors search; a name = always show that character.
	wp.blocks.registerBlockType( 'openmu/armory', {
		edit: ( { attributes, setAttributes } ) =>
			el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Armory', 'openmu-wp-connect' ) },
						el( TextControl, {
							label: __( 'Always show this character', 'openmu-wp-connect' ),
							help: __( 'Leave empty to let visitors search by name.', 'openmu-wp-connect' ),
							maxLength: 10,
							value: attributes.characterName,
							onChange: ( characterName ) => setAttributes( { characterName } ),
						} )
					)
				),
				preview( 'openmu/armory', attributes )
			),
		save: () => null,
	} );
} )( window.wp );

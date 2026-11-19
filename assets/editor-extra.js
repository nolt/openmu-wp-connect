/**
 * Editor side of the website-API blocks of OpenMU WP Connect (plain JS, no build step):
 * player account, event schedule and mini game ranking.
 *
 * Like assets/editor.js: the blocks are dynamic, the editor shows the same
 * markup as the site through ServerSideRender, and the sidebar edits the few
 * block settings. Block names, titles and attributes come from each block.json.
 */
( function ( wp ) {
	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, RangeControl, SelectControl, ToggleControl, Disabled } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	/**
	 * Mini game types of OpenMU (MiniGameType enum) that can have a leaderboard.
	 * The game's English names are used on purpose, as players know them.
	 */
	const miniGameTypes = [
		[ 'BloodCastle', 'Blood Castle' ],
		[ 'DevilSquare', 'Devil Square' ],
		[ 'ChaosCastle', 'Chaos Castle' ],
		[ 'IllusionTemple', 'Illusion Temple' ],
		[ 'Doppelganger', 'Doppelganger' ],
		[ 'ImperialGuardian', 'Imperial Guardian' ],
	];

	/** Live preview of a block, rendered by its render.php; forms and links inside are not usable. */
	function preview( name, attributes ) {
		return el( Disabled, null, el( ServerSideRender, { block: name, attributes } ) );
	}

	/** Block wrapper with a settings panel in the sidebar and the live preview. */
	function withPanel( name, title, attributes, controls ) {
		return el(
			'div',
			useBlockProps(),
			el( InspectorControls, null, el( PanelBody, { title }, ...controls ) ),
			preview( name, attributes )
		);
	}

	// Player account: registration can be switched off, e.g. while the server is closed for new players.
	wp.blocks.registerBlockType( 'openmu/account', {
		edit: ( { attributes, setAttributes } ) =>
			withPanel( 'openmu/account', __( 'Player account', 'openmu-wp-connect' ), attributes, [
				el( ToggleControl, {
					label: __( 'Allow creating accounts', 'openmu-wp-connect' ),
					help: __( 'Shows a registration form next to the login form.', 'openmu-wp-connect' ),
					checked: attributes.allowRegistration,
					onChange: ( allowRegistration ) => setAttributes( { allowRegistration } ),
				} ),
			] ),
		save: () => null,
	} );

	// Event schedule: how many events per server.
	wp.blocks.registerBlockType( 'openmu/events', {
		edit: ( { attributes, setAttributes } ) =>
			withPanel( 'openmu/events', __( 'Event schedule', 'openmu-wp-connect' ), attributes, [
				el( RangeControl, {
					label: __( 'Events per server', 'openmu-wp-connect' ),
					min: 1,
					max: 50,
					value: attributes.limit,
					onChange: ( limit ) => setAttributes( { limit } ),
				} ),
			] ),
		save: () => null,
	} );

	// Mini game ranking: mini game shown first and the period; visitors can switch the mini game.
	wp.blocks.registerBlockType( 'openmu/minigame-ranking', {
		edit: ( { attributes, setAttributes } ) =>
			withPanel( 'openmu/minigame-ranking', __( 'Mini game ranking', 'openmu-wp-connect' ), attributes, [
				el( SelectControl, {
					label: __( 'Mini game shown first', 'openmu-wp-connect' ),
					value: attributes.miniGameType,
					options: [ { label: __( 'First with results (server decides)', 'openmu-wp-connect' ), value: '' } ].concat(
						miniGameTypes.map( ( [ value, label ] ) => ( { label, value } ) )
					),
					onChange: ( miniGameType ) => setAttributes( { miniGameType } ),
				} ),
				el( RangeControl, {
					label: __( 'Period in days', 'openmu-wp-connect' ),
					help: __( '0 = the default period of the game server.', 'openmu-wp-connect' ),
					min: 0,
					max: 365,
					value: attributes.days,
					onChange: ( days ) => setAttributes( { days: days || 0 } ),
				} ),
			] ),
		save: () => null,
	} );
} )( window.wp );

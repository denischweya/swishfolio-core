/**
 * Design-token plumbing for the change preview.
 *
 * PHP's ValueStage expands an AI-supplied slug (`accent-2`) into the storage
 * value blocks actually hold (`var(--wp--preset--color--accent-2)`), so by the
 * time the editor sees an action the slug is gone. This module walks that back
 * for display and resolves a colour slug to a real swatch from the editor's own
 * palette, rather than trusting the custom property to resolve in the sidebar's
 * document.
 *
 * Mirrors TokenResolver::SETS.
 */

const CSS_SEGMENTS = {
	color: 'color',
	spacing: 'spacing',
	fontSize: 'font-size',
};

const LITERAL_COLOUR =
	/^(#[0-9a-f]{3,8}|rgba?\(|hsla?\(|color-mix\(|oklch\(|oklab\(|lab\(|lch\()/i;

/**
 * @param {string} tokenSet One of color|spacing|fontSize.
 * @param {string} slug     Token slug.
 *
 * @return {string} The stored custom-property reference.
 */
export function slugToStorageValue( tokenSet, slug ) {
	const segment = CSS_SEGMENTS[ tokenSet ];

	return segment
		? 'var(--wp--preset--' + segment + '--' + slug + ')'
		: String( slug );
}

/**
 * Inverse of slugToStorageValue().
 *
 * @param {string} tokenSet One of color|spacing|fontSize.
 * @param {*}      value    Stored attribute value.
 *
 * @return {string|null} The slug, or null when the value is not a token reference.
 */
export function storageValueToSlug( tokenSet, value ) {
	const segment = CSS_SEGMENTS[ tokenSet ];

	if ( ! segment || typeof value !== 'string' ) {
		return null;
	}

	const pattern = new RegExp(
		'^var\\(\\s*--wp--preset--' + segment + '--([A-Za-z0-9_-]+)\\s*\\)$'
	);
	const match = value.match( pattern );

	return match ? match[ 1 ] : null;
}

/**
 * @param {*} value Attribute value.
 *
 * @return {boolean} Whether the value looks like a paintable colour.
 */
export function isLiteralColour( value ) {
	return typeof value === 'string' && LITERAL_COLOUR.test( value.trim() );
}

function collectPalette( settings ) {
	const palette = {};

	const add = ( entries ) => {
		( Array.isArray( entries ) ? entries : [] ).forEach( ( entry ) => {
			if ( entry && entry.slug && entry.color ) {
				palette[ entry.slug ] = {
					color: entry.color,
					name: entry.name || entry.slug,
				};
			}
		} );
	};

	const features = settings && settings.__experimentalFeatures;
	const byOrigin = features && features.color ? features.color.palette : null;

	// Weakest origin first: a later origin overrides a slug of the same name,
	// matching TokenResolver::ORIGINS.
	if ( byOrigin && typeof byOrigin === 'object' ) {
		[ 'default', 'theme', 'custom' ].forEach( ( origin ) =>
			add( byOrigin[ origin ] )
		);
	}

	add( settings && settings.colors );

	return palette;
}

/**
 * Build a resolver that turns an attribute value into a swatch.
 *
 * @param {Object} settings Block editor settings (`getSettings()`).
 *
 * @return {Function} ( value ) => { css, label, slug } | null
 */
export function createColourResolver( settings ) {
	const palette = collectPalette( settings );

	return function resolveColour( value ) {
		if ( typeof value !== 'string' || value.trim() === '' ) {
			return null;
		}

		const slug = storageValueToSlug( 'color', value );

		if ( slug ) {
			const preset = palette[ slug ];

			return {
				css: preset ? preset.color : null,
				label: preset ? preset.name : slug,
				slug,
			};
		}

		if ( isLiteralColour( value ) ) {
			return { css: value.trim(), label: value.trim(), slug: null };
		}

		return null;
	};
}

/**
 * Display form for a token value: the slug, not the custom property.
 *
 * @param {Object|null} field Field spec.
 * @param {*}           value Attribute value.
 *
 * @return {string|null} The slug when the value is a token reference.
 */
export function tokenSlugFor( field, value ) {
	if ( ! field || field.type !== 'token' || ! field.tokenSet ) {
		return null;
	}

	return storageValueToSlug( field.tokenSet, value );
}

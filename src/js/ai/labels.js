/**
 * Human-readable labels for block names, attributes and values.
 *
 * Manifests carry machine names (`ctaText`, `overlayOpacity`); the change
 * preview has to read as English. `role` wins over the attribute name when the
 * manifest supplies one, which is what lets the preview label fields
 * meaningfully without hardcoding block knowledge.
 */

import { __, sprintf } from '@wordpress/i18n';
import { getBlockType } from '@wordpress/blocks';

const ACRONYMS = {
	cta: 'CTA',
	url: 'URL',
	id: 'ID',
	cv: 'CV',
	faq: 'FAQ',
	seo: 'SEO',
	svg: 'SVG',
	ui: 'UI',
};

const ROLE_LABELS = {
	headline: () => __( 'Headline', 'swishfolio-core' ),
	supporting: () => __( 'Supporting text', 'swishfolio-core' ),
	body: () => __( 'Body text', 'swishfolio-core' ),
	eyebrow: () => __( 'Eyebrow', 'swishfolio-core' ),
	'primary-cta': () => __( 'Primary button', 'swishfolio-core' ),
	'secondary-cta': () => __( 'Secondary button', 'swishfolio-core' ),
};

/**
 * @param {string} name Machine name, camelCase or kebab-case.
 *
 * @return {string} Sentence-case words.
 */
export function humanise( name ) {
	const words = String( name || '' )
		.replace( /([a-z0-9])([A-Z])/g, '$1 $2' )
		.replace( /[-_/]+/g, ' ' )
		.trim()
		.split( /\s+/ )
		.filter( Boolean );

	if ( ! words.length ) {
		return '';
	}

	return words
		.map( ( word, index ) => {
			const lower = word.toLowerCase();

			if ( ACRONYMS[ lower ] ) {
				return ACRONYMS[ lower ];
			}

			if ( index === 0 ) {
				return lower.charAt( 0 ).toUpperCase() + lower.slice( 1 );
			}

			return lower;
		} )
		.join( ' ' );
}

/**
 * @param {string} blockName Full block name.
 *
 * @return {string} The registered block title, or a humanised slug.
 */
export function blockLabel( blockName ) {
	if ( ! blockName ) {
		return __( 'Block', 'swishfolio-core' );
	}

	const type = getBlockType( blockName );

	if ( type && type.title ) {
		return type.title;
	}

	const slug =
		blockName.indexOf( '/' ) === -1
			? blockName
			: blockName.split( '/' ).pop();

	return humanise( slug );
}

/**
 * @param {string}      attribute Attribute name.
 * @param {Object|null} field     Field spec from the manifest.
 *
 * @return {string} Label for the change preview.
 */
export function fieldLabel( attribute, field ) {
	if ( field && field.role && ROLE_LABELS[ field.role ] ) {
		return ROLE_LABELS[ field.role ]();
	}

	return humanise( attribute );
}

function stripTags( html ) {
	return String( html ).replace( /<[^>]*>/g, '' );
}

function decodeEntities( text ) {
	return String( text )
		.replace( /&nbsp;/g, ' ' )
		.replace( /&amp;/g, '&' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&quot;/g, '"' )
		.replace( /&#0?39;|&apos;/g, "'" );
}

function truncate( text, limit ) {
	const value = String( text );

	return value.length > limit ? value.slice( 0, limit - 1 ) + '…' : value;
}

/**
 * @return {string} The placeholder shown where a value is absent.
 */
export function emptyValueLabel() {
	return __( 'not set', 'swishfolio-core' );
}

/**
 * Render one attribute value for display.
 *
 * @param {*}           value Raw attribute value.
 * @param {Object|null} field Field spec, when known.
 *
 * @return {string} Display string.
 */
export function formatValue( value, field ) {
	if ( value === undefined || value === null || value === '' ) {
		return emptyValueLabel();
	}

	if ( typeof value === 'boolean' ) {
		return value
			? __( 'Yes', 'swishfolio-core' )
			: __( 'No', 'swishfolio-core' );
	}

	if ( typeof value === 'number' ) {
		const unit =
			field &&
			field.unit &&
			field.unit !== 'count' &&
			field.unit !== 'level'
				? field.unit
				: '';

		return unit === ''
			? String( value )
			: sprintf( '%1$s%2$s', value, unit );
	}

	if ( typeof value === 'string' ) {
		const text = decodeEntities( stripTags( value ) ).trim();

		return text === '' ? emptyValueLabel() : truncate( text, 140 );
	}

	try {
		return truncate( JSON.stringify( value ), 140 );
	} catch ( error ) {
		return __( '(unreadable value)', 'swishfolio-core' );
	}
}

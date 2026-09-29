/**
 * Suggestion chips, filtered by what the selected block's manifest supports.
 *
 * A chip that proposes a change the block cannot make is worse than no chip: it
 * teaches the user that AI editing is unreliable. Every chip here declares a
 * predicate over the capability and is dropped when it fails.
 */

import { __, sprintf } from '@wordpress/i18n';

import { getFieldList } from './capabilities';
import { blockLabel } from './labels';

function fields( capability ) {
	return getFieldList( capability );
}

function hasRole( capability, role ) {
	return fields( capability ).some( ( field ) => field.role === role );
}

function hasType( capability, type ) {
	return fields( capability ).some( ( field ) => field.type === type );
}

function textFieldCount( capability ) {
	return fields( capability ).filter(
		( field ) => field.type === 'text' || field.type === 'richtext'
	).length;
}

function colourFieldCount( capability ) {
	return fields( capability ).filter(
		( field ) => field.type === 'token' && field.tokenSet === 'color'
	).length;
}

function hasGroupedType( capability, group, type ) {
	return fields( capability ).some(
		( field ) => field.group === group && field.type === type
	);
}

function allowedChild( capability ) {
	const allowed =
		capability && capability.children ? capability.children.allowed : [];

	return allowed && allowed.length ? allowed[ 0 ] : null;
}

const CATALOGUE = [
	{
		id: 'tighten-headline',
		test: ( capability ) => hasRole( capability, 'headline' ),
		label: () => __( 'Tighten the headline', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Tighten the headline so it reads in one breath, keeping the same meaning.',
				'swishfolio-core'
			),
	},
	{
		id: 'professional-voice',
		test: ( capability ) => textFieldCount( capability ) > 0,
		label: () =>
			__( 'Rewrite for a professional audience', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Rewrite the copy for a professional audience: plainer, calmer, no hype.',
				'swishfolio-core'
			),
	},
	{
		id: 'shorten-copy',
		test: ( capability ) => textFieldCount( capability ) > 1,
		label: () => __( 'Shorten the copy', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Cut the copy down — say the same thing in noticeably fewer words.',
				'swishfolio-core'
			),
	},
	{
		id: 'sharpen-cta',
		test: ( capability ) => hasRole( capability, 'primary-cta' ),
		label: () => __( 'Sharpen the call to action', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Make the call to action more specific about what happens when it is clicked.',
				'swishfolio-core'
			),
	},
	{
		id: 'raise-contrast',
		test: ( capability ) => colourFieldCount( capability ) > 0,
		label: () => __( 'Raise the contrast', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Raise the contrast between text and background using the theme palette.',
				'swishfolio-core'
			),
	},
	{
		id: 'warmer-palette',
		test: ( capability ) => colourFieldCount( capability ) > 1,
		label: () => __( 'Try a warmer palette', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Recolour this block with warmer tones from the theme palette.',
				'swishfolio-core'
			),
	},
	{
		id: 'different-layout',
		test: ( capability ) => hasGroupedType( capability, 'layout', 'enum' ),
		label: () => __( 'Try a different layout', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Try a different layout arrangement for this block.',
				'swishfolio-core'
			),
	},
	{
		id: 'more-compact',
		test: ( capability ) =>
			hasGroupedType( capability, 'layout', 'number' ),
		label: () => __( 'Make it more compact', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Make this block more compact without cramping the content.',
				'swishfolio-core'
			),
	},
	{
		id: 'hide-extras',
		test: ( capability ) => hasType( capability, 'boolean' ),
		label: () => __( 'Hide the non-essentials', 'swishfolio-core' ),
		prompt: () =>
			__(
				'Turn off the optional elements so only the essentials show.',
				'swishfolio-core'
			),
	},
	{
		id: 'add-child',
		test: ( capability ) => allowedChild( capability ) !== null,
		label: ( capability ) =>
			sprintf(
				/* translators: %s: child block title. */
				__( 'Add another %s', 'swishfolio-core' ),
				blockLabel( allowedChild( capability ) )
			),
		prompt: ( capability ) =>
			sprintf(
				/* translators: %s: child block title. */
				__(
					'Add one more %s that fits the ones already here.',
					'swishfolio-core'
				),
				blockLabel( allowedChild( capability ) )
			),
	},
];

/**
 * @param {Object|null} capability Capability for the selected block.
 * @param {number}      limit      Maximum chips to return.
 *
 * @return {Array} [ { id, label, prompt } ]
 */
export function getSuggestions( capability, limit = 6 ) {
	if ( ! capability ) {
		return [];
	}

	return CATALOGUE.filter( ( entry ) => entry.test( capability ) )
		.slice( 0, limit )
		.map( ( entry ) => ( {
			id: entry.id,
			label: entry.label( capability ),
			prompt: entry.prompt( capability ),
		} ) );
}

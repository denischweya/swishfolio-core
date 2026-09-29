/**
 * Turns validated actions into reviewable rows.
 *
 * One row per action, because that is the unit the user accepts or rejects. An
 * attribute action lists each field it touches inside its row, with the value
 * the block holds now beside the value the AI proposes.
 *
 * "Before" is read once, when the response arrives, so the preview describes the
 * state the user was looking at rather than drifting if they keep editing.
 */

import { __ } from '@wordpress/i18n';

import {
	ACTION_INSERT,
	ACTION_REMOVE,
	ACTION_UPDATE_ATTRIBUTES,
	ACTION_UPDATE_CONTENT,
} from './constants';
import { getContentAttribute, getField } from './capabilities';
import { blockLabel, fieldLabel, formatValue } from './labels';
import { isLiteralColour, tokenSlugFor } from './tokens';

function typeLabel( type ) {
	switch ( type ) {
		case ACTION_UPDATE_ATTRIBUTES:
			return __( 'Update', 'swishfolio-core' );

		case ACTION_UPDATE_CONTENT:
			return __( 'Rewrite', 'swishfolio-core' );

		case ACTION_INSERT:
			return __( 'Add block', 'swishfolio-core' );

		case ACTION_REMOVE:
			return __( 'Remove block', 'swishfolio-core' );

		default:
			return __( 'Change', 'swishfolio-core' );
	}
}

function describeSide( value, field, resolveColour ) {
	const slug = tokenSlugFor( field, value );
	const isColour =
		( field && field.type === 'token' && field.tokenSet === 'color' ) ||
		isLiteralColour( value );

	return {
		text: slug || formatValue( value, field ),
		colour: isColour ? resolveColour( value ) : null,
	};
}

function fieldRows( capability, values, attributes, resolveColour ) {
	return Object.keys( values || {} ).map( ( attribute ) => {
		const field = getField( capability, attribute );
		const before = attributes ? attributes[ attribute ] : undefined;

		return {
			attribute,
			label: fieldLabel( attribute, field ),
			before: attributes
				? describeSide( before, field, resolveColour )
				: null,
			after: describeSide( values[ attribute ], field, resolveColour ),
		};
	} );
}

/**
 * @param {Array}  actions Validated actions from `/ai/edit`.
 * @param {Object} deps    { getBlockName, getBlockAttributes, getCapability, resolveColour }.
 *
 * @return {Array} Rows, one per action, in action order.
 */
export function buildActionRows( actions, deps ) {
	const { getBlockName, getBlockAttributes, getCapability, resolveColour } =
		deps;

	return ( Array.isArray( actions ) ? actions : [] ).map(
		( action, index ) => {
			const type = action && action.type ? action.type : '';
			const isInsert = type === ACTION_INSERT;
			const targetClientId = isInsert
				? action.parentClientId
				: action.clientId;
			const targetBlockName = getBlockName( targetClientId );

			const row = {
				index,
				type,
				typeLabel: typeLabel( type ),
				clientId: targetClientId,
				blockName: isInsert ? action.blockName : targetBlockName,
				blockLabel: blockLabel(
					isInsert ? action.blockName : targetBlockName
				),
				parentLabel: isInsert ? blockLabel( targetBlockName ) : null,
				reason: action && action.reason ? String( action.reason ) : '',
				note: '',
				fields: [],
			};

			if ( type === ACTION_UPDATE_ATTRIBUTES ) {
				row.fields = fieldRows(
					getCapability( targetBlockName ),
					action.changes,
					getBlockAttributes( targetClientId ) || {},
					resolveColour
				);

				return row;
			}

			if ( type === ACTION_UPDATE_CONTENT ) {
				const capability = getCapability( targetBlockName );
				const attribute = getContentAttribute( capability );

				if ( attribute ) {
					row.fields = fieldRows(
						capability,
						{ [ attribute ]: action.content },
						getBlockAttributes( targetClientId ) || {},
						resolveColour
					);
				} else {
					row.note = __(
						'This block has no editable text content.',
						'swishfolio-core'
					);
				}

				return row;
			}

			if ( isInsert ) {
				row.fields = fieldRows(
					getCapability( action.blockName ),
					action.attributes,
					null,
					resolveColour
				);
				row.note = __(
					'Added as a new child block.',
					'swishfolio-core'
				);

				return row;
			}

			if ( type === ACTION_REMOVE ) {
				row.note = __(
					'Removed from the selection.',
					'swishfolio-core'
				);
			}

			return row;
		}
	);
}

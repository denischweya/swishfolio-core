/**
 * Reads the AI capability manifests PHP localises into the editor.
 *
 * PHP owns the manifests (`blocks/<name>/ai.json` plus
 * `src/AI/Manifests/core/*.json`) and is the only authority on what is
 * AI-editable; this module never invents a field. Two payload shapes are
 * accepted so the wiring can send whichever is cheaper to produce: the
 * flattened one built from `BlockCapability` (`fields` keyed by attribute) and
 * the raw manifest one (`capabilities` keyed by group).
 *
 * Field order is preserved, because `update_block_content` targets the *first*
 * richtext field — exactly the rule AllowlistStage and ValueStage apply in PHP.
 */

import { DEFAULT_MAX_ACTIONS, GLOBAL_DATA_KEY } from './constants';

const FIELD_TYPES = [
	'text',
	'url',
	'richtext',
	'number',
	'boolean',
	'enum',
	'token',
];

const TIERS = [ 'full', 'content' ];

let cachedSource;
let cachedMap;

/**
 * @return {Object} The localised payload, or an empty object when absent.
 */
export function getEditorData() {
	const data =
		typeof window === 'undefined' ? null : window[ GLOBAL_DATA_KEY ];

	return data && typeof data === 'object' ? data : {};
}

/**
 * @return {number} The server's action cap, used only to label the UI.
 */
export function getMaxActions() {
	const max = Number( getEditorData().maxActions );

	return Number.isFinite( max ) && max > 0
		? Math.floor( max )
		: DEFAULT_MAX_ACTIONS;
}

function toFiniteOrNull( value ) {
	const number = Number( value );

	return value === null ||
		value === undefined ||
		value === '' ||
		! Number.isFinite( number )
		? null
		: number;
}

function normaliseField( attribute, spec, group ) {
	if ( ! spec || typeof spec !== 'object' ) {
		return null;
	}

	const hasEnum = Array.isArray( spec.enum ) && spec.enum.length > 0;
	const type = spec.type || ( hasEnum ? 'enum' : null );

	if ( ! type || FIELD_TYPES.indexOf( type ) === -1 ) {
		return null;
	}

	if ( type === 'enum' && ! hasEnum ) {
		return null;
	}

	if ( type === 'token' && ! spec.tokenSet ) {
		return null;
	}

	return {
		name: attribute,
		type,
		group: spec.group || group || 'style',
		role: spec.role || null,
		enum: hasEnum ? spec.enum.slice() : [],
		tokenSet: spec.tokenSet || null,
		min: toFiniteOrNull( spec.min ),
		max: toFiniteOrNull( spec.max ),
		maxLength: toFiniteOrNull( spec.maxLength ),
		unit: spec.unit || null,
	};
}

function normaliseChildren( raw ) {
	const children = raw && typeof raw === 'object' ? raw : {};

	return {
		allowed: Array.isArray( children.allowed )
			? children.allowed.slice()
			: [],
		min: toFiniteOrNull( children.min ),
		max: toFiniteOrNull( children.max ),
	};
}

function normaliseCapability( blockName, raw ) {
	if ( ! raw || typeof raw !== 'object' ) {
		return null;
	}

	const tier = TIERS.indexOf( raw.tier ) === -1 ? 'full' : raw.tier;
	const fields = {};

	const addField = ( attribute, spec, group ) => {
		const field = normaliseField( attribute, spec, group );

		if ( field ) {
			fields[ attribute ] = field;
		}
	};

	if ( raw.fields && typeof raw.fields === 'object' ) {
		Object.keys( raw.fields ).forEach( ( attribute ) => {
			addField( attribute, raw.fields[ attribute ], null );
		} );
	} else if ( raw.capabilities && typeof raw.capabilities === 'object' ) {
		Object.keys( raw.capabilities ).forEach( ( group ) => {
			const groupFields = raw.capabilities[ group ];

			if ( ! groupFields || typeof groupFields !== 'object' ) {
				return;
			}

			Object.keys( groupFields ).forEach( ( attribute ) => {
				addField( attribute, groupFields[ attribute ], group );
			} );
		} );
	}

	return {
		blockName,
		tier,
		purpose: typeof raw.purpose === 'string' ? raw.purpose : '',
		summary: typeof raw.summary === 'string' ? raw.summary : '',
		fields,
		children: normaliseChildren( raw.children ),
	};
}

/**
 * @return {Object} Normalised capabilities keyed by block name.
 */
export function getCapabilityMap() {
	const data = getEditorData();
	const source =
		data.capabilities && typeof data.capabilities === 'object'
			? data.capabilities
			: {};

	if ( source === cachedSource && cachedMap ) {
		return cachedMap;
	}

	const map = {};

	Object.keys( source ).forEach( ( blockName ) => {
		const capability = normaliseCapability(
			blockName,
			source[ blockName ]
		);

		if ( capability ) {
			map[ blockName ] = capability;
		}
	} );

	cachedSource = source;
	cachedMap = map;

	return map;
}

/**
 * @param {string} blockName Full block name.
 *
 * @return {Object|null} The capability, or null when the block is not AI-editable.
 */
export function getCapability( blockName ) {
	if ( ! blockName ) {
		return null;
	}

	const map = getCapabilityMap();

	return Object.prototype.hasOwnProperty.call( map, blockName )
		? map[ blockName ]
		: null;
}

/**
 * @param {string} blockName Full block name.
 *
 * @return {boolean} Whether the block has a manifest.
 */
export function hasCapability( blockName ) {
	return getCapability( blockName ) !== null;
}

/**
 * @return {boolean} Whether PHP localised anything usable at all.
 */
export function isAvailable() {
	return Object.keys( getCapabilityMap() ).length > 0;
}

/**
 * @param {Object|null} capability Capability.
 *
 * @return {Object[]} Fields in manifest order.
 */
export function getFieldList( capability ) {
	if ( ! capability ) {
		return [];
	}

	return Object.keys( capability.fields ).map(
		( attribute ) => capability.fields[ attribute ]
	);
}

/**
 * @param {Object|null} capability Capability.
 *
 * @return {number} How many properties the AI may change on this block.
 */
export function countFields( capability ) {
	return getFieldList( capability ).length;
}

/**
 * @param {Object|null} capability Capability.
 * @param {string}      attribute  Attribute name.
 *
 * @return {Object|null} Field spec, or null when the attribute is not editable.
 */
export function getField( capability, attribute ) {
	if ( ! capability || ! attribute ) {
		return null;
	}

	return Object.prototype.hasOwnProperty.call( capability.fields, attribute )
		? capability.fields[ attribute ]
		: null;
}

/**
 * The attribute an `update_block_content` action writes to.
 *
 * PHP resolves it as the first richtext field in manifest order; this must
 * agree with PHP or the client would write the wrong attribute.
 *
 * @param {Object|null} capability Capability.
 *
 * @return {string|null} Attribute name, or null when the block has no text content.
 */
export function getContentAttribute( capability ) {
	const field = getFieldList( capability ).find(
		( candidate ) => candidate.type === 'richtext'
	);

	return field ? field.name : null;
}

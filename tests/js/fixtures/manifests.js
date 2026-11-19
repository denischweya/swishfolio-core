/**
 * Capability manifests as PHP localises them, trimmed to the fields the tests
 * exercise but otherwise copied from the real files: blocks/hero/ai.json,
 * blocks/bento-cards/ai.json, blocks/bento-card/ai.json and
 * src/AI/Manifests/core/paragraph.json.
 *
 * HERO and BENTO use the raw grouped `capabilities` shape; PARAGRAPH uses the
 * flattened `fields` shape built from BlockCapability. capabilities.js accepts
 * both, so both paths get covered by using both here.
 */

import * as wpBlocks from '../mocks/wp-blocks';

export const HERO = {
	version: 1,
	tier: 'full',
	purpose: 'hero',
	summary:
		'Full-bleed opening section: headline, subheading, up to two CTAs, over a colour or image background.',
	capabilities: {
		content: {
			heading: { type: 'text', maxLength: 120, role: 'headline' },
			subheading: { type: 'text', maxLength: 300, role: 'supporting' },
			ctaText: { type: 'text', maxLength: 40, role: 'primary-cta' },
			ctaUrl: { type: 'url' },
		},
		layout: {
			layout: { enum: [ 'full', 'split-right', 'split-left' ] },
			minHeight: { type: 'number', min: 320, max: 1000, unit: 'px' },
			headingFullWidth: { type: 'boolean' },
		},
		style: {
			backgroundColor: { type: 'token', tokenSet: 'color' },
			textColor: { type: 'token', tokenSet: 'color' },
			overlayOpacity: { type: 'number', min: 0, max: 100, unit: '%' },
		},
	},
	excluded: [ 'heroImage', 'socialLinks' ],
};

/** Flattened shape: tier `content`, one richtext field, nothing else. */
export const PARAGRAPH = {
	blockName: 'core/paragraph',
	version: 1,
	tier: 'content',
	purpose: 'body-text',
	summary: 'A paragraph of body text.',
	fields: {
		content: {
			type: 'richtext',
			group: 'content',
			maxLength: 5000,
			role: 'body',
		},
	},
};

export const BENTO_CARDS = {
	version: 1,
	tier: 'full',
	purpose: 'bento-grid',
	summary:
		'Asymmetric bento grid container; owns shared card styling and an optional CTA.',
	capabilities: {
		style: {
			accentColor: { type: 'token', tokenSet: 'color' },
		},
	},
	children: { allowed: [ 'swishfolio-core/bento-card' ], min: 1, max: 8 },
};

/** Field order matters here: `subtitle` is the first richtext field. */
export const BENTO_CARD = {
	version: 1,
	tier: 'full',
	purpose: 'bento-item',
	summary: 'One bento grid cell with a title, subtitle and description.',
	capabilities: {
		content: {
			subtitle: { type: 'richtext', maxLength: 120, role: 'eyebrow' },
			title: { type: 'richtext', maxLength: 120, role: 'headline' },
			description: { type: 'richtext', maxLength: 400, role: 'body' },
		},
		style: {
			titleColor: { type: 'token', tokenSet: 'color' },
		},
	},
};

export const ALL = {
	'swishfolio-core/hero': HERO,
	'swishfolio-core/bento-cards': BENTO_CARDS,
	'swishfolio-core/bento-card': BENTO_CARD,
	'core/paragraph': PARAGRAPH,
};

/**
 * Put manifests where capabilities.js looks for them.
 *
 * @param {Object} capabilities Block name => manifest. Defaults to ALL.
 * @param {Object} extra		Extra keys on the localised payload.
 */
export function installManifests( capabilities = ALL, extra = {} ) {
	window.sfcoreAiEditor = { capabilities, maxActions: 25, ...extra };
}

/** Remove the localised payload, as on a site where PHP enqueued nothing. */
export function clearManifests() {
	delete window.sfcoreAiEditor;
}

/** Titles for blockLabel(), which reads the block registry. */
export function installBlockTypes() {
	wpBlocks.__setBlockTypes( {
		'swishfolio-core/hero': { title: 'Hero' },
		'swishfolio-core/bento-cards': { title: 'Bento Cards' },
		'swishfolio-core/bento-card': { title: 'Bento Card' },
		'core/paragraph': { title: 'Paragraph' },
		'core/spacer': { title: 'Spacer' },
	} );
}

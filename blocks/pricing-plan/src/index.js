/**
 * Pricing Plan - Entry Point.
 *
 * Child of swishfolio-core/pricing (ADR-0002). Owns title, subtitle,
 * prices, price link, highlight flag + badge, and CTA button. Features are
 * a core/list block locked inside. Styles live in the parent's style.scss.
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import Edit from './edit';
import save from './save';

registerBlockType( metadata.name, {
	icon: 'money-alt',
	edit: Edit,
	save,
} );

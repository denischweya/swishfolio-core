/**
 * Swish Slide - Entry Point.
 *
 * Child of swishfolio-core/swish-slider. Owns image, title, subtitle, and
 * two attribute-based CTA links. Styles live in the parent's style.scss.
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import Edit from './edit';
import save from './save';

registerBlockType( metadata.name, {
	icon: 'format-image',
	edit: Edit,
	save,
} );

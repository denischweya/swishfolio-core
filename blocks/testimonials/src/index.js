/**
 * Testimonials Block - Entry Point.
 *
 * Parent block. Holds shared card styling (background, border, shadow,
 * columns) as CSS custom properties consumed by testimonial-card children.
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/testimonials', {
	icon: 'format-quote',
	edit: Edit,
	save,
} );

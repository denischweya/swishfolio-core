/**
 * Testimonial Card Block - Entry Point.
 *
 * Child of swishfolio-core/testimonials. Each card holds its own quote,
 * attribution, optional avatar (image or initial), and optional URL on
 * the role line.
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import './editor.scss';

registerBlockType( 'swishfolio-core/testimonial-card', {
	icon: 'format-quote',
	edit: Edit,
	save,
} );

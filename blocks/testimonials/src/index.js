/**
 * Testimonials Block
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/testimonials', {
	icon: 'format-quote',
	edit: Edit,
	save: () => null, // Dynamic block - rendered on server
} );

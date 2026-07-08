/**
 * Swish Slider Block
 *
 * A continuous horizontal marquee of image slides. Parent of
 * swishfolio-core/swish-slide.
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import Edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	icon: 'slides',
	edit: Edit,
	save,
} );

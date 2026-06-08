/**
 * Swish Counter Block - Entry Point.
 *
 * Parent block. Holds shared font sizes, colors, weight, gap, and alignment
 * as CSS custom properties consumed by swish-counter-item children.
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/swish-counter', {
	icon: 'chart-bar',
	edit: Edit,
	save,
} );

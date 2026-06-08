/**
 * Counter Item - Entry Point.
 *
 * Child of swishfolio-core/swish-counter. All four fields (prefix, value,
 * suffix, title) are inline-editable RichText spans.
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import './editor.scss';

registerBlockType( 'swishfolio-core/swish-counter-item', {
	icon: 'chart-line',
	edit: Edit,
	save,
} );

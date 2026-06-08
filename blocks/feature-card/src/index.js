/**
 * Feature Card Block - Entry Point.
 *
 * A child block of swishfolio-core/features. Each card is individually
 * selectable in the editor and can override the parent block's globals.
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import './editor.scss';

registerBlockType( 'swishfolio-core/feature-card', {
	icon: 'screenoptions',
	edit: Edit,
	save,
} );

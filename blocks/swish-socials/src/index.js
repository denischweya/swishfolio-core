/**
 * Swish Socials Block
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/swish-socials', {
	icon: 'share',
	edit: Edit,
	save,
} );

/**
 * Latest Posts Block
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/latest-posts', {
	icon: 'list-view',
	edit: Edit,
	save: () => null, // Dynamic block - rendered on server
} );

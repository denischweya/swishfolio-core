/**
 * Portfolio Block
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/portfolio', {
	icon: 'portfolio',
	edit: Edit,
	save: () => null, // Dynamic block - rendered on server
} );

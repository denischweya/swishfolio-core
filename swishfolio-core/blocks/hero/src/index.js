/**
 * Hero Block
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';
import deprecations from './deprecations';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/hero', {
	icon: 'cover-image',
	edit: Edit,
	save,
	deprecated: deprecations,
} );

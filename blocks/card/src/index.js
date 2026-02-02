/**
 * Card Block
 *
 * A versatile card with icon, title, description, and link.
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';
import deprecations from './deprecations';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/card', {
	icon: 'star-filled',
	edit: Edit,
	save,
	deprecated: deprecations,
} );

/**
 * FAQ Block
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';
import deprecations from './deprecations';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/faq', {
	icon: 'editor-help',
	edit: Edit,
	save,
	deprecated: deprecations,
} );

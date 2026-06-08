/**
 * Features Block - Entry Point
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import deprecations from './deprecations';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/features', {
	icon: 'grid-view',
	edit: Edit,
	save,
	deprecated: deprecations,
} );

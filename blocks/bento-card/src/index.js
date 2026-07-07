/**
 * Bento Card - Entry Point.
 *
 * Child of swishfolio-core/bento-cards. Owns image, subtitle, title,
 * description, titleColor and cardSize.
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import Edit from './edit';
import save from './save';

registerBlockType( metadata.name, {
	icon: 'index-card',
	edit: Edit,
	save,
} );

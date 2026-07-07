/**
 * Bento Cards Block
 *
 * A bento-grid style card layout for showcasing portfolio items.
 */

import { registerBlockType } from '@wordpress/blocks';
import { grid as icon } from '@wordpress/icons';
import metadata from '../block.json';
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save,
	deprecated,
} );

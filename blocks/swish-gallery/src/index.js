/**
 * Swish Gallery Block
 */

import { registerBlockType } from '@wordpress/blocks';
import { gallery as icon } from '@wordpress/icons';

import Edit from './edit';
import save from './save';
import metadata from '../block.json';

import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save,
} );

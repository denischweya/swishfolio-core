/**
 * Custom Heading Block
 *
 * A neo-brutalist heading block with pre-heading, heading, and sub-heading elements.
 */

import { registerBlockType } from '@wordpress/blocks';
import { heading as headingIcon } from '@wordpress/icons';

import './style.scss';
import './editor.scss';

import Edit from './edit';
import save from './save';
import deprecations from './deprecations';
import metadata from '../block.json';

registerBlockType( metadata.name, {
	icon: headingIcon,
	edit: Edit,
	save,
	deprecated: deprecations,
} );

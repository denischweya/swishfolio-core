import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/swish-cv', {
	icon: 'list-view',
	edit: Edit,
	save,
} );

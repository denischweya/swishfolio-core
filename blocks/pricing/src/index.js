import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType( 'swishfolio-core/pricing', {
	icon: 'money-alt',
	edit: Edit,
	save,
} );

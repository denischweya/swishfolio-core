/**
 * Text Color Format - Allows coloring selected text in RichText fields
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { registerFormatType, applyFormat, removeFormat } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { ColorPalette, Popover, Button } from '@wordpress/components';
import { color as colorIcon } from '@wordpress/icons';
import { useSetting } from '@wordpress/block-editor';

const FORMAT_NAME = 'swishfolio-core/text-color';

const TextColorButton = ( { isActive, value, onChange } ) => {
	const [ showPopover, setShowPopover ] = useState( false );
	const themeColors = useSetting( 'color.palette' ) || [];

	const onColorChange = ( selectedColor ) => {
		if ( selectedColor ) {
			onChange(
				applyFormat( value, {
					type: FORMAT_NAME,
					attributes: {
						style: `color:${ selectedColor }`,
					},
				} )
			);
		} else {
			onChange( removeFormat( value, FORMAT_NAME ) );
		}
		setShowPopover( false );
	};

	const onRemove = () => {
		onChange( removeFormat( value, FORMAT_NAME ) );
		setShowPopover( false );
	};

	return (
		<>
			<RichTextToolbarButton
				icon={ colorIcon }
				title={ __( 'Text Color', 'swishfolio-core' ) }
				onClick={ () => setShowPopover( ! showPopover ) }
				isActive={ isActive }
			/>
			{ showPopover && (
				<Popover
					position="bottom center"
					onClose={ () => setShowPopover( false ) }
					className="sfcore-text-color-popover"
				>
					<div style={ { padding: '16px', minWidth: '200px' } }>
						<p style={ { margin: '0 0 8px', fontWeight: 600, fontSize: '12px' } }>
							{ __( 'Select Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							onChange={ onColorChange }
							clearable={ false }
						/>
						{ isActive && (
							<Button
								variant="secondary"
								isDestructive
								onClick={ onRemove }
								style={ { marginTop: '8px', width: '100%' } }
							>
								{ __( 'Remove Color', 'swishfolio-core' ) }
							</Button>
						) }
					</div>
				</Popover>
			) }
		</>
	);
};

registerFormatType( FORMAT_NAME, {
	title: __( 'Text Color', 'swishfolio-core' ),
	tagName: 'span',
	className: null,
	attributes: {
		style: 'style',
	},
	edit: TextColorButton,
} );

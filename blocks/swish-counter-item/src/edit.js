/**
 * Counter Item - Edit Component.
 *
 * Four inline-editable spans: prefix, value, suffix, title. No custom
 * inspector - the parent owns all visual styling.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function Edit( { attributes, setAttributes } ) {
	const { prefix, value, suffix, title } = attributes;

	const blockProps = useBlockProps( {
		className: 'sfcore-swish-counter-item',
	} );

	return (
		<figure { ...blockProps }>
			<div className="sfcore-swish-counter-item__counter">
				<RichText
					tagName="span"
					className="sfcore-swish-counter-item__prefix"
					value={ prefix }
					onChange={ ( v ) => setAttributes( { prefix: v } ) }
					placeholder={ __( '$', 'swishfolio-core' ) }
					allowedFormats={ [] }
				/>
				<RichText
					tagName="span"
					className="sfcore-swish-counter-item__value"
					value={ value }
					onChange={ ( v ) => setAttributes( { value: v } ) }
					placeholder={ __( '0', 'swishfolio-core' ) }
					allowedFormats={ [] }
				/>
				<RichText
					tagName="span"
					className="sfcore-swish-counter-item__suffix"
					value={ suffix }
					onChange={ ( v ) => setAttributes( { suffix: v } ) }
					placeholder={ __( '+', 'swishfolio-core' ) }
					allowedFormats={ [] }
				/>
			</div>

			<RichText
				tagName="p"
				className="sfcore-swish-counter-item__title"
				value={ title }
				onChange={ ( v ) => setAttributes( { title: v } ) }
				placeholder={ __( 'Title', 'swishfolio-core' ) }
				allowedFormats={ [] }
			/>
		</figure>
	);
}

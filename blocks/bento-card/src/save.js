/**
 * Bento Card - Save Component.
 *
 * Static save. All shared visuals (overlay, accent colour, grid layout) are
 * inherited via CSS custom properties on the parent wrapper.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { image, subtitle, title, description, titleColor, cardSize } =
		attributes;

	const classes = [
		'sfcore-bento__card',
		`sfcore-bento__card--${ cardSize || 'one-third' }`,
	];
	if ( image?.url ) {
		classes.push( 'sfcore-bento__card--has-image' );
	}

	const blockProps = useBlockProps.save( { className: classes.join( ' ' ) } );
	const titleStyle = titleColor ? { color: titleColor } : undefined;

	return (
		<div { ...blockProps }>
			{ image?.url && (
				<img
					src={ image.url }
					alt={ image.alt || '' }
					className="sfcore-bento__card-image"
				/>
			) }
			<div className="sfcore-bento__card-overlay" />
			<div className="sfcore-bento__card-content">
				{ subtitle && (
					<RichText.Content
						tagName="span"
						className="sfcore-bento__card-subtitle"
						value={ subtitle }
						style={ titleStyle }
					/>
				) }
				<div className="sfcore-bento__card-info">
					{ title && (
						<RichText.Content
							tagName="h3"
							className="sfcore-bento__card-title"
							value={ title }
							style={ titleStyle }
						/>
					) }
					{ description && (
						<RichText.Content
							tagName="p"
							className="sfcore-bento__card-description"
							value={ description }
							style={ titleStyle }
						/>
					) }
				</div>
			</div>
		</div>
	);
}

/**
 * Bento Card - Save Component.
 *
 * Static save. Emits a uniform DOM across all parent `cardStyle` values —
 * CSS on the parent wrapper drives the actual visual layout. The image layer
 * wrapper enables the parent's `layered` mode (image bottom-aligned on a
 * backdrop). The `InnerBlocks.Content` slot receives an optional core/buttons
 * block used by the `cta` style.
 */

import { useBlockProps, InnerBlocks, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		image,
		subtitle,
		title,
		description,
		titleColor,
		cardSize,
		layeredImageWidth,
	} = attributes;

	const classes = [
		'sfcore-bento__card',
		`sfcore-bento__card--${ cardSize || 'one-third' }`,
	];
	if ( image?.url ) {
		classes.push( 'sfcore-bento__card--has-image' );
	}

	const blockProps = useBlockProps.save( {
		className: classes.join( ' ' ),
		style: {
			'--layered-image-width': `${ layeredImageWidth || 100 }%`,
		},
	} );

	const titleStyle = titleColor ? { color: titleColor } : undefined;

	return (
		<div { ...blockProps }>
			<div className="sfcore-bento__card-image-layer">
				{ image?.url && (
					<img
						src={ image.url }
						alt={ image.alt || '' }
						className="sfcore-bento__card-image"
					/>
				) }
			</div>
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
					<div className="sfcore-bento__card-title-row">
						{ title && (
							<RichText.Content
								tagName="h3"
								className="sfcore-bento__card-title"
								value={ title }
								style={ titleStyle }
							/>
						) }
						<div className="sfcore-bento__card-actions">
							<InnerBlocks.Content />
						</div>
					</div>
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

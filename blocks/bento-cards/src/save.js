/**
 * Bento Cards Block - Save Component
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		cards,
		gridGap,
		cardOverlayColor,
		cardOverlayOpacity,
		accentColor,
		ctaType,
		ctaText,
		ctaUrl,
		ctaTextColor,
		ctaBgColor,
	} = attributes;

	// Get card classes
	const getCardClasses = ( card ) => {
		const classes = [
			'sfcore-bento__card',
			`sfcore-bento__card--${ card.cardSize || 'one-third' }`,
		];
		if ( card.image?.url ) {
			classes.push( 'sfcore-bento__card--has-image' );
		}
		return classes.join( ' ' );
	};

	const blockProps = useBlockProps.save( {
		className: 'sfcore-bento',
		style: {
			'--grid-gap': gridGap,
			'--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
			'--overlay-opacity': cardOverlayOpacity / 100,
			'--accent-color': accentColor || '#FFE500',
		},
	} );

	return (
		<div { ...blockProps }>
			{ /* Cards Grid */ }
			<div className="sfcore-bento__grid">
				{ cards.map( ( card ) => (
					<div
						key={ card.id }
						className={ getCardClasses( card ) }
					>
						{ card.image?.url && (
							<img
								src={ card.image.url }
								alt={ card.image.alt || '' }
								className="sfcore-bento__card-image"
							/>
						) }
						<div className="sfcore-bento__card-overlay" />
						<div className="sfcore-bento__card-content">
							{ card.subtitle && (
								<RichText.Content
									tagName="span"
									className="sfcore-bento__card-subtitle"
									value={ card.subtitle }
									style={ card.titleColor ? { color: card.titleColor } : undefined }
								/>
							) }
							<div className="sfcore-bento__card-info">
								{ card.title && (
									<RichText.Content
										tagName="h3"
										className="sfcore-bento__card-title"
										value={ card.title }
										style={ card.titleColor ? { color: card.titleColor } : undefined }
									/>
								) }
								{ card.description && (
									<RichText.Content
										tagName="p"
										className="sfcore-bento__card-description"
										value={ card.description }
										style={ card.titleColor ? { color: card.titleColor } : undefined }
									/>
								) }
							</div>
						</div>
					</div>
				) ) }
			</div>

			{ /* Block CTA */ }
			{ ctaType !== 'none' && ctaText && ctaUrl && (
				<div className="sfcore-bento__cta-wrapper">
					<a
						href={ ctaUrl }
						className={ `sfcore-bento__cta sfcore-bento__cta--${ ctaType }` }
						style={ {
							color: ctaTextColor || undefined,
							backgroundColor: ctaType === 'button' ? ( ctaBgColor || undefined ) : undefined,
						} }
					>
						{ ctaText }
					</a>
				</div>
			) }
		</div>
	);
}

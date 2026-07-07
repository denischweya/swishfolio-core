/**
 * Bento Cards - Deprecated Versions.
 *
 * v1 stored each card as an object in a `cards` array attribute. v2 splits
 * cards into inner blocks (swishfolio-core/bento-card). The migrate below
 * converts old post content into inner blocks on first edit.
 */

import { createBlock } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';

const v1Attributes = {
	cards: {
		type: 'array',
		default: [],
	},
	gridGap: { type: 'string', default: '1.5rem' },
	cardOverlayColor: { type: 'string', default: '' },
	cardOverlayOpacity: { type: 'number', default: 60 },
	accentColor: { type: 'string', default: '' },
	ctaType: { type: 'string', default: 'none' },
	ctaText: { type: 'string', default: 'View All' },
	ctaUrl: { type: 'string', default: '' },
	ctaTextColor: { type: 'string', default: '' },
	ctaBgColor: { type: 'string', default: '' },
};

const v1Supports = {
	align: [ 'wide', 'full' ],
	html: false,
	color: { background: true, text: true },
	spacing: { margin: true, padding: true },
	typography: { fontSize: true, lineHeight: true },
};

function v1Save( { attributes } ) {
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
			<div className="sfcore-bento__grid">
				{ cards.map( ( card ) => (
					<div key={ card.id } className={ getCardClasses( card ) }>
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

			{ ctaType !== 'none' && ctaText && ctaUrl && (
				<div className="sfcore-bento__cta-wrapper">
					<a
						href={ ctaUrl }
						className={ `sfcore-bento__cta sfcore-bento__cta--${ ctaType }` }
						style={ {
							color: ctaTextColor || undefined,
							backgroundColor:
								ctaType === 'button' ? ctaBgColor || undefined : undefined,
						} }
					>
						{ ctaText }
					</a>
				</div>
			) }
		</div>
	);
}

const v1 = {
	attributes: v1Attributes,
	supports: v1Supports,
	save: v1Save,
	isEligible: ( attributes ) =>
		Array.isArray( attributes.cards ) && attributes.cards.length > 0,
	migrate: ( attributes ) => {
		const { cards = [], ...rest } = attributes;

		const innerBlocks = cards.map( ( card ) => {
			const cardAttrs = {
				subtitle: card.subtitle || '',
				title: card.title || '',
				description: card.description || '',
				titleColor: card.titleColor || '',
				cardSize: card.cardSize || 'one-third',
			};
			if ( card.image && card.image.url ) {
				cardAttrs.image = card.image;
			}
			return createBlock( 'swishfolio-core/bento-card', cardAttrs );
		} );

		return [ rest, innerBlocks ];
	},
};

export default [ v1 ];

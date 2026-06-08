/**
 * Features Block - Deprecations.
 *
 * v1: monolithic block with a `featureItems` array attribute. Each item was
 * rendered inside the same block by `save.js`. Migrating to v2 splits each
 * item into a child swishfolio-core/feature-card block via InnerBlocks.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import {
	starFilled,
	starEmpty,
	check,
	code,
	desktop,
	mobile,
	globe,
	lock,
	key,
	people,
	store,
	tag,
	category,
	grid,
	list,
	settings,
	tool,
	brush,
	image,
	video,
	download,
	upload,
	external,
	info,
	warning,
	inbox,
	envelope,
	shield,
	gift,
	bell,
	calendar,
	cog,
	pencil,
	plugins,
	arrowRight,
} from '@wordpress/icons';

const ICON_LIBRARY = {
	star: starFilled,
	'star-empty': starEmpty,
	check,
	code,
	desktop,
	mobile,
	globe,
	lock,
	key,
	people,
	store,
	tag,
	category,
	grid,
	list,
	settings,
	tool,
	brush,
	image,
	video,
	download,
	upload,
	external,
	info,
	warning,
	inbox,
	envelope,
	shield,
	gift,
	bell,
	calendar,
	cog,
	pencil,
	plugins,
};

// --- v1 attributes (pre-InnerBlocks) ----------------------------------------

const v1Attributes = {
	featureItems: {
		type: 'array',
		default: [
			{
				id: 'feature-1',
				subheading: 'MODULE 01',
				title: 'Feature Title',
				titleTag: 'h3',
				description: 'Feature description goes here...',
				icon: { type: 'library', value: 'star' },
				ctaType: 'link',
				ctaText: 'Learn More',
				ctaUrl: '',
				cardSize: 'medium',
				cardStyle: 'default',
				backgroundImage: {},
				backgroundImagePosition: 'center center',
				overlayColor: '',
				overlayOpacity: 50,
				isHighlighted: false,
				titleFontSize: '',
				subheadingFontSize: '',
				titleColor: '',
				iconColor: '',
				iconBackgroundColor: '',
				ctaTextColor: '',
				ctaBackgroundColor: '',
				cardBackgroundColor: '',
				cardAccentColor: '',
			},
		],
	},
	gridGap: { type: 'string', default: '2rem' },
	cardBorderStyle: { type: 'string', default: 'solid' },
	cardBorderColor: { type: 'string', default: '' },
	cardShadowColor: { type: 'string', default: '' },
	titleFontSize: { type: 'string', default: '' },
	subheadingFontSize: { type: 'string', default: '' },
	titleColor: { type: 'string', default: '' },
	iconColor: { type: 'string', default: '' },
	iconBackgroundColor: { type: 'string', default: '' },
	ctaTextColor: { type: 'string', default: '' },
	ctaBackgroundColor: { type: 'string', default: '' },
	cardBackgroundColor: { type: 'string', default: '' },
	cardAccentColor: { type: 'string', default: '' },
};

const v1Supports = {
	align: [ 'wide', 'full' ],
	html: false,
	color: { background: true, text: true },
	spacing: { margin: true, padding: true },
	typography: { fontSize: true, lineHeight: true },
};

// --- v1 save (verbatim from pre-refactor save.js) ---------------------------

function v1Save( { attributes } ) {
	const {
		featureItems,
		gridGap,
		cardBorderStyle,
		cardBorderColor,
		cardShadowColor,
		titleFontSize,
		subheadingFontSize,
		titleColor,
		iconColor,
		iconBackgroundColor,
		ctaTextColor,
		ctaBackgroundColor,
		cardBackgroundColor,
		cardAccentColor,
	} = attributes;

	const resolveCardStyle = ( item ) => {
		const pick = ( key, shared ) =>
			item.isHighlighted && item[ key ] ? item[ key ] : shared;
		return {
			titleFontSize: pick( 'titleFontSize', titleFontSize ),
			subheadingFontSize: pick( 'subheadingFontSize', subheadingFontSize ),
			titleColor: pick( 'titleColor', titleColor ),
			iconColor: pick( 'iconColor', iconColor ),
			iconBackgroundColor: pick(
				'iconBackgroundColor',
				iconBackgroundColor
			),
			ctaTextColor: pick( 'ctaTextColor', ctaTextColor ),
			ctaBackgroundColor: pick(
				'ctaBackgroundColor',
				ctaBackgroundColor
			),
			cardBackgroundColor: pick(
				'cardBackgroundColor',
				cardBackgroundColor
			),
			cardAccentColor: pick( 'cardAccentColor', cardAccentColor ),
		};
	};

	const renderIcon = ( item, cs ) => {
		if ( ! item.icon ) {
			return null;
		}
		if ( item.icon.type === 'library' && ICON_LIBRARY[ item.icon.value ] ) {
			return (
				<span
					className="sfcore-features__card-icon"
					style={ {
						color: cs.iconColor || undefined,
						backgroundColor: cs.iconBackgroundColor || undefined,
					} }
				>
					{ ICON_LIBRARY[ item.icon.value ] }
				</span>
			);
		}
		if ( item.icon.type === 'custom' && item.icon.value ) {
			return (
				<span
					className="sfcore-features__card-icon sfcore-features__card-icon--custom"
					style={ {
						backgroundColor: cs.iconBackgroundColor || undefined,
					} }
				>
					<img src={ item.icon.value } alt="" />
				</span>
			);
		}
		return null;
	};

	const getCardClasses = ( item ) => {
		const classes = [
			'sfcore-features__card',
			`sfcore-features__card--${ item.cardSize || 'medium' }`,
			`sfcore-features__card--${ item.cardStyle || 'default' }`,
		];
		if ( item.backgroundImage?.url ) {
			classes.push( 'sfcore-features__card--has-bg-image' );
		}
		if ( item.backgroundImage?.url && item.overlayColor ) {
			classes.push( 'sfcore-features__card--has-overlay' );
		}
		return classes.join( ' ' );
	};

	const getCardStyles = ( cs ) => {
		const styles = {};
		if ( cs.cardBackgroundColor ) {
			styles[ '--card-bg' ] = cs.cardBackgroundColor;
		}
		if ( cs.cardAccentColor ) {
			styles[ '--card-accent' ] = cs.cardAccentColor;
		}
		return styles;
	};

	const blockProps = useBlockProps.save( {
		className: `sfcore-features sfcore-features--border-${ cardBorderStyle }`,
		style: {
			'--grid-gap': gridGap,
			'--card-border-color': cardBorderColor || undefined,
			'--card-shadow-color': cardShadowColor || undefined,
		},
	} );

	return (
		<div { ...blockProps }>
			<div className="sfcore-features__grid">
				{ featureItems.map( ( item ) => {
					const cs = resolveCardStyle( item );
					return (
						<div
							key={ item.id }
							className={ getCardClasses( item ) }
							style={ getCardStyles( cs ) }
						>
							{ item.backgroundImage?.url && (
								<img
									src={ item.backgroundImage.url }
									alt={ item.backgroundImage.alt || '' }
									className="sfcore-features__card-bg-image"
									style={ {
										objectPosition:
											item.backgroundImagePosition || 'center center',
									} }
								/>
							) }
							{ item.backgroundImage?.url && item.overlayColor && (
								<div
									className="sfcore-features__card-overlay"
									style={ {
										backgroundColor: item.overlayColor,
										opacity: ( item.overlayOpacity || 50 ) / 100,
									} }
								/>
							) }
							<div className="sfcore-features__card-inner">
								{ item.subheading && (
									<div className="sfcore-features__card-header">
										<span
											className="sfcore-features__card-subheading"
											style={ {
												fontSize: cs.subheadingFontSize || undefined,
											} }
										>
											<RichText.Content value={ item.subheading } />
										</span>
									</div>
								) }

								{ item.title && (
									<RichText.Content
										tagName={ item.titleTag || 'h3' }
										className="sfcore-features__card-title"
										value={ item.title }
										style={ {
											fontSize: cs.titleFontSize || undefined,
											color: cs.titleColor || undefined,
										} }
									/>
								) }

								{ item.description && (
									<RichText.Content
										tagName="p"
										className="sfcore-features__card-description"
										value={ item.description }
										style={ {
											color: cs.titleColor || undefined,
										} }
									/>
								) }

								{ item.icon &&
									item.icon.type !== 'none' &&
									renderIcon( item, cs ) }

								{ item.ctaType &&
									item.ctaType !== 'none' &&
									item.ctaText && (
										<div className="sfcore-features__card-cta-wrapper">
											{ item.ctaType === 'link' ? (
												<a
													href={ item.ctaUrl || '#' }
													className="sfcore-features__card-link"
													style={ {
														color: cs.ctaTextColor || undefined,
														backgroundColor:
															cs.ctaBackgroundColor || undefined,
													} }
												>
													<RichText.Content value={ item.ctaText } />
													{ arrowRight }
												</a>
											) : (
												<a
													href={ item.ctaUrl || '#' }
													className="sfcore-features__card-button"
													style={ {
														color: cs.ctaTextColor || undefined,
														backgroundColor:
															cs.ctaBackgroundColor || undefined,
													} }
												>
													<RichText.Content value={ item.ctaText } />
												</a>
											) }
										</div>
									) }
							</div>
						</div>
					);
				} ) }
			</div>
		</div>
	);
}

// --- Migration: featureItems[] → child feature-card blocks ------------------

function v1Migrate( attributes ) {
	const { featureItems = [], ...rest } = attributes;

	const innerBlocks = featureItems.map( ( item ) =>
		createBlock( 'swishfolio-core/feature-card', {
			subheading: item.subheading || '',
			title: item.title || '',
			titleTag: item.titleTag || 'h3',
			description: item.description || '',
			icon: item.icon || { type: 'library', value: 'star' },
			ctaType: item.ctaType || 'link',
			ctaText: item.ctaText || '',
			ctaUrl: item.ctaUrl || '',
			cardSize: item.cardSize || 'medium',
			cardStyle: item.cardStyle || 'default',
			backgroundImage: item.backgroundImage || {},
			backgroundImagePosition:
				item.backgroundImagePosition || 'center center',
			overlayColor: item.overlayColor || '',
			overlayOpacity:
				item.overlayOpacity !== undefined ? item.overlayOpacity : 50,
			// Per-card overrides only carry forward when the card was
			// previously highlighted; otherwise leave empty so the parent's
			// globals (in `rest`) apply.
			titleFontSize: item.isHighlighted ? item.titleFontSize || '' : '',
			subheadingFontSize: item.isHighlighted
				? item.subheadingFontSize || ''
				: '',
			titleColor: item.isHighlighted ? item.titleColor || '' : '',
			iconColor: item.isHighlighted ? item.iconColor || '' : '',
			iconBackgroundColor: item.isHighlighted
				? item.iconBackgroundColor || ''
				: '',
			ctaTextColor: item.isHighlighted ? item.ctaTextColor || '' : '',
			ctaBackgroundColor: item.isHighlighted
				? item.ctaBackgroundColor || ''
				: '',
			cardBackgroundColor: item.isHighlighted
				? item.cardBackgroundColor || ''
				: '',
			cardAccentColor: item.isHighlighted
				? item.cardAccentColor || ''
				: '',
		} )
	);

	return [ rest, innerBlocks ];
}

const v1 = {
	attributes: v1Attributes,
	supports: v1Supports,
	save: v1Save,
	migrate: v1Migrate,
};

export default [ v1 ];

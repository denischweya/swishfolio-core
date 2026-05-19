/**
 * Features Block - Save Component
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';
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

// Icon library mapping (must match edit.js)
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

export default function save( { attributes } ) {
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

	// Render icon
	const renderIcon = ( item ) => {
		if ( ! item.icon ) {
			return null;
		}
		if ( item.icon.type === 'library' && ICON_LIBRARY[ item.icon.value ] ) {
			return (
				<span
					className="sfcore-features__card-icon"
					style={ {
						color: iconColor || undefined,
						backgroundColor: iconBackgroundColor || undefined,
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
						backgroundColor: iconBackgroundColor || undefined,
					} }
				>
					<img src={ item.icon.value } alt="" />
				</span>
			);
		}
		return null;
	};

	// Get card classes
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

	// Get card styles (shared across all cards)
	const getCardStyles = () => {
		const styles = {};
		if ( cardBackgroundColor ) {
			styles[ '--card-bg' ] = cardBackgroundColor;
		}
		if ( cardAccentColor ) {
			styles[ '--card-accent' ] = cardAccentColor;
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
				{ featureItems.map( ( item ) => (
					<div
						key={ item.id }
						className={ getCardClasses( item ) }
						style={ getCardStyles() }
					>
						{ item.backgroundImage?.url && (
							<img
								src={ item.backgroundImage.url }
								alt={ item.backgroundImage.alt || '' }
								className="sfcore-features__card-bg-image"
								style={ {
									objectPosition: item.backgroundImagePosition || 'center center',
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
											fontSize: subheadingFontSize || undefined,
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
										fontSize: titleFontSize || undefined,
										color: titleColor || undefined,
									} }
								/>
							) }

							{ item.description && (
								<RichText.Content
									tagName="p"
									className="sfcore-features__card-description"
									value={ item.description }
									style={ {
										color: titleColor || undefined,
									} }
								/>
							) }

							{ item.icon && item.icon.type !== 'none' && renderIcon( item ) }

							{ item.ctaType && item.ctaType !== 'none' && item.ctaText && (
								<div className="sfcore-features__card-cta-wrapper">
									{ item.ctaType === 'link' ? (
										<a
											href={ item.ctaUrl || '#' }
											className="sfcore-features__card-link"
											style={ {
												color: ctaTextColor || undefined,
												backgroundColor: ctaBackgroundColor || undefined,
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
												color: ctaTextColor || undefined,
												backgroundColor: ctaBackgroundColor || undefined,
											} }
										>
											<RichText.Content value={ item.ctaText } />
										</a>
									) }
								</div>
							) }
						</div>
					</div>
				) ) }
			</div>
		</div>
	);
}

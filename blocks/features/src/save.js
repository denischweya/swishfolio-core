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
	tablet,
	globe,
	lock,
	key,
	people,
	postComments,
	payment,
	shipping,
	store,
	tag,
	category,
	grid,
	list,
	settings,
	tool,
	brush,
	color as colorIcon,
	image,
	video,
	audio,
	file,
	download,
	upload,
	external,
	link,
	info,
	warning,
	help,
	inbox,
	envelope,
	shield,
	gift,
	bell,
	calendar,
	cog,
	pencil,
	plugins,
	search,
	cloud,
	chartBar,
	arrowRight,
} from '@wordpress/icons';

// Icon library mapping (must match edit.js)
const ICON_LIBRARY = {
	star: starFilled,
	'star-empty': starEmpty,
	check: check,
	code: code,
	desktop: desktop,
	mobile: mobile,
	tablet: tablet,
	globe: globe,
	lock: lock,
	key: key,
	people: people,
	comments: postComments,
	payment: payment,
	shipping: shipping,
	store: store,
	tag: tag,
	category: category,
	grid: grid,
	list: list,
	settings: settings,
	tool: tool,
	brush: brush,
	color: colorIcon,
	image: image,
	video: video,
	audio: audio,
	file: file,
	download: download,
	upload: upload,
	external: external,
	link: link,
	info: info,
	warning: warning,
	help: help,
	inbox: inbox,
	envelope: envelope,
	shield: shield,
	gift: gift,
	bell: bell,
	calendar: calendar,
	cog: cog,
	pencil: pencil,
	plugins: plugins,
	search: search,
	cloud: cloud,
	chart: chartBar,
};

export default function save( { attributes } ) {
	const {
		sectionTitle,
		sectionTitleTag,
		sectionTitleColor,
		sectionDescription,
		showSectionCta,
		primaryCtaText,
		primaryCtaUrl,
		primaryCtaStyle,
		secondaryCtaText,
		secondaryCtaUrl,
		secondaryCtaStyle,
		featureItems,
		gridGap,
		cardBorderStyle,
		cardBorderColor,
		cardShadowColor,
	} = attributes;

	// Render icon
	const renderIcon = ( item ) => {
		if ( item.icon.type === 'library' && ICON_LIBRARY[ item.icon.value ] ) {
			return (
				<span
					className="sfcore-features__card-icon"
					style={ {
						color: item.iconColor || undefined,
						backgroundColor: item.iconBackgroundColor || undefined,
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
						backgroundColor: item.iconBackgroundColor || undefined,
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
			`sfcore-features__card--${ item.cardSize }`,
			`sfcore-features__card--${ item.cardStyle }`,
		];
		return classes.join( ' ' );
	};

	// Get card styles
	const getCardStyles = ( item ) => {
		const styles = {};
		if ( item.cardBackgroundColor ) {
			styles[ '--card-bg' ] = item.cardBackgroundColor;
		}
		if ( item.cardAccentColor ) {
			styles[ '--card-accent' ] = item.cardAccentColor;
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

	const SectionTitleTag = sectionTitleTag;

	return (
		<div { ...blockProps }>
			{ /* Section Header */ }
			{ ( sectionTitle || sectionDescription || showSectionCta ) && (
				<div className="sfcore-features__header">
					{ sectionTitle && (
						<RichText.Content
							tagName={ sectionTitleTag }
							className="sfcore-features__title"
							value={ sectionTitle }
							style={ { color: sectionTitleColor || undefined } }
						/>
					) }
					{ sectionDescription && (
						<RichText.Content
							tagName="p"
							className="sfcore-features__description"
							value={ sectionDescription }
						/>
					) }

					{ showSectionCta && ( primaryCtaText || secondaryCtaText ) && (
						<div className="sfcore-features__header-cta">
							{ primaryCtaText && primaryCtaUrl && (
								<a
									href={ primaryCtaUrl }
									className={ `sfcore-features__cta sfcore-features__cta--${ primaryCtaStyle }` }
								>
									<RichText.Content value={ primaryCtaText } />
								</a>
							) }
							{ secondaryCtaText && secondaryCtaUrl && (
								<a
									href={ secondaryCtaUrl }
									className={ `sfcore-features__cta sfcore-features__cta--${ secondaryCtaStyle }` }
								>
									<RichText.Content value={ secondaryCtaText } />
								</a>
							) }
						</div>
					) }
				</div>
			) }

			{ /* Feature Cards Grid */ }
			<div className="sfcore-features__grid">
				{ featureItems.map( ( item ) => (
					<div
						key={ item.id }
						className={ getCardClasses( item ) }
						style={ getCardStyles( item ) }
					>
						<div className="sfcore-features__card-inner">
							{ item.subheading && (
								<div className="sfcore-features__card-header">
									<RichText.Content
										tagName="span"
										className="sfcore-features__card-subheading"
										value={ item.subheading }
									/>
								</div>
							) }

							{ item.title && (
								<RichText.Content
									tagName={ item.titleTag }
									className="sfcore-features__card-title"
									value={ item.title }
								/>
							) }

							{ item.description && (
								<RichText.Content
									tagName="p"
									className="sfcore-features__card-description"
									value={ item.description }
								/>
							) }

							{ item.icon.type !== 'none' && renderIcon( item ) }

							{ item.ctaType !== 'none' && item.ctaText && (
								<div className="sfcore-features__card-cta-wrapper">
									{ item.ctaType === 'link' ? (
										<a
											href={ item.ctaUrl || '#' }
											className="sfcore-features__card-link"
										>
											<RichText.Content value={ item.ctaText } />
											{ arrowRight }
										</a>
									) : (
										<a
											href={ item.ctaUrl || '#' }
											className="sfcore-features__card-button"
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

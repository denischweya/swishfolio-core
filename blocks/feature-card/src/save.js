/**
 * Feature Card Block - Save Component.
 *
 * Per-card style attributes, when set, override the parent block's globals.
 * When empty, inline style falls back to the parent's --features-* CSS vars
 * so the global wins.
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

const inherit = ( override, varName ) =>
	override || `var(${ varName })`;

export default function save( { attributes } ) {
	const {
		subheading,
		title,
		titleTag,
		description,
		icon,
		ctaType,
		ctaText,
		ctaUrl,
		cardSize,
		cardStyle,
		backgroundImage,
		backgroundImagePosition,
		overlayColor,
		overlayOpacity,
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

	const classes = [
		'sfcore-features__card',
		`sfcore-features__card--${ cardSize || 'medium' }`,
		`sfcore-features__card--${ cardStyle || 'default' }`,
	];
	if ( backgroundImage?.url ) {
		classes.push( 'sfcore-features__card--has-bg-image' );
	}
	if ( backgroundImage?.url && overlayColor ) {
		classes.push( 'sfcore-features__card--has-overlay' );
	}

	const blockProps = useBlockProps.save( {
		className: classes.join( ' ' ),
		style: {
			'--card-bg': inherit( cardBackgroundColor, '--features-card-bg' ),
			'--card-accent': inherit( cardAccentColor, '--features-card-accent' ),
		},
	} );

	const renderIcon = () => {
		if ( ! icon || icon.type === 'none' ) {
			return null;
		}
		const iconStyle = {
			color: inherit( iconColor, '--features-icon-color' ),
			backgroundColor: inherit( iconBackgroundColor, '--features-icon-bg' ),
		};
		if ( icon.type === 'library' && ICON_LIBRARY[ icon.value ] ) {
			return (
				<span className="sfcore-features__card-icon" style={ iconStyle }>
					{ ICON_LIBRARY[ icon.value ] }
				</span>
			);
		}
		if ( icon.type === 'custom' && icon.value ) {
			return (
				<span
					className="sfcore-features__card-icon sfcore-features__card-icon--custom"
					style={ {
						backgroundColor: inherit(
							iconBackgroundColor,
							'--features-icon-bg'
						),
					} }
				>
					<img src={ icon.value } alt="" />
				</span>
			);
		}
		return null;
	};

	return (
		<div { ...blockProps }>
			{ backgroundImage?.url && (
				<img
					src={ backgroundImage.url }
					alt={ backgroundImage.alt || '' }
					className="sfcore-features__card-bg-image"
					style={ {
						objectPosition: backgroundImagePosition || 'center center',
					} }
				/>
			) }
			{ backgroundImage?.url && overlayColor && (
				<div
					className="sfcore-features__card-overlay"
					style={ {
						backgroundColor: overlayColor,
						opacity: ( overlayOpacity || 50 ) / 100,
					} }
				/>
			) }
			<div className="sfcore-features__card-inner">
				{ subheading && (
					<div className="sfcore-features__card-header">
						<span
							className="sfcore-features__card-subheading"
							style={ {
								fontSize: inherit(
									subheadingFontSize,
									'--features-subheading-font-size'
								),
							} }
						>
							<RichText.Content value={ subheading } />
						</span>
					</div>
				) }

				{ title && (
					<RichText.Content
						tagName={ titleTag || 'h3' }
						className="sfcore-features__card-title"
						value={ title }
						style={ {
							fontSize: inherit(
								titleFontSize,
								'--features-title-font-size'
							),
							color: inherit( titleColor, '--features-title-color' ),
						} }
					/>
				) }

				{ description && (
					<RichText.Content
						tagName="p"
						className="sfcore-features__card-description"
						value={ description }
						style={ {
							color: inherit( titleColor, '--features-title-color' ),
						} }
					/>
				) }

				{ renderIcon() }

				{ ctaType && ctaType !== 'none' && ctaText && (
					<div className="sfcore-features__card-cta-wrapper">
						{ ctaType === 'link' ? (
							<a
								href={ ctaUrl || '#' }
								className="sfcore-features__card-link"
								style={ {
									color: inherit(
										ctaTextColor,
										'--features-cta-text-color'
									),
									backgroundColor: inherit(
										ctaBackgroundColor,
										'--features-cta-bg'
									),
								} }
							>
								<RichText.Content value={ ctaText } />
								{ arrowRight }
							</a>
						) : (
							<a
								href={ ctaUrl || '#' }
								className="sfcore-features__card-button"
								style={ {
									color: inherit(
										ctaTextColor,
										'--features-cta-text-color'
									),
									backgroundColor: inherit(
										ctaBackgroundColor,
										'--features-cta-bg'
									),
								} }
							>
								<RichText.Content value={ ctaText } />
							</a>
						) }
					</div>
				) }
			</div>
		</div>
	);
}

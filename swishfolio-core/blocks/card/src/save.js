/**
 * Card Block - Save Component
 *
 * For the save function, we output a placeholder SVG icon that gets replaced
 * by the frontend script, or we save null for library icons and render them
 * via PHP if needed.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		icon,
		iconPosition,
		iconSize,
		iconColor,
		iconBackgroundColor,
		iconShape,
		cardBorderStyle,
		cardBorderColor,
		cardShadowColor,
		title,
		description,
		linkUrl,
		linkText,
		linkStyle,
		decorativeShape,
		decorativeColor,
	} = attributes;

	const cardStyles = {
		'--card-border-color': cardBorderColor || '#121212',
		'--card-shadow-color': cardShadowColor || '#121212',
	};

	const blockProps = useBlockProps.save( {
		className: `sfcore-card sfcore-card--icon-${ iconPosition } sfcore-card--icon-${ iconSize } sfcore-card--icon-shape-${ iconShape || 'square' } sfcore-card--border-${ cardBorderStyle || 'solid' }`,
		style: cardStyles,
	} );

	// Render icon - save the icon name as a data attribute for potential PHP rendering
	const renderIcon = () => {
		const iconStyles = {
			color: iconColor || undefined,
			backgroundColor: iconBackgroundColor || '#F0C020',
		};

		if ( icon.type === 'library' && icon.value ) {
			return (
				<span
					className="sfcore-card__icon"
					style={ iconStyles }
					data-icon={ icon.value }
				>
					<svg
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 24 24"
						width="24"
						height="24"
						aria-hidden="true"
						focusable="false"
					>
						<path d="M11.776 4.454a.25.25 0 01.448 0l2.069 4.192a.25.25 0 00.188.137l4.626.672a.25.25 0 01.139.426l-3.348 3.263a.25.25 0 00-.072.222l.79 4.607a.25.25 0 01-.362.263l-4.138-2.175a.25.25 0 00-.232 0l-4.138 2.175a.25.25 0 01-.363-.263l.79-4.607a.25.25 0 00-.071-.222L4.754 9.881a.25.25 0 01.139-.426l4.626-.672a.25.25 0 00.188-.137l2.069-4.192z" />
					</svg>
				</span>
			);
		}
		if ( icon.type === 'custom' && icon.value ) {
			return (
				<span
					className="sfcore-card__icon sfcore-card__icon--custom"
					style={ iconStyles }
				>
					<img src={ icon.value } alt="" />
				</span>
			);
		}
		return null;
	};

	// Render decorative shape
	const renderDecorativeShape = () => {
		if ( decorativeShape === 'none' ) {
			return null;
		}
		const shapeColor = decorativeColor || '#F0C020';
		return (
			<div
				className={ `sfcore-card__shape sfcore-card__shape--${ decorativeShape }` }
				style={ { backgroundColor: shapeColor } }
				aria-hidden="true"
			/>
		);
	};

	// Render link
	const renderLink = () => {
		if ( ! linkText ) {
			return null;
		}

		const linkClassName = `sfcore-card__link sfcore-card__link--${ linkStyle }`;

		if ( linkUrl ) {
			return (
				<div className="sfcore-card__link-wrapper">
					<a href={ linkUrl } className={ linkClassName }>
						<RichText.Content value={ linkText } />
					</a>
				</div>
			);
		}

		return (
			<div className="sfcore-card__link-wrapper">
				<span className={ linkClassName }>
					<RichText.Content value={ linkText } />
				</span>
			</div>
		);
	};

	return (
		<div { ...blockProps }>
			{ renderDecorativeShape() }
			<div className="sfcore-card__inner">
				{ renderIcon() }
				<div className="sfcore-card__content">
					{ title && (
						<RichText.Content
							tagName="h3"
							className="sfcore-card__title"
							value={ title }
						/>
					) }
					{ description && (
						<RichText.Content
							tagName="p"
							className="sfcore-card__description"
							value={ description }
						/>
					) }
					{ renderLink() }
				</div>
			</div>
		</div>
	);
}

/**
 * Swish Socials Block - Save
 */

import { useBlockProps } from '@wordpress/block-editor';
import { SOCIAL_ICONS } from '../../shared/social-icons';

export default function save( { attributes } ) {
	const {
		socialLinks,
		iconShape,
		iconSize,
		borderWidth,
		borderColor,
		iconColor,
		backgroundColor,
		shadowSize,
		hoverEffect,
		useBrandColors,
		alignment,
		gapSize,
	} = attributes;

	if ( ! socialLinks || socialLinks.length === 0 ) {
		return null;
	}

	const blockProps = useBlockProps.save( {
		className: `sfcore-socials sfcore-socials--align-${ alignment } sfcore-socials--gap-${ gapSize }`,
	} );

	return (
		<div { ...blockProps }>
			{ socialLinks.map( ( link, index ) => {
				const iconData = SOCIAL_ICONS[ link.icon ];
				const style = {};

				if ( useBrandColors && link.icon !== 'custom' ) {
					style.backgroundColor = iconData?.color || '#121212';
					style.color = '#FFFFFF';
				} else {
					style.color = iconColor;
					style.backgroundColor = backgroundColor;
				}
				style.borderWidth = `${ borderWidth }px`;
				style.borderColor = borderColor;
				style.borderStyle = 'solid';

				const ariaLabel = link.label || iconData?.label || 'Social link';
				const isExternal = link.isExternal !== false;

				const className = `sfcore-socials__link sfcore-socials__link--${ iconShape } sfcore-socials__link--${ iconSize } sfcore-socials__link--shadow-${ shadowSize } sfcore-socials__link--hover-${ hoverEffect }`;

				return (
					<a
						key={ index }
						href={ link.url }
						className={ className }
						style={ style }
						{ ...( isExternal
							? { target: '_blank', rel: 'noopener noreferrer' }
							: {} ) }
						aria-label={ ariaLabel }
					>
						{ link.icon === 'custom' && link.customIcon?.url ? (
							<img
								src={ link.customIcon.url }
								alt=""
								className="sfcore-socials__icon-img"
							/>
						) : (
							<span
								className="sfcore-socials__icon"
								dangerouslySetInnerHTML={ {
									__html: iconData?.svg || '',
								} }
							/>
						) }
					</a>
				);
			} ) }
		</div>
	);
}

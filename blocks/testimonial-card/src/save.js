/**
 * Testimonial Card Block - Save Component.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		quote,
		name,
		role,
		roleUrl,
		avatarImageUrl,
		avatarColor,
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: 'sfcore-testimonial-card',
		style: avatarColor ? { '--sf-avatar-color': avatarColor } : undefined,
	} );

	const initial = ( name || '' )
		.replace( /<[^>]+>/g, '' )
		.trim()
		.charAt( 0 )
		.toUpperCase();

	const avatarStyle = avatarColor
		? { backgroundColor: avatarColor }
		: undefined;

	const RoleTag = roleUrl ? 'a' : 'div';
	const roleProps = roleUrl
		? { href: roleUrl, className: 'sfcore-testimonial-card__role' }
		: { className: 'sfcore-testimonial-card__role' };

	return (
		<figure { ...blockProps }>
			<div
				className="sfcore-testimonial-card__quote-mark"
				aria-hidden="true"
			>
				&ldquo;
			</div>

			<RichText.Content
				tagName="p"
				className="sfcore-testimonial-card__quote"
				value={ quote }
			/>

			<figcaption className="sfcore-testimonial-card__attribution">
				<div
					className="sfcore-testimonial-card__avatar"
					style={ avatarStyle }
				>
					{ avatarImageUrl ? (
						<img src={ avatarImageUrl } alt="" />
					) : (
						<span>{ initial }</span>
					) }
				</div>

				<div className="sfcore-testimonial-card__meta">
					<RichText.Content
						tagName="div"
						className="sfcore-testimonial-card__name"
						value={ name }
					/>
					<RichText.Content
						tagName={ RoleTag }
						value={ role }
						{ ...roleProps }
					/>
				</div>
			</figcaption>
		</figure>
	);
}

import { useBlockProps, RichText } from '@wordpress/block-editor';
import { ICONS } from './icons';

export default function save( { attributes } ) {
	const {
		links = [],
		direction = 'horizontal',
		gap = 28,
		hoverColor = '',
		hoverStyle = 'underline-grow',
	} = attributes;

	const wrapperStyle = {
		flexDirection: direction === 'vertical' ? 'column' : 'row',
		gap: `${ gap }px`,
	};
	if ( hoverColor ) {
		wrapperStyle[ '--swish-links-hover-color' ] = hoverColor;
	}

	const blockProps = useBlockProps.save( {
		className: `wp-block-swishfolio-core-swish-links sfcore-links sfcore-links--${ direction } sfcore-links--hover-${ hoverStyle }`,
		style: wrapperStyle,
	} );

	return (
		<div { ...blockProps }>
			{ links.map( ( link, index ) => {
				const iconEl = link.icon ? (
					<span
						className={ `sfcore-link__icon sfcore-link__icon--${ link.iconPosition }` }
						aria-hidden="true"
					>
						{ ICONS[ link.icon ] }
					</span>
				) : null;

				return (
					<a
						key={ index }
						className={ `sfcore-link${
							link.icon ? ' sfcore-link--with-icon' : ''
						}` }
						href={ link.url || '#' }
						target={ link.newTab ? '_blank' : undefined }
						rel={ link.newTab ? 'noopener noreferrer' : undefined }
					>
						{ link.iconPosition === 'before' && iconEl }
						<span className="sfcore-link__text">
							<RichText.Content value={ link.text } />
						</span>
						{ link.handle && (
							<span className="sfcore-link__handle">
								<RichText.Content value={ link.handle } />
							</span>
						) }
						{ link.iconPosition === 'after' && iconEl }
					</a>
				);
			} ) }
		</div>
	);
}

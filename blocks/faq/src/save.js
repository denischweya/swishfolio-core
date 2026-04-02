/**
 * FAQ Block - Save Component
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		allowMultipleOpen,
		defaultOpenFirst,
		iconStyle,
		iconPosition,
		items,
		questionColor,
		headerBackgroundColor,
		contentBackgroundColor,
		answerColor,
	} = attributes;

	if ( items.length === 0 ) {
		return null;
	}

	const blockProps = useBlockProps.save( {
		className: `sfcore-faq sfcore-faq--icon-${ iconPosition }`,
		'data-allow-multiple': allowMultipleOpen ? 'true' : 'false',
		'data-default-open-first': defaultOpenFirst ? 'true' : 'false',
	} );

	const renderIcon = () => {
		if ( iconStyle === 'plus' ) {
			return <span className="sfcore-faq__icon" data-icon="plus">+</span>;
		}
		if ( iconStyle === 'chevron' ) {
			return <span className="sfcore-faq__icon sfcore-faq__icon--chevron">▼</span>;
		}
		if ( iconStyle === 'arrow' ) {
			return <span className="sfcore-faq__icon sfcore-faq__icon--arrow">→</span>;
		}
		return null;
	};

	return (
		<div { ...blockProps }>
			{ items.map( ( item, index ) => {
				const isDefaultOpen = defaultOpenFirst && index === 0;
				return (
					<div
						key={ index }
						className={ `sfcore-faq__item ${ isDefaultOpen ? 'is-open' : '' }` }
					>
						<button
							type="button"
							className="sfcore-faq__header"
							aria-expanded={ isDefaultOpen ? 'true' : 'false' }
							aria-controls={ `faq-content-${ index }` }
							style={ headerBackgroundColor ? { backgroundColor: headerBackgroundColor } : undefined }
						>
							{ iconPosition === 'left' && renderIcon() }
							<span
								className="sfcore-faq__question"
								style={ questionColor ? { color: questionColor } : undefined }
							>
								<RichText.Content value={ item.question } />
							</span>
							{ iconPosition === 'right' && renderIcon() }
						</button>
						<div
							id={ `faq-content-${ index }` }
							className="sfcore-faq__content"
							aria-hidden={ isDefaultOpen ? 'false' : 'true' }
							style={ contentBackgroundColor ? { backgroundColor: contentBackgroundColor } : undefined }
						>
							<div
								className="sfcore-faq__answer"
								style={ answerColor ? { color: answerColor } : undefined }
							>
								<RichText.Content value={ item.answer } />
							</div>
						</div>
					</div>
				);
			} ) }
		</div>
	);
}

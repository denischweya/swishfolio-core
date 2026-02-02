/**
 * FAQ Block - Deprecations
 *
 * Handles backward compatibility when the block structure changes.
 * Each deprecation represents a previous version of the block's save output.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Version 1.0.0 - Initial release
 *
 * This deprecation captures the original save output structure.
 */
const v1 = {
	attributes: {
		allowMultipleOpen: {
			type: 'boolean',
			default: false,
		},
		defaultOpenFirst: {
			type: 'boolean',
			default: true,
		},
		iconStyle: {
			type: 'string',
			default: 'plus',
		},
		iconPosition: {
			type: 'string',
			default: 'right',
		},
		items: {
			type: 'array',
			default: [],
		},
	},

	supports: {
		align: [ 'wide', 'full' ],
		html: false,
		spacing: {
			margin: true,
			padding: true,
		},
	},

	save( { attributes } ) {
		const {
			allowMultipleOpen,
			defaultOpenFirst,
			iconStyle,
			iconPosition,
			items,
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
							>
								{ iconPosition === 'left' && renderIcon() }
								<span className="sfcore-faq__question">
									<RichText.Content value={ item.question } />
								</span>
								{ iconPosition === 'right' && renderIcon() }
							</button>
							<div
								id={ `faq-content-${ index }` }
								className="sfcore-faq__content"
								aria-hidden={ isDefaultOpen ? 'false' : 'true' }
							>
								<div className="sfcore-faq__answer">
									<RichText.Content value={ item.answer } />
								</div>
							</div>
						</div>
					);
				} ) }
			</div>
		);
	},
};

// Export deprecations array
export default [ v1 ];

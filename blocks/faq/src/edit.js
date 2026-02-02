/**
 * FAQ Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	Button,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	const {
		allowMultipleOpen,
		defaultOpenFirst,
		iconStyle,
		iconPosition,
		items,
	} = attributes;

	const [ openItems, setOpenItems ] = useState(
		defaultOpenFirst && items.length > 0 ? [ 0 ] : []
	);

	const blockProps = useBlockProps( {
		className: `sfcore-faq sfcore-faq--icon-${ iconPosition }`,
	} );

	const addItem = () => {
		const newItems = [
			...items,
			{
				question: '',
				answer: '',
			},
		];
		setAttributes( { items: newItems } );
	};

	const removeItem = ( index ) => {
		const newItems = items.filter( ( _, i ) => i !== index );
		setAttributes( { items: newItems } );
		setOpenItems( openItems.filter( ( i ) => i !== index ).map( ( i ) => ( i > index ? i - 1 : i ) ) );
	};

	const updateItem = ( index, field, value ) => {
		const newItems = [ ...items ];
		newItems[ index ] = { ...newItems[ index ], [ field ]: value };
		setAttributes( { items: newItems } );
	};

	const toggleItem = ( index ) => {
		if ( allowMultipleOpen ) {
			if ( openItems.includes( index ) ) {
				setOpenItems( openItems.filter( ( i ) => i !== index ) );
			} else {
				setOpenItems( [ ...openItems, index ] );
			}
		} else {
			if ( openItems.includes( index ) ) {
				setOpenItems( [] );
			} else {
				setOpenItems( [ index ] );
			}
		}
	};

	const renderIcon = ( isOpen ) => {
		if ( iconStyle === 'plus' ) {
			return <span className="sfcore-faq__icon">{ isOpen ? '−' : '+' }</span>;
		}
		if ( iconStyle === 'chevron' ) {
			return <span className={ `sfcore-faq__icon sfcore-faq__icon--chevron ${ isOpen ? 'is-open' : '' }` }>▼</span>;
		}
		if ( iconStyle === 'arrow' ) {
			return <span className={ `sfcore-faq__icon sfcore-faq__icon--arrow ${ isOpen ? 'is-open' : '' }` }>→</span>;
		}
		return null;
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'FAQ Settings', 'swishfolio-core' ) }>
					<ToggleControl
						label={ __( 'Allow Multiple Open', 'swishfolio-core' ) }
						help={ __( 'Allow multiple FAQ items to be open at the same time.', 'swishfolio-core' ) }
						checked={ allowMultipleOpen }
						onChange={ ( value ) => setAttributes( { allowMultipleOpen: value } ) }
					/>

					<ToggleControl
						label={ __( 'Open First Item by Default', 'swishfolio-core' ) }
						checked={ defaultOpenFirst }
						onChange={ ( value ) => setAttributes( { defaultOpenFirst: value } ) }
					/>

					<ToggleGroupControl
						label={ __( 'Icon Style', 'swishfolio-core' ) }
						value={ iconStyle }
						onChange={ ( value ) => setAttributes( { iconStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="plus" label={ __( 'Plus', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="chevron" label={ __( 'Chevron', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="arrow" label={ __( 'Arrow', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Icon Position', 'swishfolio-core' ) }
						value={ iconPosition }
						onChange={ ( value ) => setAttributes( { iconPosition: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
					</ToggleGroupControl>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ items.map( ( item, index ) => {
					const isOpen = openItems.includes( index );
					return (
						<div
							key={ index }
							className={ `sfcore-faq__item ${ isOpen ? 'is-open' : '' }` }
						>
							<button
								type="button"
								className="sfcore-faq__header"
								onClick={ () => toggleItem( index ) }
								aria-expanded={ isOpen }
							>
								{ iconPosition === 'left' && renderIcon( isOpen ) }
								<RichText
									tagName="span"
									className="sfcore-faq__question"
									value={ item.question }
									onChange={ ( value ) => updateItem( index, 'question', value ) }
									placeholder={ __( 'Add your question...', 'swishfolio-core' ) }
									onClick={ ( e ) => e.stopPropagation() }
								/>
								{ iconPosition === 'right' && renderIcon( isOpen ) }
							</button>
							<div
								className="sfcore-faq__content"
								style={ { display: isOpen ? 'block' : 'none' } }
							>
								<RichText
									tagName="div"
									className="sfcore-faq__answer"
									value={ item.answer }
									onChange={ ( value ) => updateItem( index, 'answer', value ) }
									placeholder={ __( 'Add your answer...', 'swishfolio-core' ) }
								/>
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => removeItem( index ) }
									className="sfcore-faq__remove"
								>
									{ __( 'Remove', 'swishfolio-core' ) }
								</Button>
							</div>
						</div>
					);
				} ) }

				<Button
					variant="secondary"
					onClick={ addItem }
					className="sfcore-faq__add"
				>
					{ __( 'Add FAQ Item', 'swishfolio-core' ) }
				</Button>
			</div>
		</>
	);
}

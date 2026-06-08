import {
	useBlockProps,
	RichText,
	BlockControls,
	AlignmentToolbar,
	InspectorControls,
	PanelColorSettings,
	__experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import {
	Button,
	ToolbarGroup,
	ToolbarButton,
	PanelBody,
	SelectControl,
	RangeControl,
	ToggleControl,
	Popover,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plus, trash, arrowUp, arrowLeft, arrowRight, link as linkIcon } from '@wordpress/icons';
import { useState } from '@wordpress/element';
import { ICONS, ICON_OPTIONS } from './icons';

const blankLink = () => ( {
	text: 'Label',
	url: '',
	handle: '',
	icon: '',
	iconPosition: 'after',
	newTab: false,
} );

const HOVER_STYLE_OPTIONS = [
	{ value: 'none', label: __( 'None', 'swishfolio-core' ) },
	{ value: 'underline', label: __( 'Underline', 'swishfolio-core' ) },
	{ value: 'underline-grow', label: __( 'Underline grow', 'swishfolio-core' ) },
	{ value: 'color', label: __( 'Color shift', 'swishfolio-core' ) },
];

const DIRECTION_OPTIONS = [
	{ value: 'horizontal', label: __( 'Horizontal', 'swishfolio-core' ) },
	{ value: 'vertical', label: __( 'Vertical', 'swishfolio-core' ) },
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		links = [],
		direction = 'horizontal',
		gap = 28,
		hoverColor = '',
		hoverStyle = 'underline-grow',
		linksAlign = 'center',
	} = attributes;

	const [ activeLink, setActiveLink ] = useState( 0 );
	const [ urlPopoverOpen, setUrlPopoverOpen ] = useState( false );

	const wrapperStyle = {
		flexDirection: direction === 'vertical' ? 'column' : 'row',
		gap: `${ gap }px`,
	};
	if ( hoverColor ) {
		wrapperStyle[ '--swish-links-hover-color' ] = hoverColor;
	}

	const blockProps = useBlockProps( {
		className: `wp-block-swishfolio-core-swish-links sfcore-links sfcore-links--${ direction } sfcore-links--hover-${ hoverStyle } sfcore-links--align-${ linksAlign }`,
		style: wrapperStyle,
	} );

	const updateLink = ( index, field, value ) => {
		const next = links.map( ( link, i ) =>
			i === index ? { ...link, [ field ]: value } : link
		);
		setAttributes( { links: next } );
	};

	const addLink = () => {
		setAttributes( { links: [ ...links, blankLink() ] } );
		setActiveLink( links.length );
	};

	const removeLink = ( index ) => {
		const next = links.filter( ( _, i ) => i !== index );
		setAttributes( { links: next } );
		setActiveLink( Math.max( 0, index - 1 ) );
	};

	const moveLink = ( index, dir ) => {
		const target = index + dir;
		if ( target < 0 || target >= links.length ) {
			return;
		}
		const next = [ ...links ];
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		setAttributes( { links: next } );
		setActiveLink( target );
	};

	const active = links[ activeLink ] || null;

	return (
		<div { ...blockProps }>
			<BlockControls>
				<AlignmentToolbar
					value={ linksAlign }
					onChange={ ( v ) =>
						setAttributes( { linksAlign: v || 'center' } )
					}
				/>
				<ToolbarGroup>
					<ToolbarButton
						icon={ linkIcon }
						label={ __( 'Edit URL', 'swishfolio-core' ) }
						onClick={ () => setUrlPopoverOpen( ( o ) => ! o ) }
						disabled={ ! active }
						isActive={ urlPopoverOpen }
					/>
					<ToolbarButton
						icon={ direction === 'vertical' ? arrowUp : arrowLeft }
						label={ __( 'Move earlier', 'swishfolio-core' ) }
						onClick={ () => moveLink( activeLink, -1 ) }
						disabled={ activeLink <= 0 }
					/>
					<ToolbarButton
						icon={ direction === 'vertical' ? arrowUp : arrowRight }
						label={ __( 'Move later', 'swishfolio-core' ) }
						onClick={ () => moveLink( activeLink, 1 ) }
						disabled={ activeLink >= links.length - 1 }
					/>
					<ToolbarButton
						icon={ trash }
						label={ __( 'Remove link', 'swishfolio-core' ) }
						onClick={ () => removeLink( activeLink ) }
						disabled={ links.length <= 1 }
						isDestructive
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) } initialOpen>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Direction', 'swishfolio-core' ) }
						value={ direction }
						options={ DIRECTION_OPTIONS }
						onChange={ ( v ) => setAttributes( { direction: v } ) }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Gap (px)', 'swishfolio-core' ) }
						value={ gap }
						min={ 0 }
						max={ 64 }
						onChange={ ( v ) => setAttributes( { gap: v } ) }
					/>
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Hover color', 'swishfolio-core' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: hoverColor,
							onChange: ( c ) => setAttributes( { hoverColor: c || '' } ),
							label: __( 'Hover color', 'swishfolio-core' ),
						},
					] }
				/>

				<PanelBody title={ __( 'Hover style', 'swishfolio-core' ) } initialOpen={ false }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Style', 'swishfolio-core' ) }
						value={ hoverStyle }
						options={ HOVER_STYLE_OPTIONS }
						onChange={ ( v ) => setAttributes( { hoverStyle: v } ) }
						help={ __(
							'How each link reacts on hover. If an icon is set, it also shifts in its direction.',
							'swishfolio-core'
						) }
					/>
				</PanelBody>

				{ active && (
					<PanelBody
						title={ __( 'Selected link', 'swishfolio-core' ) }
						initialOpen
					>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Icon', 'swishfolio-core' ) }
							value={ active.icon }
							options={ ICON_OPTIONS }
							onChange={ ( v ) => updateLink( activeLink, 'icon', v ) }
						/>
						{ active.icon && (
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Icon position', 'swishfolio-core' ) }
								value={ active.iconPosition }
								options={ [
									{ value: 'before', label: __( 'Before', 'swishfolio-core' ) },
									{ value: 'after', label: __( 'After', 'swishfolio-core' ) },
								] }
								onChange={ ( v ) =>
									updateLink( activeLink, 'iconPosition', v )
								}
							/>
						) }
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Open in new tab', 'swishfolio-core' ) }
							checked={ !! active.newTab }
							onChange={ ( v ) => updateLink( activeLink, 'newTab', v ) }
						/>
					</PanelBody>
				) }
			</InspectorControls>

			{ links.map( ( link, index ) => {
				const isActive = activeLink === index;
				const iconEl = link.icon ? (
					<span
						className={ `sfcore-link__icon sfcore-link__icon--${ link.iconPosition }` }
						aria-hidden="true"
					>
						{ ICONS[ link.icon ] }
					</span>
				) : null;

				return (
					<span
						key={ index }
						className={ `sfcore-link${ isActive ? ' is-active' : '' }${
							link.icon ? ' sfcore-link--with-icon' : ''
						}` }
						onClick={ () => setActiveLink( index ) }
						onFocus={ () => setActiveLink( index ) }
					>
						{ link.iconPosition === 'before' && iconEl }
						<RichText
							tagName="span"
							className="sfcore-link__text"
							value={ link.text }
							onChange={ ( v ) => updateLink( index, 'text', v ) }
							placeholder={ __( 'Label', 'swishfolio-core' ) }
							allowedFormats={ [] }
						/>
						<RichText
							tagName="span"
							className="sfcore-link__handle"
							value={ link.handle }
							onChange={ ( v ) => updateLink( index, 'handle', v ) }
							placeholder={ __( '/handle', 'swishfolio-core' ) }
							allowedFormats={ [] }
						/>
						{ link.iconPosition === 'after' && iconEl }
					</span>
				);
			} ) }

			<Button
				variant="secondary"
				icon={ plus }
				onClick={ addLink }
				className="sfcore-links__add"
			>
				{ __( 'Add link', 'swishfolio-core' ) }
			</Button>

			{ urlPopoverOpen && active && (
				<Popover
					placement="bottom-start"
					onClose={ () => setUrlPopoverOpen( false ) }
				>
					<div style={ { padding: 8, minWidth: 280 } }>
						<LinkControl
							value={ {
								url: active.url,
								opensInNewTab: !! active.newTab,
							} }
							onChange={ ( next ) => {
								updateLink( activeLink, 'url', next.url || '' );
								updateLink( activeLink, 'newTab', !! next.opensInNewTab );
							} }
							settings={ [
								{
									id: 'opensInNewTab',
									title: __( 'Open in new tab', 'swishfolio-core' ),
								},
							] }
						/>
					</div>
				</Popover>
			) }
		</div>
	);
}

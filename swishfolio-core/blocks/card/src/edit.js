/**
 * Card Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	URLInput,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	Button,
	ButtonGroup,
	ColorPalette,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Icon,
} from '@wordpress/components';
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
	linkOff,
	info,
	warning,
	help,
	close,
	plus,
	chevronUp,
	chevronDown,
	chevronLeft,
	chevronRight,
	arrowUp,
	arrowDown,
	arrowLeft,
	arrowRight,
	inbox,
	envelope,
	shield,
	gift,
	bell,
	calendar,
	cog,
	pencil,
	trash,
} from '@wordpress/icons';

// Icon library mapping - only using icons available in @wordpress/icons
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
	'link-off': linkOff,
	info: info,
	warning: warning,
	help: help,
	close: close,
	plus: plus,
	'chevron-up': chevronUp,
	'chevron-down': chevronDown,
	'chevron-left': chevronLeft,
	'chevron-right': chevronRight,
	'arrow-up': arrowUp,
	'arrow-down': arrowDown,
	'arrow-left': arrowLeft,
	'arrow-right': arrowRight,
	inbox: inbox,
	envelope: envelope,
	shield: shield,
	gift: gift,
	bell: bell,
	calendar: calendar,
	cog: cog,
	pencil: pencil,
	trash: trash,
};

// Bauhaus colors for the palette
const BAUHAUS_COLORS = [
	{ name: 'Primary Red', color: '#D02020' },
	{ name: 'Primary Blue', color: '#1040C0' },
	{ name: 'Primary Yellow', color: '#F0C020' },
	{ name: 'Contrast', color: '#121212' },
	{ name: 'Base', color: '#F0F0F0' },
];

export default function Edit( { attributes, setAttributes } ) {
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

	const blockProps = useBlockProps( {
		className: `sfcore-card sfcore-card--icon-${ iconPosition } sfcore-card--icon-${ iconSize } sfcore-card--icon-shape-${ iconShape } sfcore-card--border-${ cardBorderStyle }`,
		style: cardStyles,
	} );

	// Get icon component from library or render custom SVG
	const renderIcon = () => {
		const iconStyles = {
			color: iconColor || undefined,
			backgroundColor: iconBackgroundColor || '#F0C020',
		};

		if ( icon.type === 'library' && ICON_LIBRARY[ icon.value ] ) {
			const iconComponent = ICON_LIBRARY[ icon.value ];
			return (
				<span className="sfcore-card__icon" style={ iconStyles }>
					<Icon icon={ iconComponent } />
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

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Icon Settings', 'swishfolio-core' ) }>
					<SelectControl
						label={ __( 'Icon Source', 'swishfolio-core' ) }
						value={ icon.type }
						options={ [
							{ label: __( 'Icon Library', 'swishfolio-core' ), value: 'library' },
							{ label: __( 'Custom Image', 'swishfolio-core' ), value: 'custom' },
						] }
						onChange={ ( type ) =>
							setAttributes( {
								icon: { ...icon, type, value: type === 'library' ? 'star' : '' },
							} )
						}
					/>

					{ icon.type === 'library' && (
						<>
							<p className="components-base-control__label">
								{ __( 'Select Icon', 'swishfolio-core' ) }
							</p>
							<div className="sfcore-icon-picker">
								{ Object.keys( ICON_LIBRARY ).map( ( iconKey ) => {
									const iconComponent = ICON_LIBRARY[ iconKey ];
									return (
										<Button
											key={ iconKey }
											className={ `sfcore-icon-picker__item ${
												icon.value === iconKey ? 'is-selected' : ''
											}` }
											onClick={ () =>
												setAttributes( {
													icon: { ...icon, value: iconKey },
												} )
											}
											label={ iconKey }
										>
											<Icon icon={ iconComponent } />
										</Button>
									);
								} ) }
							</div>
						</>
					) }

					{ icon.type === 'custom' && (
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									setAttributes( {
										icon: { ...icon, value: media.url },
									} )
								}
								allowedTypes={ [ 'image/svg+xml', 'image' ] }
								render={ ( { open } ) => (
									<>
										{ icon.value ? (
											<div className="sfcore-custom-icon-preview">
												<img src={ icon.value } alt="" />
												<ButtonGroup>
													<Button variant="secondary" onClick={ open }>
														{ __( 'Replace', 'swishfolio-core' ) }
													</Button>
													<Button
														variant="tertiary"
														isDestructive
														onClick={ () =>
															setAttributes( {
																icon: { ...icon, value: '' },
															} )
														}
													>
														{ __( 'Remove', 'swishfolio-core' ) }
													</Button>
												</ButtonGroup>
											</div>
										) : (
											<Button variant="secondary" onClick={ open }>
												{ __( 'Upload Icon', 'swishfolio-core' ) }
											</Button>
										) }
									</>
								) }
							/>
						</MediaUploadCheck>
					) }

					<ToggleGroupControl
						label={ __( 'Icon Position', 'swishfolio-core' ) }
						value={ iconPosition }
						onChange={ ( value ) => setAttributes( { iconPosition: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="top" label={ __( 'Top', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Icon Size', 'swishfolio-core' ) }
						value={ iconSize }
						onChange={ ( value ) => setAttributes( { iconSize: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<p className="components-base-control__label">
						{ __( 'Icon Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ iconColor }
						onChange={ ( color ) => setAttributes( { iconColor: color } ) }
						clearable
					/>

					<p className="components-base-control__label">
						{ __( 'Icon Background', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ iconBackgroundColor }
						onChange={ ( color ) => setAttributes( { iconBackgroundColor: color } ) }
						clearable
					/>

					<ToggleGroupControl
						label={ __( 'Icon Shape', 'swishfolio-core' ) }
						value={ iconShape }
						onChange={ ( value ) => setAttributes( { iconShape: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="square" label={ __( 'Square', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="rounded" label={ __( 'Rounded', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="circle" label={ __( 'Circle', 'swishfolio-core' ) } />
					</ToggleGroupControl>
				</PanelBody>

				<PanelBody
					title={ __( 'Card Style', 'swishfolio-core' ) }
					initialOpen={ false }
				>
					<ToggleGroupControl
						label={ __( 'Border Style', 'swishfolio-core' ) }
						value={ cardBorderStyle }
						onChange={ ( value ) => setAttributes( { cardBorderStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="solid" label={ __( 'Solid', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="dashed" label={ __( 'Dashed', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<p className="components-base-control__label">
						{ __( 'Border Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ cardBorderColor }
						onChange={ ( color ) => setAttributes( { cardBorderColor: color } ) }
						clearable
					/>

					<p className="components-base-control__label">
						{ __( 'Shadow Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ cardShadowColor }
						onChange={ ( color ) => setAttributes( { cardShadowColor: color } ) }
						clearable
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Link Settings', 'swishfolio-core' ) }
					initialOpen={ false }
				>
					<URLInput
						label={ __( 'Link URL', 'swishfolio-core' ) }
						value={ linkUrl }
						onChange={ ( url ) => setAttributes( { linkUrl: url } ) }
					/>
					<ToggleGroupControl
						label={ __( 'Link Style', 'swishfolio-core' ) }
						value={ linkStyle }
						onChange={ ( value ) => setAttributes( { linkStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="text" label={ __( 'Text', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="button" label={ __( 'Button', 'swishfolio-core' ) } />
					</ToggleGroupControl>
				</PanelBody>

				<PanelBody
					title={ __( 'Decorative Shape', 'swishfolio-core' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Shape', 'swishfolio-core' ) }
						value={ decorativeShape }
						options={ [
							{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
							{ label: __( 'Circle', 'swishfolio-core' ), value: 'circle' },
							{ label: __( 'Square', 'swishfolio-core' ), value: 'square' },
							{ label: __( 'Triangle', 'swishfolio-core' ), value: 'triangle' },
						] }
						onChange={ ( value ) => setAttributes( { decorativeShape: value } ) }
					/>
					{ decorativeShape !== 'none' && (
						<>
							<p className="components-base-control__label">
								{ __( 'Shape Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ BAUHAUS_COLORS }
								value={ decorativeColor }
								onChange={ ( color ) => setAttributes( { decorativeColor: color } ) }
								clearable
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ renderDecorativeShape() }
				<div className="sfcore-card__inner">
					{ renderIcon() }
					<div className="sfcore-card__content">
						<RichText
							tagName="h3"
							className="sfcore-card__title"
							value={ title }
							onChange={ ( value ) => setAttributes( { title: value } ) }
							placeholder={ __( 'Card Title', 'swishfolio-core' ) }
						/>
						<RichText
							tagName="p"
							className="sfcore-card__description"
							value={ description }
							onChange={ ( value ) => setAttributes( { description: value } ) }
							placeholder={ __( 'Card description goes here...', 'swishfolio-core' ) }
						/>
						{ ( linkUrl || linkText ) && (
							<div className="sfcore-card__link-wrapper">
								<RichText
									tagName="span"
									className={ `sfcore-card__link sfcore-card__link--${ linkStyle }` }
									value={ linkText }
									onChange={ ( value ) => setAttributes( { linkText: value } ) }
									placeholder={ __( 'Learn more', 'swishfolio-core' ) }
								/>
							</div>
						) }
					</div>
				</div>
			</div>
		</>
	);
}

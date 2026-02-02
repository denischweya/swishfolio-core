/**
 * Custom Heading Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ColorPalette,
	Button,
	ButtonGroup,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Icon,
} from '@wordpress/components';
import {
	starFilled,
	starEmpty,
	check,
	chevronLeft,
	chevronRight,
	arrowLeft,
	arrowRight,
	warning,
	info,
	shield,
	lock,
	key,
	people,
	globe,
	desktop,
	mobile,
	code,
	brush,
	tool,
	settings,
	category,
	tag,
	grid,
	list,
	inbox,
	envelope,
	bell,
	calendar,
	gift,
	cog,
	pencil,
	store,
	plugins,
	square,
} from '@wordpress/icons';

// Neo-brutalist color palette
const NEO_BRUTALIST_COLORS = [
	{ name: 'Pure Black', color: '#000000' },
	{ name: 'Cream', color: '#FFFDF5' },
	{ name: 'Hot Red', color: '#FF6B6B' },
	{ name: 'Vivid Yellow', color: '#FFD93D' },
	{ name: 'Soft Violet', color: '#C4B5FD' },
	{ name: 'White', color: '#FFFFFF' },
];

// Icon library mapping
const ICON_LIBRARY = {
	star: starFilled,
	'star-empty': starEmpty,
	check: check,
	'chevron-left': chevronLeft,
	'chevron-right': chevronRight,
	'arrow-left': arrowLeft,
	'arrow-right': arrowRight,
	warning: warning,
	info: info,
	shield: shield,
	lock: lock,
	key: key,
	people: people,
	globe: globe,
	desktop: desktop,
	mobile: mobile,
	code: code,
	brush: brush,
	tool: tool,
	settings: settings,
	category: category,
	tag: tag,
	grid: grid,
	list: list,
	inbox: inbox,
	envelope: envelope,
	bell: bell,
	calendar: calendar,
	gift: gift,
	cog: cog,
	pencil: pencil,
	store: store,
	plugins: plugins,
	square: square,
};

export default function Edit( { attributes, setAttributes } ) {
	const {
		preHeading,
		preHeadingBackgroundColor,
		preHeadingTextColor,
		preHeadingRotation,
		preHeadingBorderStyle,
		preHeadingBorderWidth,
		preHeadingBorderColor,
		preHeadingShadowSize,
		showPreHeading,

		heading,
		headingTag,
		headingBackgroundColor,
		headingTextColor,
		headingRotation,
		headingBorderStyle,
		headingBorderWidth,
		headingBorderColor,
		headingShadowSize,
		headingSize,
		headingStyle,
		showHeading,

		subHeading,
		subHeadingBackgroundColor,
		subHeadingTextColor,
		subHeadingRotation,
		subHeadingBorderStyle,
		subHeadingBorderWidth,
		subHeadingBorderColor,
		subHeadingShadowSize,
		subHeadingFontSize,
		showSubHeading,

		leftIcon,
		leftIconColor,
		leftIconBackgroundColor,
		leftIconSize,

		rightIcon,
		rightIconColor,
		rightIconBackgroundColor,
		rightIconSize,

		contentAlign,
		blockBackgroundColor,
		showBackgroundPattern,
		backgroundPattern,
	} = attributes;

	// Helper function to generate shadow CSS
	const getShadow = ( size ) => {
		const shadows = {
			none: 'none',
			small: '4px 4px 0 #000',
			medium: '8px 8px 0 #000',
			large: '12px 12px 0 #000',
			xlarge: '16px 16px 0 #000',
		};
		return shadows[ size ] || shadows.none;
	};

	// Get element styles
	const getPreHeadingStyles = () => ( {
		backgroundColor: preHeadingBackgroundColor || undefined,
		color: preHeadingTextColor || undefined,
		transform: preHeadingRotation ? `rotate(${ preHeadingRotation }deg)` : undefined,
		borderStyle: preHeadingBorderStyle !== 'none' ? preHeadingBorderStyle : undefined,
		borderWidth: preHeadingBorderStyle !== 'none' ? `${ preHeadingBorderWidth }px` : undefined,
		borderColor: preHeadingBorderStyle !== 'none' ? preHeadingBorderColor : undefined,
		boxShadow: getShadow( preHeadingShadowSize ),
	} );

	const getHeadingStyles = () => ( {
		backgroundColor: headingBackgroundColor || undefined,
		color: headingTextColor || undefined,
		transform: headingRotation ? `rotate(${ headingRotation }deg)` : undefined,
		borderStyle: headingBorderStyle !== 'none' ? headingBorderStyle : undefined,
		borderWidth: headingBorderStyle !== 'none' ? `${ headingBorderWidth }px` : undefined,
		borderColor: headingBorderStyle !== 'none' ? headingBorderColor : undefined,
		boxShadow: getShadow( headingShadowSize ),
	} );

	const getSubHeadingStyles = () => ( {
		backgroundColor: subHeadingBackgroundColor || undefined,
		color: subHeadingTextColor || undefined,
		transform: subHeadingRotation ? `rotate(${ subHeadingRotation }deg)` : undefined,
		borderStyle: subHeadingBorderStyle !== 'none' ? subHeadingBorderStyle : undefined,
		borderWidth: subHeadingBorderStyle !== 'none' ? `${ subHeadingBorderWidth }px` : undefined,
		borderColor: subHeadingBorderStyle !== 'none' ? subHeadingBorderColor : undefined,
		boxShadow: getShadow( subHeadingShadowSize ),
	} );

	// Get heading classes
	const getHeadingClasses = () => {
		const classes = [ 'sfcore-heading__main' ];
		classes.push( `sfcore-heading__main--${ headingSize }` );
		if ( headingStyle !== 'normal' ) {
			classes.push( `sfcore-heading__main--${ headingStyle }` );
		}
		if ( headingBackgroundColor ) {
			classes.push( 'sfcore-heading__main--has-bg' );
		}
		return classes.join( ' ' );
	};

	// Render icon
	const renderIcon = ( icon, color, bgColor, size, position ) => {
		if ( ! icon || icon.type === 'none' ) {
			return null;
		}

		const iconStyles = {
			color: color || undefined,
			backgroundColor: bgColor || undefined,
		};

		const iconClasses = `sfcore-heading__icon sfcore-heading__icon--${ position } sfcore-heading__icon--${ size }`;

		if ( icon.type === 'library' && ICON_LIBRARY[ icon.value ] ) {
			return (
				<span className={ iconClasses } style={ iconStyles }>
					<Icon icon={ ICON_LIBRARY[ icon.value ] } />
				</span>
			);
		}

		if ( icon.type === 'custom' && icon.value ) {
			return (
				<span className={ iconClasses } style={ iconStyles }>
					<img src={ icon.value } alt="" />
				</span>
			);
		}

		return null;
	};

	// Background pattern style
	const getBackgroundPatternStyle = () => {
		if ( ! showBackgroundPattern ) {
			return {};
		}

		const patterns = {
			dots: {
				backgroundImage: 'radial-gradient(#000 1.5px, transparent 1.5px)',
				backgroundSize: '20px 20px',
			},
			grid: {
				backgroundImage: 'linear-gradient(to right, rgba(0, 0, 0, 0.1) 1px, transparent 1px), linear-gradient(to bottom, rgba(0, 0, 0, 0.1) 1px, transparent 1px)',
				backgroundSize: '40px 40px',
			},
			'large-dots': {
				backgroundImage: 'radial-gradient(circle, #000 2px, transparent 2.5px)',
				backgroundSize: '30px 30px',
			},
		};

		return patterns[ backgroundPattern ] || {};
	};

	const blockStyles = {
		backgroundColor: blockBackgroundColor || undefined,
		...getBackgroundPatternStyle(),
	};

	const blockProps = useBlockProps( {
		className: `sfcore-heading sfcore-heading--align-${ contentAlign }`,
		style: blockStyles,
	} );

	// Icon settings panel component
	const IconSettingsPanel = ( { position, icon, setIcon, iconColor, setIconColor, iconBgColor, setIconBgColor, iconSize, setIconSize } ) => (
		<PanelBody
			title={ position === 'left' ? __( 'Left Icon', 'swishfolio-core' ) : __( 'Right Icon', 'swishfolio-core' ) }
			initialOpen={ false }
		>
			<SelectControl
				label={ __( 'Icon Source', 'swishfolio-core' ) }
				value={ icon.type }
				options={ [
					{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
					{ label: __( 'Icon Library', 'swishfolio-core' ), value: 'library' },
					{ label: __( 'Custom SVG', 'swishfolio-core' ), value: 'custom' },
				] }
				onChange={ ( type ) => setIcon( { type, value: type === 'library' ? 'star' : '' } ) }
			/>

			{ icon.type === 'library' && (
				<>
					<p className="components-base-control__label">
						{ __( 'Select Icon', 'swishfolio-core' ) }
					</p>
					<div className="sfcore-icon-picker">
						{ Object.keys( ICON_LIBRARY ).map( ( iconKey ) => (
							<Button
								key={ iconKey }
								className={ `sfcore-icon-picker__item ${ icon.value === iconKey ? 'is-selected' : '' }` }
								onClick={ () => setIcon( { ...icon, value: iconKey } ) }
								label={ iconKey }
							>
								<Icon icon={ ICON_LIBRARY[ iconKey ] } />
							</Button>
						) ) }
					</div>
				</>
			) }

			{ icon.type === 'custom' && (
				<MediaUploadCheck>
					<MediaUpload
						onSelect={ ( media ) => setIcon( { ...icon, value: media.url } ) }
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
												onClick={ () => setIcon( { ...icon, value: '' } ) }
											>
												{ __( 'Remove', 'swishfolio-core' ) }
											</Button>
										</ButtonGroup>
									</div>
								) : (
									<Button variant="secondary" onClick={ open }>
										{ __( 'Upload SVG', 'swishfolio-core' ) }
									</Button>
								) }
							</>
						) }
					/>
				</MediaUploadCheck>
			) }

			{ icon.type !== 'none' && (
				<>
					<ToggleGroupControl
						label={ __( 'Icon Size', 'swishfolio-core' ) }
						value={ iconSize }
						onChange={ setIconSize }
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
						colors={ NEO_BRUTALIST_COLORS }
						value={ iconColor }
						onChange={ setIconColor }
						clearable
					/>

					<p className="components-base-control__label">
						{ __( 'Icon Background', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ NEO_BRUTALIST_COLORS }
						value={ iconBgColor }
						onChange={ setIconBgColor }
						clearable
					/>
				</>
			) }
		</PanelBody>
	);

	// Text element settings panel component
	const TextElementPanel = ( {
		title,
		show,
		setShow,
		bgColor,
		setBgColor,
		textColor,
		setTextColor,
		rotation,
		setRotation,
		borderStyle,
		setBorderStyle,
		borderWidth,
		setBorderWidth,
		borderColor,
		setBorderColor,
		shadowSize,
		setShadowSize,
		extraControls,
		initialOpen = false,
	} ) => (
		<PanelBody title={ title } initialOpen={ initialOpen }>
			<ToggleControl
				label={ __( 'Show Element', 'swishfolio-core' ) }
				checked={ show }
				onChange={ setShow }
			/>

			{ show && (
				<>
					<p className="components-base-control__label">
						{ __( 'Background Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ NEO_BRUTALIST_COLORS }
						value={ bgColor }
						onChange={ setBgColor }
						clearable
					/>

					<p className="components-base-control__label">
						{ __( 'Text Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ NEO_BRUTALIST_COLORS }
						value={ textColor }
						onChange={ setTextColor }
						clearable
					/>

					<RangeControl
						label={ __( 'Rotation', 'swishfolio-core' ) }
						value={ rotation }
						onChange={ setRotation }
						min={ -10 }
						max={ 10 }
					/>

					<ToggleGroupControl
						label={ __( 'Border Style', 'swishfolio-core' ) }
						value={ borderStyle }
						onChange={ setBorderStyle }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="solid" label={ __( 'Solid', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="dashed" label={ __( 'Dashed', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					{ borderStyle !== 'none' && (
						<>
							<RangeControl
								label={ __( 'Border Width', 'swishfolio-core' ) }
								value={ borderWidth }
								onChange={ setBorderWidth }
								min={ 1 }
								max={ 8 }
							/>

							<p className="components-base-control__label">
								{ __( 'Border Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ NEO_BRUTALIST_COLORS }
								value={ borderColor }
								onChange={ setBorderColor }
								clearable
							/>
						</>
					) }

					<ToggleGroupControl
						label={ __( 'Shadow Size', 'swishfolio-core' ) }
						value={ shadowSize }
						onChange={ setShadowSize }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					{ extraControls }
				</>
			) }
		</PanelBody>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
					<ToggleGroupControl
						label={ __( 'Content Alignment', 'swishfolio-core' ) }
						value={ contentAlign }
						onChange={ ( value ) => setAttributes( { contentAlign: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="center" label={ __( 'Center', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<p className="components-base-control__label">
						{ __( 'Block Background', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ NEO_BRUTALIST_COLORS }
						value={ blockBackgroundColor }
						onChange={ ( color ) => setAttributes( { blockBackgroundColor: color } ) }
						clearable
					/>

					<ToggleControl
						label={ __( 'Show Background Pattern', 'swishfolio-core' ) }
						checked={ showBackgroundPattern }
						onChange={ ( value ) => setAttributes( { showBackgroundPattern: value } ) }
					/>

					{ showBackgroundPattern && (
						<SelectControl
							label={ __( 'Pattern Type', 'swishfolio-core' ) }
							value={ backgroundPattern }
							options={ [
								{ label: __( 'Halftone Dots', 'swishfolio-core' ), value: 'dots' },
								{ label: __( 'Grid', 'swishfolio-core' ), value: 'grid' },
								{ label: __( 'Large Dots', 'swishfolio-core' ), value: 'large-dots' },
							] }
							onChange={ ( value ) => setAttributes( { backgroundPattern: value } ) }
						/>
					) }
				</PanelBody>

				<TextElementPanel
					title={ __( 'Pre-Heading', 'swishfolio-core' ) }
					show={ showPreHeading }
					setShow={ ( value ) => setAttributes( { showPreHeading: value } ) }
					bgColor={ preHeadingBackgroundColor }
					setBgColor={ ( color ) => setAttributes( { preHeadingBackgroundColor: color } ) }
					textColor={ preHeadingTextColor }
					setTextColor={ ( color ) => setAttributes( { preHeadingTextColor: color } ) }
					rotation={ preHeadingRotation }
					setRotation={ ( value ) => setAttributes( { preHeadingRotation: value } ) }
					borderStyle={ preHeadingBorderStyle }
					setBorderStyle={ ( value ) => setAttributes( { preHeadingBorderStyle: value } ) }
					borderWidth={ preHeadingBorderWidth }
					setBorderWidth={ ( value ) => setAttributes( { preHeadingBorderWidth: value } ) }
					borderColor={ preHeadingBorderColor }
					setBorderColor={ ( color ) => setAttributes( { preHeadingBorderColor: color } ) }
					shadowSize={ preHeadingShadowSize }
					setShadowSize={ ( value ) => setAttributes( { preHeadingShadowSize: value } ) }
				/>

				<TextElementPanel
					title={ __( 'Heading', 'swishfolio-core' ) }
					show={ showHeading }
					setShow={ ( value ) => setAttributes( { showHeading: value } ) }
					bgColor={ headingBackgroundColor }
					setBgColor={ ( color ) => setAttributes( { headingBackgroundColor: color } ) }
					textColor={ headingTextColor }
					setTextColor={ ( color ) => setAttributes( { headingTextColor: color } ) }
					rotation={ headingRotation }
					setRotation={ ( value ) => setAttributes( { headingRotation: value } ) }
					borderStyle={ headingBorderStyle }
					setBorderStyle={ ( value ) => setAttributes( { headingBorderStyle: value } ) }
					borderWidth={ headingBorderWidth }
					setBorderWidth={ ( value ) => setAttributes( { headingBorderWidth: value } ) }
					borderColor={ headingBorderColor }
					setBorderColor={ ( color ) => setAttributes( { headingBorderColor: color } ) }
					shadowSize={ headingShadowSize }
					setShadowSize={ ( value ) => setAttributes( { headingShadowSize: value } ) }
					extraControls={
						<>
							<SelectControl
								label={ __( 'Heading Tag', 'swishfolio-core' ) }
								value={ headingTag }
								options={ [
									{ label: 'H1', value: 'h1' },
									{ label: 'H2', value: 'h2' },
									{ label: 'H3', value: 'h3' },
									{ label: 'H4', value: 'h4' },
									{ label: 'H5', value: 'h5' },
									{ label: 'H6', value: 'h6' },
								] }
								onChange={ ( value ) => setAttributes( { headingTag: value } ) }
							/>

							<ToggleGroupControl
								label={ __( 'Heading Size', 'swishfolio-core' ) }
								value={ headingSize }
								onChange={ ( value ) => setAttributes( { headingSize: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="xlarge" label={ __( 'XL', 'swishfolio-core' ) } />
							</ToggleGroupControl>

							<ToggleGroupControl
								label={ __( 'Heading Style', 'swishfolio-core' ) }
								value={ headingStyle }
								onChange={ ( value ) => setAttributes( { headingStyle: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="normal" label={ __( 'Normal', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="shadow" label={ __( 'Shadow', 'swishfolio-core' ) } />
							</ToggleGroupControl>
						</>
					}
				/>

				<TextElementPanel
					title={ __( 'Sub-Heading', 'swishfolio-core' ) }
					show={ showSubHeading }
					setShow={ ( value ) => setAttributes( { showSubHeading: value } ) }
					bgColor={ subHeadingBackgroundColor }
					setBgColor={ ( color ) => setAttributes( { subHeadingBackgroundColor: color } ) }
					textColor={ subHeadingTextColor }
					setTextColor={ ( color ) => setAttributes( { subHeadingTextColor: color } ) }
					rotation={ subHeadingRotation }
					setRotation={ ( value ) => setAttributes( { subHeadingRotation: value } ) }
					borderStyle={ subHeadingBorderStyle }
					setBorderStyle={ ( value ) => setAttributes( { subHeadingBorderStyle: value } ) }
					borderWidth={ subHeadingBorderWidth }
					setBorderWidth={ ( value ) => setAttributes( { subHeadingBorderWidth: value } ) }
					borderColor={ subHeadingBorderColor }
					setBorderColor={ ( color ) => setAttributes( { subHeadingBorderColor: color } ) }
					shadowSize={ subHeadingShadowSize }
					setShadowSize={ ( value ) => setAttributes( { subHeadingShadowSize: value } ) }
					extraControls={
						<ToggleGroupControl
							label={ __( 'Font Size', 'swishfolio-core' ) }
							value={ subHeadingFontSize }
							onChange={ ( value ) => setAttributes( { subHeadingFontSize: value } ) }
							isBlock
						>
							<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
						</ToggleGroupControl>
					}
				/>

				<IconSettingsPanel
					position="left"
					icon={ leftIcon }
					setIcon={ ( icon ) => setAttributes( { leftIcon: icon } ) }
					iconColor={ leftIconColor }
					setIconColor={ ( color ) => setAttributes( { leftIconColor: color } ) }
					iconBgColor={ leftIconBackgroundColor }
					setIconBgColor={ ( color ) => setAttributes( { leftIconBackgroundColor: color } ) }
					iconSize={ leftIconSize }
					setIconSize={ ( size ) => setAttributes( { leftIconSize: size } ) }
				/>

				<IconSettingsPanel
					position="right"
					icon={ rightIcon }
					setIcon={ ( icon ) => setAttributes( { rightIcon: icon } ) }
					iconColor={ rightIconColor }
					setIconColor={ ( color ) => setAttributes( { rightIconColor: color } ) }
					iconBgColor={ rightIconBackgroundColor }
					setIconBgColor={ ( color ) => setAttributes( { rightIconBackgroundColor: color } ) }
					iconSize={ rightIconSize }
					setIconSize={ ( size ) => setAttributes( { rightIconSize: size } ) }
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="sfcore-heading__inner">
					{ renderIcon( leftIcon, leftIconColor, leftIconBackgroundColor, leftIconSize, 'left' ) }

					<div className="sfcore-heading__content">
						{ showPreHeading && (
							<div className="sfcore-heading__pre-wrapper">
								<RichText
									tagName="span"
									className="sfcore-heading__pre"
									value={ preHeading }
									onChange={ ( value ) => setAttributes( { preHeading: value } ) }
									placeholder={ __( 'Pre-heading', 'swishfolio-core' ) }
									style={ getPreHeadingStyles() }
								/>
							</div>
						) }

						{ showHeading && (
							<div className="sfcore-heading__main-wrapper">
								<RichText
									tagName={ headingTag }
									className={ getHeadingClasses() }
									value={ heading }
									onChange={ ( value ) => setAttributes( { heading: value } ) }
									placeholder={ __( 'Main Heading', 'swishfolio-core' ) }
									style={ getHeadingStyles() }
								/>
							</div>
						) }

						{ showSubHeading && (
							<div className="sfcore-heading__sub-wrapper">
								<RichText
									tagName="p"
									className={ `sfcore-heading__sub sfcore-heading__sub--${ subHeadingFontSize } ${ subHeadingBackgroundColor ? 'sfcore-heading__sub--has-bg' : '' }` }
									value={ subHeading }
									onChange={ ( value ) => setAttributes( { subHeading: value } ) }
									placeholder={ __( 'Sub-heading text goes here...', 'swishfolio-core' ) }
									style={ getSubHeadingStyles() }
								/>
							</div>
						) }
					</div>

					{ renderIcon( rightIcon, rightIconColor, rightIconBackgroundColor, rightIconSize, 'right' ) }
				</div>
			</div>
		</>
	);
}

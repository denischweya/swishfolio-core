/**
 * Swish Slider - Edit Component.
 *
 * Parent marquee block. Holds a row of swish-slide inner blocks. Emits CSS
 * custom properties on the wrapper (--slide-w, --slide-h, --slide-ratio,
 * --slide-gap, --seconds-per-slide, --scrim-color, --scrim-opacity,
 * --slider-text-color, --cta-color, --accent-color) used by both parent and
 * slide styles.
 *
 * The marquee is static in the editor — animation only runs on the frontend
 * (see view.js).
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ColorPalette,
	RangeControl,
	TextControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const ALLOWED_BLOCKS = [ 'swishfolio-core/swish-slide' ];

const TEMPLATE = [
	[ 'swishfolio-core/swish-slide' ],
	[ 'swishfolio-core/swish-slide' ],
	[ 'swishfolio-core/swish-slide' ],
	[ 'swishfolio-core/swish-slide' ],
];

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];

	const {
		slideWidth,
		slideHeight,
		gap,
		direction,
		secondsPerSlide,
		pauseOnHover,
		revealOnHover,
		textColor,
		scrimColor,
		scrimOpacity,
		ctaColor,
		accentColor,
		ariaLabel,
	} = attributes;

	const wrapperClasses = [ 'sfcore-swish-slider' ];
	if ( direction === 'right' ) {
		wrapperClasses.push( 'sfcore-swish-slider--right' );
	}
	if ( pauseOnHover ) {
		wrapperClasses.push( 'sfcore-swish-slider--pause-hover' );
	}

	const blockProps = useBlockProps( {
		className: wrapperClasses.join( ' ' ),
		style: {
			'--slide-w': `${ slideWidth ?? 426 }px`,
			'--slide-h': `${ slideHeight ?? 262 }px`,
			'--slide-ratio': `${ slideWidth ?? 426 } / ${ slideHeight ?? 262 }`,
			'--slide-gap': `${ gap ?? 1 }rem`,
			'--seconds-per-slide': secondsPerSlide ?? 6,
			'--scrim-color': scrimColor || undefined,
			'--scrim-opacity': ( scrimOpacity ?? 60 ) / 100,
			'--slider-text-color': textColor || undefined,
			'--cta-color': ctaColor || undefined,
			'--accent-color': accentColor || undefined,
		},
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sfcore-swish-slider__track' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			orientation: 'horizontal',
		}
	);

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
				/>

				{ activeTab === 'general' && (
					<>
						<PanelBody title={ __( 'Slider', 'swishfolio-core' ) }>
							<SelectControl
								label={ __( 'Direction', 'swishfolio-core' ) }
								value={ direction }
								options={ [
									{ label: __( 'Left', 'swishfolio-core' ), value: 'left' },
									{ label: __( 'Right', 'swishfolio-core' ), value: 'right' },
								] }
								onChange={ ( value ) =>
									setAttributes( { direction: value } )
								}
							/>

							<RangeControl
								label={ __( 'Seconds per slide', 'swishfolio-core' ) }
								help={ __(
									'How long each slide takes to cross the strip. Higher = slower.',
									'swishfolio-core'
								) }
								value={ secondsPerSlide }
								onChange={ ( value ) =>
									setAttributes( { secondsPerSlide: value ?? 6 } )
								}
								min={ 1 }
								max={ 20 }
								step={ 0.5 }
							/>

							<ToggleControl
								label={ __( 'Pause on hover', 'swishfolio-core' ) }
								help={ __(
									'Stop the slider while the pointer (or keyboard focus) is over it.',
									'swishfolio-core'
								) }
								checked={ !! pauseOnHover }
								onChange={ ( value ) =>
									setAttributes( { pauseOnHover: value } )
								}
							/>

							<ToggleControl
								label={ __( 'Show text on hover', 'swishfolio-core' ) }
								help={ __(
									'Hide the overlay and text until a slide is hovered or focused. Off = always visible.',
									'swishfolio-core'
								) }
								checked={ !! revealOnHover }
								onChange={ ( value ) =>
									setAttributes( { revealOnHover: value } )
								}
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Slide Size', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<NumberControl
								label={ __( 'Width (px)', 'swishfolio-core' ) }
								value={ slideWidth }
								onChange={ ( value ) =>
									setAttributes( {
										slideWidth: parseInt( value, 10 ) || 426,
									} )
								}
								min={ 100 }
							/>
							<NumberControl
								label={ __( 'Height (px)', 'swishfolio-core' ) }
								value={ slideHeight }
								onChange={ ( value ) =>
									setAttributes( {
										slideHeight: parseInt( value, 10 ) || 262,
									} )
								}
								min={ 100 }
							/>
							<RangeControl
								label={ __( 'Gap (rem)', 'swishfolio-core' ) }
								value={ gap }
								onChange={ ( value ) =>
									setAttributes( { gap: value ?? 1 } )
								}
								min={ 0 }
								max={ 4 }
								step={ 0.25 }
							/>
						</PanelBody>
					</>
				) }

				{ activeTab === 'style' && (
					<PanelBody title={ __( 'Slide Colours', 'swishfolio-core' ) }>
						<p className="components-base-control__label">
							{ __( 'Text Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ textColor }
							onChange={ ( color ) =>
								setAttributes( { textColor: color || '' } )
							}
							clearable
						/>

						<p className="components-base-control__label" style={ { marginTop: '16px' } }>
							{ __( 'Scrim Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ scrimColor }
							onChange={ ( color ) =>
								setAttributes( { scrimColor: color || '' } )
							}
							clearable
						/>

						<RangeControl
							label={ __( 'Scrim Opacity', 'swishfolio-core' ) }
							value={ scrimOpacity }
							onChange={ ( value ) =>
								setAttributes( { scrimOpacity: value ?? 60 } )
							}
							min={ 0 }
							max={ 100 }
						/>

						<p className="components-base-control__label" style={ { marginTop: '16px' } }>
							{ __( 'CTA Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ ctaColor }
							onChange={ ( color ) =>
								setAttributes( { ctaColor: color || '' } )
							}
							clearable
						/>

						<p className="components-base-control__label" style={ { marginTop: '16px' } }>
							{ __( 'Accent Color (arrow)', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ accentColor }
							onChange={ ( color ) =>
								setAttributes( { accentColor: color || '' } )
							}
							clearable
						/>
					</PanelBody>
				) }

				{ activeTab === 'advanced' && (
					<PanelBody title={ __( 'Accessibility', 'swishfolio-core' ) }>
						<TextControl
							label={ __( 'Aria Label', 'swishfolio-core' ) }
							help={ __(
								'Announced to screen readers when entering the slider region.',
								'swishfolio-core'
							) }
							value={ ariaLabel }
							onChange={ ( value ) =>
								setAttributes( { ariaLabel: value } )
							}
						/>
					</PanelBody>
				) }
			</InspectorControls>

			<section { ...blockProps }>
				<div { ...innerBlocksProps } />
			</section>
		</>
	);
}

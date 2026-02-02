/**
 * Testimonials Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	Spinner,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const {
		displayMode,
		columns,
		postsPerPage,
		showRating,
		showCompany,
		showAvatar,
		autoplay,
		autoplaySpeed,
	} = attributes;

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
					<ToggleGroupControl
						label={ __( 'Display Mode', 'swishfolio-core' ) }
						value={ displayMode }
						onChange={ ( value ) => setAttributes( { displayMode: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="grid" label={ __( 'Grid', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="carousel" label={ __( 'Carousel', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					{ displayMode === 'grid' && (
						<RangeControl
							label={ __( 'Columns', 'swishfolio-core' ) }
							value={ columns }
							onChange={ ( value ) => setAttributes( { columns: value } ) }
							min={ 1 }
							max={ 4 }
						/>
					) }

					<RangeControl
						label={ __( 'Testimonials to Show', 'swishfolio-core' ) }
						value={ postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
						min={ 1 }
						max={ 12 }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Content', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show Rating', 'swishfolio-core' ) }
						checked={ showRating }
						onChange={ ( value ) => setAttributes( { showRating: value } ) }
					/>

					<ToggleControl
						label={ __( 'Show Avatar', 'swishfolio-core' ) }
						checked={ showAvatar }
						onChange={ ( value ) => setAttributes( { showAvatar: value } ) }
					/>

					<ToggleControl
						label={ __( 'Show Company', 'swishfolio-core' ) }
						checked={ showCompany }
						onChange={ ( value ) => setAttributes( { showCompany: value } ) }
					/>
				</PanelBody>

				{ displayMode === 'carousel' && (
					<PanelBody title={ __( 'Carousel Settings', 'swishfolio-core' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Autoplay', 'swishfolio-core' ) }
							checked={ autoplay }
							onChange={ ( value ) => setAttributes( { autoplay: value } ) }
						/>

						{ autoplay && (
							<RangeControl
								label={ __( 'Autoplay Speed (ms)', 'swishfolio-core' ) }
								value={ autoplaySpeed }
								onChange={ ( value ) => setAttributes( { autoplaySpeed: value } ) }
								min={ 2000 }
								max={ 10000 }
								step={ 500 }
							/>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="swishfolio-core/testimonials"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<div className="sfcore-testimonials__loading">
							<Spinner />
						</div>
					) }
					EmptyResponsePlaceholder={ () => (
						<div className="sfcore-testimonials__empty">
							<p>{ __( 'No testimonials found. Add some testimonials to display them here.', 'swishfolio-core' ) }</p>
						</div>
					) }
				/>
			</div>
		</>
	);
}

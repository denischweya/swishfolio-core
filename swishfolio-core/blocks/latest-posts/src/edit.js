/**
 * Latest Posts Block - Edit Component
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
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const {
		columns,
		postsPerPage,
		categories,
		showDate,
		showAuthor,
		showExcerpt,
		excerptLength,
		showFeaturedImage,
		imageAspectRatio,
	} = attributes;

	const blockProps = useBlockProps();

	// Fetch categories
	const postCategories = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords( 'taxonomy', 'category', {
			per_page: -1,
		} );
	}, [] );

	const categoryOptions = postCategories
		? postCategories.map( ( cat ) => ( {
				label: cat.name,
				value: cat.id,
		  } ) )
		: [];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
					<RangeControl
						label={ __( 'Columns', 'swishfolio-core' ) }
						value={ columns }
						onChange={ ( value ) => setAttributes( { columns: value } ) }
						min={ 1 }
						max={ 4 }
					/>

					<RangeControl
						label={ __( 'Posts to Show', 'swishfolio-core' ) }
						value={ postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
						min={ 1 }
						max={ 12 }
					/>

					<ToggleGroupControl
						label={ __( 'Image Aspect Ratio', 'swishfolio-core' ) }
						value={ imageAspectRatio }
						onChange={ ( value ) => setAttributes( { imageAspectRatio: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="square" label={ __( 'Square', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="landscape" label={ __( 'Landscape', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="portrait" label={ __( 'Portrait', 'swishfolio-core' ) } />
					</ToggleGroupControl>
				</PanelBody>

				<PanelBody title={ __( 'Content', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show Featured Image', 'swishfolio-core' ) }
						checked={ showFeaturedImage }
						onChange={ ( value ) => setAttributes( { showFeaturedImage: value } ) }
					/>

					<ToggleControl
						label={ __( 'Show Date', 'swishfolio-core' ) }
						checked={ showDate }
						onChange={ ( value ) => setAttributes( { showDate: value } ) }
					/>

					<ToggleControl
						label={ __( 'Show Author', 'swishfolio-core' ) }
						checked={ showAuthor }
						onChange={ ( value ) => setAttributes( { showAuthor: value } ) }
					/>

					<ToggleControl
						label={ __( 'Show Excerpt', 'swishfolio-core' ) }
						checked={ showExcerpt }
						onChange={ ( value ) => setAttributes( { showExcerpt: value } ) }
					/>

					{ showExcerpt && (
						<RangeControl
							label={ __( 'Excerpt Length (words)', 'swishfolio-core' ) }
							value={ excerptLength }
							onChange={ ( value ) => setAttributes( { excerptLength: value } ) }
							min={ 10 }
							max={ 55 }
						/>
					) }
				</PanelBody>

				{ categoryOptions.length > 0 && (
					<PanelBody title={ __( 'Filter by Category', 'swishfolio-core' ) } initialOpen={ false }>
						{ categoryOptions.map( ( cat ) => (
							<ToggleControl
								key={ cat.value }
								label={ cat.label }
								checked={ categories.includes( cat.value ) }
								onChange={ ( checked ) => {
									const newCategories = checked
										? [ ...categories, cat.value ]
										: categories.filter( ( id ) => id !== cat.value );
									setAttributes( { categories: newCategories } );
								} }
							/>
						) ) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="swishfolio-core/latest-posts"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<div className="sfcore-latest-posts__loading">
							<Spinner />
						</div>
					) }
					EmptyResponsePlaceholder={ () => (
						<div className="sfcore-latest-posts__empty">
							<p>{ __( 'No posts found.', 'swishfolio-core' ) }</p>
						</div>
					) }
				/>
			</div>
		</>
	);
}

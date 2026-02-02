/**
 * Portfolio Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	SelectControl,
	TextControl,
	Spinner,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const {
		layout,
		cardStyle,
		columns,
		gap,
		postsPerPage,
		categories,
		projectTypes,
		orderBy,
		order,
		showFilters,
		filterTaxonomy,
		showExcerpt,
		showCategory,
		showProjectType,
		showStatus,
		showCompletionDate,
		showSkills,
		showClient,
		showProjectUrl,
		imageAspectRatio,
		hoverEffect,
		showViewButton,
		viewButtonText,
	} = attributes;

	const blockProps = useBlockProps();

	// Fetch project categories
	const projectCategories = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords( 'taxonomy', 'sfcore_project_cat', {
			per_page: -1,
		} );
	}, [] );

	// Fetch project types
	const projectTypeTerms = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords( 'taxonomy', 'sfcore_project_type', {
			per_page: -1,
		} );
	}, [] );

	const categoryOptions = projectCategories
		? projectCategories.map( ( cat ) => ( {
				label: cat.name,
				value: cat.id,
		  } ) )
		: [];

	const projectTypeOptions = projectTypeTerms
		? projectTypeTerms.map( ( type ) => ( {
				label: type.name,
				value: type.id,
		  } ) )
		: [];

	const showColumnsControl = layout !== 'list';

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
					<ToggleGroupControl
						label={ __( 'Layout Style', 'swishfolio-core' ) }
						value={ layout }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="grid" label={ __( 'Grid', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="masonry" label={ __( 'Masonry', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="list" label={ __( 'List', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Card Style', 'swishfolio-core' ) }
						value={ cardStyle }
						onChange={ ( value ) => setAttributes( { cardStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="default" label={ __( 'Default', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="minimal" label={ __( 'Minimal', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="overlay" label={ __( 'Overlay', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="stacked" label={ __( 'Stacked', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					{ showColumnsControl && (
						<RangeControl
							label={ __( 'Columns', 'swishfolio-core' ) }
							value={ columns }
							onChange={ ( value ) => setAttributes( { columns: value } ) }
							min={ 1 }
							max={ 4 }
						/>
					) }

					<RangeControl
						label={ __( 'Gap (px)', 'swishfolio-core' ) }
						value={ gap }
						onChange={ ( value ) => setAttributes( { gap: value } ) }
						min={ 8 }
						max={ 64 }
						step={ 4 }
					/>

					<RangeControl
						label={ __( 'Projects to Show', 'swishfolio-core' ) }
						value={ postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
						min={ 1 }
						max={ 24 }
					/>

					<ToggleGroupControl
						label={ __( 'Image Aspect Ratio', 'swishfolio-core' ) }
						value={ imageAspectRatio }
						onChange={ ( value ) => setAttributes( { imageAspectRatio: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="square" label={ __( '1:1', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="landscape" label={ __( '4:3', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="wide" label={ __( '16:9', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="portrait" label={ __( '3:4', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Hover Effect', 'swishfolio-core' ) }
						value={ hoverEffect }
						onChange={ ( value ) => setAttributes( { hoverEffect: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="lift" label={ __( 'Lift', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="zoom" label={ __( 'Zoom', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="reveal" label={ __( 'Reveal', 'swishfolio-core' ) } />
					</ToggleGroupControl>
				</PanelBody>

				<PanelBody title={ __( 'Query', 'swishfolio-core' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Order By', 'swishfolio-core' ) }
						value={ orderBy }
						options={ [
							{ label: __( 'Date', 'swishfolio-core' ), value: 'date' },
							{ label: __( 'Title', 'swishfolio-core' ), value: 'title' },
							{ label: __( 'Modified', 'swishfolio-core' ), value: 'modified' },
							{ label: __( 'Random', 'swishfolio-core' ), value: 'rand' },
							{ label: __( 'Menu Order', 'swishfolio-core' ), value: 'menu_order' },
						] }
						onChange={ ( value ) => setAttributes( { orderBy: value } ) }
					/>

					<ToggleGroupControl
						label={ __( 'Order', 'swishfolio-core' ) }
						value={ order }
						onChange={ ( value ) => setAttributes( { order: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="DESC" label={ __( 'Descending', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="ASC" label={ __( 'Ascending', 'swishfolio-core' ) } />
					</ToggleGroupControl>
				</PanelBody>

				<PanelBody title={ __( 'Filters', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show Filter Buttons', 'swishfolio-core' ) }
						checked={ showFilters }
						onChange={ ( value ) => setAttributes( { showFilters: value } ) }
					/>

					{ showFilters && (
						<ToggleGroupControl
							label={ __( 'Filter By', 'swishfolio-core' ) }
							value={ filterTaxonomy }
							onChange={ ( value ) => setAttributes( { filterTaxonomy: value } ) }
							isBlock
						>
							<ToggleGroupControlOption value="category" label={ __( 'Category', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="type" label={ __( 'Type', 'swishfolio-core' ) } />
						</ToggleGroupControl>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Display Options', 'swishfolio-core' ) } initialOpen={ false }>
					<p className="components-base-control__label" style={ { fontWeight: 600, marginBottom: '8px' } }>
						{ __( 'Taxonomies', 'swishfolio-core' ) }
					</p>
					<ToggleControl
						label={ __( 'Show Category', 'swishfolio-core' ) }
						checked={ showCategory }
						onChange={ ( value ) => setAttributes( { showCategory: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Project Type', 'swishfolio-core' ) }
						checked={ showProjectType }
						onChange={ ( value ) => setAttributes( { showProjectType: value } ) }
					/>

					<hr style={ { margin: '16px 0' } } />

					<p className="components-base-control__label" style={ { fontWeight: 600, marginBottom: '8px' } }>
						{ __( 'Content', 'swishfolio-core' ) }
					</p>
					<ToggleControl
						label={ __( 'Show Excerpt', 'swishfolio-core' ) }
						checked={ showExcerpt }
						onChange={ ( value ) => setAttributes( { showExcerpt: value } ) }
					/>

					<hr style={ { margin: '16px 0' } } />

					<p className="components-base-control__label" style={ { fontWeight: 600, marginBottom: '8px' } }>
						{ __( 'Project Details', 'swishfolio-core' ) }
					</p>
					<ToggleControl
						label={ __( 'Show Status', 'swishfolio-core' ) }
						checked={ showStatus }
						onChange={ ( value ) => setAttributes( { showStatus: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Completion Date', 'swishfolio-core' ) }
						checked={ showCompletionDate }
						onChange={ ( value ) => setAttributes( { showCompletionDate: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Skills', 'swishfolio-core' ) }
						checked={ showSkills }
						onChange={ ( value ) => setAttributes( { showSkills: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Client', 'swishfolio-core' ) }
						checked={ showClient }
						onChange={ ( value ) => setAttributes( { showClient: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Project URL', 'swishfolio-core' ) }
						checked={ showProjectUrl }
						onChange={ ( value ) => setAttributes( { showProjectUrl: value } ) }
					/>

					<hr style={ { margin: '16px 0' } } />

					<p className="components-base-control__label" style={ { fontWeight: 600, marginBottom: '8px' } }>
						{ __( 'Button', 'swishfolio-core' ) }
					</p>
					<ToggleControl
						label={ __( 'Show View Button', 'swishfolio-core' ) }
						checked={ showViewButton }
						onChange={ ( value ) => setAttributes( { showViewButton: value } ) }
					/>
					{ showViewButton && (
						<TextControl
							label={ __( 'Button Text', 'swishfolio-core' ) }
							value={ viewButtonText }
							onChange={ ( value ) => setAttributes( { viewButtonText: value } ) }
						/>
					) }
				</PanelBody>

				{ categoryOptions.length > 0 && (
					<PanelBody title={ __( 'Filter by Category', 'swishfolio-core' ) } initialOpen={ false }>
						<p className="components-base-control__help" style={ { marginTop: 0, marginBottom: '12px' } }>
							{ __( 'Select categories to display. Leave empty to show all.', 'swishfolio-core' ) }
						</p>
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

				{ projectTypeOptions.length > 0 && (
					<PanelBody title={ __( 'Filter by Project Type', 'swishfolio-core' ) } initialOpen={ false }>
						<p className="components-base-control__help" style={ { marginTop: 0, marginBottom: '12px' } }>
							{ __( 'Select project types to display. Leave empty to show all.', 'swishfolio-core' ) }
						</p>
						{ projectTypeOptions.map( ( type ) => (
							<ToggleControl
								key={ type.value }
								label={ type.label }
								checked={ projectTypes.includes( type.value ) }
								onChange={ ( checked ) => {
									const newTypes = checked
										? [ ...projectTypes, type.value ]
										: projectTypes.filter( ( id ) => id !== type.value );
									setAttributes( { projectTypes: newTypes } );
								} }
							/>
						) ) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="swishfolio-core/portfolio"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<div className="sfcore-portfolio__loading">
							<Spinner />
						</div>
					) }
					EmptyResponsePlaceholder={ () => (
						<div className="sfcore-portfolio__empty">
							<p>{ __( 'No projects found. Add some projects to display them here.', 'swishfolio-core' ) }</p>
						</div>
					) }
				/>
			</div>
		</>
	);
}

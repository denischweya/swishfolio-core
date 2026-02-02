/**
 * Project Sidebar Plugin
 *
 * Adds a custom sidebar panel for editing project meta fields in the block editor.
 */

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	TextControl,
	SelectControl,
	TextareaControl,
	DatePicker,
	Dropdown,
	Button,
} from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { format } from '@wordpress/date';

const STATUS_OPTIONS = [
	{ label: __( 'In Progress', 'swishfolio-core' ), value: 'in-progress' },
	{ label: __( 'Completed', 'swishfolio-core' ), value: 'completed' },
	{ label: __( 'Planned', 'swishfolio-core' ), value: 'planned' },
	{ label: __( 'On Hold', 'swishfolio-core' ), value: 'on-hold' },
];

const ProjectSidebarPanel = () => {
	const postType = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPostType();
	}, [] );

	// Only render for project post type.
	if ( postType !== 'sfcore_project' ) {
		return null;
	}

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const completionDate = meta?._sfcore_completion_date || '';
	const projectStatus = meta?._sfcore_project_status || 'in-progress';
	const skills = meta?._sfcore_skills || [];
	const projectUrl = meta?._sfcore_project_url || '';
	const clientName = meta?._sfcore_client_name || '';

	const updateMeta = ( key, value ) => {
		setMeta( { ...meta, [ key ]: value } );
	};

	const skillsString = Array.isArray( skills ) ? skills.join( ', ' ) : '';

	const handleSkillsChange = ( value ) => {
		const skillsArray = value
			.split( ',' )
			.map( ( s ) => s.trim() )
			.filter( ( s ) => s.length > 0 );
		updateMeta( '_sfcore_skills', skillsArray );
	};

	const formatDateForDisplay = ( dateString ) => {
		if ( ! dateString ) {
			return __( 'Select date', 'swishfolio-core' );
		}
		try {
			return format( 'F j, Y', dateString );
		} catch ( e ) {
			return dateString;
		}
	};

	return (
		<PluginDocumentSettingPanel
			name="sfcore-project-details"
			title={ __( 'Project Details', 'swishfolio-core' ) }
			className="sfcore-project-sidebar"
		>
			<SelectControl
				label={ __( 'Status', 'swishfolio-core' ) }
				value={ projectStatus }
				options={ STATUS_OPTIONS }
				onChange={ ( value ) => updateMeta( '_sfcore_project_status', value ) }
			/>

			<div className="sfcore-date-field">
				<label className="components-base-control__label">
					{ __( 'Completion Date', 'swishfolio-core' ) }
				</label>
				<Dropdown
					popoverProps={ { placement: 'bottom-start' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							variant="secondary"
							onClick={ onToggle }
							aria-expanded={ isOpen }
							style={ { width: '100%', justifyContent: 'flex-start' } }
						>
							{ formatDateForDisplay( completionDate ) }
						</Button>
					) }
					renderContent={ ( { onClose } ) => (
						<div style={ { padding: '8px' } }>
							<DatePicker
								currentDate={ completionDate || undefined }
								onChange={ ( date ) => {
									const formatted = date ? date.split( 'T' )[ 0 ] : '';
									updateMeta( '_sfcore_completion_date', formatted );
									onClose();
								} }
							/>
							{ completionDate && (
								<Button
									variant="link"
									isDestructive
									onClick={ () => {
										updateMeta( '_sfcore_completion_date', '' );
										onClose();
									} }
									style={ { marginTop: '8px' } }
								>
									{ __( 'Clear date', 'swishfolio-core' ) }
								</Button>
							) }
						</div>
					) }
				/>
			</div>

			<TextControl
				label={ __( 'Client Name', 'swishfolio-core' ) }
				value={ clientName }
				onChange={ ( value ) => updateMeta( '_sfcore_client_name', value ) }
			/>

			<TextControl
				label={ __( 'Project URL', 'swishfolio-core' ) }
				value={ projectUrl }
				onChange={ ( value ) => updateMeta( '_sfcore_project_url', value ) }
				type="url"
				placeholder="https://"
			/>

			<TextareaControl
				label={ __( 'Skills / Technologies', 'swishfolio-core' ) }
				value={ skillsString }
				onChange={ handleSkillsChange }
				help={ __( 'Comma-separated list (e.g., React, PHP, WordPress)', 'swishfolio-core' ) }
				rows={ 3 }
			/>
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'sfcore-project-sidebar', {
	render: ProjectSidebarPanel,
	icon: 'portfolio',
} );

/**
 * Testimonial Card Block - Edit Component.
 *
 * Inline-editable quote, name, and role. Click the avatar circle to open
 * the media picker; falls back to the first letter of `name` when no image
 * is uploaded.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	useSetting,
	URLInput,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ColorPalette,
	Button,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const getInitial = ( name ) => {
	if ( ! name ) {
		return '';
	}
	// Strip RichText HTML tags before taking the first letter.
	const text = name.replace( /<[^>]+>/g, '' ).trim();
	return text ? text.charAt( 0 ).toUpperCase() : '';
};

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs( 'style' );
	const themeColors = useSetting( 'color.palette' ) || [];

	const {
		quote,
		name,
		role,
		roleUrl,
		avatarImageId,
		avatarImageUrl,
		avatarColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'sfcore-testimonial-card',
		style: avatarColor ? { '--sf-avatar-color': avatarColor } : undefined,
	} );

	const onSelectImage = ( media ) => {
		setAttributes( {
			avatarImageId: media.id,
			avatarImageUrl: media.url,
		} );
	};

	const onRemoveImage = () => {
		setAttributes( {
			avatarImageId: 0,
			avatarImageUrl: '',
		} );
	};

	const initial = getInitial( name );

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
					allowedTabs={ [ 'style', 'advanced' ] }
				/>

				{ activeTab === 'style' && (
					<PanelBody title={ __( 'Card', 'swishfolio-core' ) }>
						<URLInput
							label={ __( 'Role URL', 'swishfolio-core' ) }
							value={ roleUrl }
							onChange={ ( url ) =>
								setAttributes( { roleUrl: url } )
							}
							className="sfcore-testimonial-card__role-url-input"
						/>

						{ ! avatarImageUrl && (
							<>
								<p className="components-base-control__label">
									{ __( 'Avatar Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ themeColors }
									value={ avatarColor }
									onChange={ ( color ) =>
										setAttributes( {
											avatarColor: color || '',
										} )
									}
									clearable
								/>
							</>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			<figure { ...blockProps }>
				<div
					className="sfcore-testimonial-card__quote-mark"
					aria-hidden="true"
				>
					&ldquo;
				</div>

				<RichText
					tagName="p"
					className="sfcore-testimonial-card__quote"
					value={ quote }
					onChange={ ( value ) =>
						setAttributes( { quote: value } )
					}
					placeholder={ __(
						'Add testimonial…',
						'swishfolio-core'
					) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>

				<figcaption className="sfcore-testimonial-card__attribution">
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ onSelectImage }
							allowedTypes={ [ 'image' ] }
							value={ avatarImageId }
							render={ ( { open } ) => (
								<Button
									className="sfcore-testimonial-card__avatar"
									onClick={ open }
									aria-label={ __(
										'Set avatar image',
										'swishfolio-core'
									) }
									style={
										avatarColor
											? {
													backgroundColor:
														avatarColor,
											  }
											: undefined
									}
								>
									{ avatarImageUrl ? (
										<img
											src={ avatarImageUrl }
											alt=""
										/>
									) : (
										<span>{ initial }</span>
									) }
								</Button>
							) }
						/>
					</MediaUploadCheck>

					{ avatarImageUrl && (
						<Button
							isSmall
							variant="link"
							onClick={ onRemoveImage }
							className="sfcore-testimonial-card__avatar-remove"
						>
							{ __( 'Remove', 'swishfolio-core' ) }
						</Button>
					) }

					<div className="sfcore-testimonial-card__meta">
						<RichText
							tagName="div"
							className="sfcore-testimonial-card__name"
							value={ name }
							onChange={ ( value ) =>
								setAttributes( { name: value } )
							}
							placeholder={ __( 'Name', 'swishfolio-core' ) }
							allowedFormats={ [] }
						/>
						<RichText
							tagName="div"
							className="sfcore-testimonial-card__role"
							value={ role }
							onChange={ ( value ) =>
								setAttributes( { role: value } )
							}
							placeholder={ __(
								'Role, Company',
								'swishfolio-core'
							) }
							allowedFormats={ [] }
						/>
					</div>
				</figcaption>
			</figure>
		</>
	);
}

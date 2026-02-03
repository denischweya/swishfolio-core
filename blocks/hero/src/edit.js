/**
 * Hero Block - Edit Component
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
	RangeControl,
	ColorPalette,
	Button,
	ButtonGroup,
	ToggleControl,
	TextControl,
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
	arrowUp,
	arrowDown,
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
	external,
	download,
	upload,
	video,
	image,
} from '@wordpress/icons';

// Bauhaus colors
const BAUHAUS_COLORS = [
	{ name: 'Primary Red', color: '#D02020' },
	{ name: 'Primary Blue', color: '#1040C0' },
	{ name: 'Primary Yellow', color: '#F0C020' },
	{ name: 'Contrast', color: '#121212' },
	{ name: 'Base', color: '#F0F0F0' },
	{ name: 'White', color: '#FFFFFF' },
];

// Icon library for CTA buttons (same pattern as heading block)
const CTA_ICON_LIBRARY = {
	'arrow-right': arrowRight,
	'arrow-left': arrowLeft,
	'arrow-up': arrowUp,
	'arrow-down': arrowDown,
	'chevron-right': chevronRight,
	'chevron-left': chevronLeft,
	external,
	download,
	upload,
	video,
	image,
	star: starFilled,
	'star-empty': starEmpty,
	check,
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
};

// Blob shape presets - organic SVG paths
const BLOB_PRESETS = {
	blob1: 'M44.5,-76.3C58.1,-69.1,70,-57.5,77.4,-43.6C84.8,-29.7,87.8,-13.4,86.1,2.1C84.4,17.5,78,32.3,68.4,44.3C58.8,56.3,46,65.5,32,72.1C18,78.7,2.8,82.7,-12.2,81.3C-27.2,79.9,-42,73.1,-54.3,63.1C-66.6,53.1,-76.4,39.9,-81.1,25C-85.8,10.1,-85.4,-6.5,-80.4,-21.1C-75.4,-35.7,-65.8,-48.3,-53.4,-56.1C-41,-63.9,-25.8,-66.9,-10.8,-71.5C4.2,-76.1,30.9,-83.5,44.5,-76.3Z',
	blob2: 'M40.9,-69.5C53.4,-63.1,64.4,-53,72,-40.6C79.6,-28.2,83.8,-13.5,83.3,0.8C82.8,15.1,77.6,29,69.1,40.5C60.6,52,48.8,61.1,35.8,67.1C22.8,73.1,8.6,76,-6.4,76.2C-21.4,76.4,-37.2,73.9,-49.4,66.3C-61.6,58.7,-70.2,46,-75.3,32C-80.4,18,-82,-0.3,-78.4,-17C-74.8,-33.7,-66,-48.8,-53.8,-55.5C-41.6,-62.2,-26,-60.5,-12,-63.5C2,-66.5,28.4,-75.9,40.9,-69.5Z',
	blob3: 'M38.6,-65C50.5,-58.5,61.2,-49,69.2,-37.1C77.2,-25.2,82.5,-10.8,81.6,3C80.7,16.8,73.6,30.2,64.4,41.3C55.2,52.4,43.9,61.2,31.2,67.5C18.5,73.8,4.4,77.6,-10.8,77.6C-26,77.6,-42.3,73.8,-54.5,64.8C-66.7,55.8,-74.8,41.6,-79.2,26.2C-83.6,10.8,-84.3,-5.8,-79.7,-20.5C-75.1,-35.2,-65.2,-48,-52.5,-54.8C-39.8,-61.6,-24.3,-62.4,-10.4,-63.9C3.5,-65.4,26.7,-71.5,38.6,-65Z',
	blob4: 'M42.1,-72C55.2,-65.1,67,-54.8,74.7,-41.9C82.4,-29,86,-13.5,84.6,1.2C83.2,15.9,76.8,29.8,67.8,41.4C58.8,53,47.2,62.3,34.2,68.9C21.2,75.5,6.8,79.4,-7.8,78.8C-22.4,78.2,-37.2,73.1,-49.1,64.3C-61,55.5,-70,43,-75.1,29C-80.2,15,-81.4,-0.5,-77.7,-14.5C-74,-28.5,-65.4,-41,-54,-49.5C-42.6,-58,-28.4,-62.5,-14.8,-68.3C-1.2,-74.1,29,-78.9,42.1,-72Z',
	blob5: 'M45.3,-77.8C59.2,-70.7,71.6,-59.8,79.3,-46.2C87,-32.6,90,-16.3,88.4,-0.9C86.8,14.5,80.6,29,71.7,41.3C62.8,53.6,51.2,63.7,38,70.5C24.8,77.3,10,80.8,-4.5,80.1C-19,79.4,-33.2,74.5,-45.3,66.5C-57.4,58.5,-67.4,47.4,-74,34.3C-80.6,21.2,-83.8,6.1,-82.1,-8.2C-80.4,-22.5,-73.8,-36,-63.8,-46.4C-53.8,-56.8,-40.4,-64.1,-27,-71.5C-13.6,-78.9,0.2,-86.4,13.2,-85.1C26.2,-83.8,31.4,-84.9,45.3,-77.8Z',
	blob6: 'M39.5,-67.4C52.3,-60.3,64.8,-51.6,72.6,-39.7C80.4,-27.8,83.5,-12.7,82.1,1.6C80.7,15.9,74.8,29.4,66.2,40.8C57.6,52.2,46.3,61.5,33.6,68.2C20.9,74.9,6.8,79,-8.4,78.7C-23.6,78.4,-39.9,73.7,-52.6,64.7C-65.3,55.7,-74.4,42.4,-79.2,27.6C-84,12.8,-84.5,-3.5,-80,-17.8C-75.5,-32.1,-66,-44.4,-53.9,-51.8C-41.8,-59.2,-27.1,-61.7,-13.5,-66.5C0.1,-71.3,26.7,-74.5,39.5,-67.4Z',
};

// Social media icons
const SOCIAL_ICONS = {
	facebook: {
		label: 'Facebook',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
	},
	twitter: {
		label: 'X (Twitter)',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
	},
	instagram: {
		label: 'Instagram',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
	},
	linkedin: {
		label: 'LinkedIn',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
	},
	github: {
		label: 'GitHub',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>',
	},
	youtube: {
		label: 'YouTube',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
	},
	tiktok: {
		label: 'TikTok',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
	},
	dribbble: {
		label: 'Dribbble',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 24C5.385 24 0 18.615 0 12S5.385 0 12 0s12 5.385 12 12-5.385 12-12 12zm10.12-10.358c-.35-.11-3.17-.953-6.384-.438 1.34 3.684 1.887 6.684 1.992 7.308 2.3-1.555 3.936-4.02 4.395-6.87zm-6.115 7.808c-.153-.9-.75-4.032-2.19-7.77l-.066.02c-5.79 2.015-7.86 6.025-8.04 6.4 1.73 1.358 3.92 2.166 6.29 2.166 1.42 0 2.77-.29 4-.814zm-11.62-2.58c.232-.4 3.045-5.055 8.332-6.765.135-.045.27-.084.405-.12-.26-.585-.54-1.167-.832-1.74C7.17 11.775 2.206 11.71 1.756 11.7l-.004.312c0 2.633.998 5.037 2.634 6.855zm-2.42-8.955c.46.008 4.683.026 9.477-1.248-1.698-3.018-3.53-5.558-3.8-5.928-2.868 1.35-5.01 3.99-5.676 7.17zM9.6 2.052c.282.38 2.145 2.914 3.822 6 3.645-1.365 5.19-3.44 5.373-3.702-1.81-1.61-4.19-2.586-6.795-2.586-.825 0-1.63.1-2.4.285zm10.335 3.483c-.218.29-1.935 2.493-5.724 4.04.24.49.47.985.68 1.486.08.18.15.36.22.53 3.41-.43 6.8.26 7.14.33-.02-2.42-.88-4.64-2.31-6.38z"/></svg>',
	},
	behance: {
		label: 'Behance',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.938 4.503c.702 0 1.34.06 1.92.188.577.13 1.07.33 1.485.61.41.28.733.65.96 1.12.225.47.34 1.05.34 1.73 0 .74-.17 1.36-.507 1.86-.338.5-.837.9-1.502 1.22.906.26 1.576.72 2.022 1.37.448.66.665 1.45.665 2.36 0 .75-.13 1.39-.41 1.93-.28.55-.67 1-1.16 1.35-.48.348-1.05.6-1.67.767-.61.165-1.252.254-1.91.254H0V4.51h6.938v-.007zM6.545 9.66c.6 0 1.086-.16 1.467-.477.383-.316.572-.79.572-1.42 0-.36-.06-.66-.185-.9-.124-.24-.294-.43-.508-.57-.214-.14-.47-.24-.754-.3-.285-.07-.592-.1-.918-.1H3.262v3.76h3.283v.01zm.2 5.69c.364 0 .7-.04 1.01-.12.306-.08.574-.21.8-.38.227-.17.406-.4.54-.68.133-.28.2-.62.2-1.02 0-.8-.225-1.37-.675-1.715-.45-.34-1.047-.51-1.786-.51H3.262v4.43h3.483v-.01zM21.754 16.06c-.4.49-.9.83-1.5 1.03-.604.2-1.25.3-1.938.3-.69 0-1.33-.1-1.92-.31-.59-.21-1.1-.52-1.53-.94-.43-.42-.76-.94-.99-1.56-.23-.62-.35-1.34-.35-2.16 0-.8.12-1.51.36-2.14.24-.63.56-1.16.97-1.59.41-.43.9-.75 1.46-.97.56-.22 1.17-.33 1.83-.33.78 0 1.46.15 2.03.44.57.29 1.04.69 1.41 1.2.37.51.64 1.11.81 1.79.17.68.23 1.42.19 2.21h-7.04c0 .86.27 1.55.79 2.06.52.51 1.21.76 2.08.76.63 0 1.16-.16 1.59-.47.43-.31.73-.68.9-1.1l1.84.69zm-1.67-5.21c-.05-.39-.15-.74-.3-1.04-.15-.3-.35-.55-.58-.75-.24-.2-.51-.35-.82-.45-.31-.1-.65-.15-1.02-.15-.74 0-1.35.23-1.82.69-.47.46-.76 1.04-.87 1.74h5.41v-.04zM15.5 4.8h5v1.58h-5V4.8z"/></svg>',
	},
	pinterest: {
		label: 'Pinterest',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/></svg>',
	},
	custom: {
		label: 'Custom Icon',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>',
	},
};

// Helper: gradient mask style based on fade side
const getGradientMaskStyle = ( side ) => {
	if ( side === 'none' ) {
		return {};
	}
	const directions = {
		top: 'to top',
		bottom: 'to bottom',
		left: 'to left',
		right: 'to right',
	};
	return {
		WebkitMaskImage: `linear-gradient(${ directions[ side ] }, black 30%, transparent 100%)`,
		maskImage: `linear-gradient(${ directions[ side ] }, black 30%, transparent 100%)`,
	};
};

// Helper: render a blob SVG preview
const renderBlobSvg = ( blobKey, color, size = 60 ) => {
	const pathData = BLOB_PRESETS[ blobKey ];
	if ( ! pathData ) {
		return null;
	}
	return (
		<svg viewBox="0 0 200 200" width={ size } height={ size } xmlns="http://www.w3.org/2000/svg">
			<g transform="translate(100,100)">
				<path d={ pathData } fill={ color || '#ccc' } />
			</g>
		</svg>
	);
};

export default function Edit( { attributes, setAttributes } ) {
	const {
		layout,
		heading,
		subheading,
		headingStyle,
		headingSize,
		headingTransform,
		backgroundType,
		backgroundColor,
		backgroundImage,
		overlayColor,
		overlayOpacity,
		heroImage,
		imageBorderWidth,
		imageBorderColor,
		imageShadowSize,
		imageRotation,
		showImageDecoShapes,
		ctaText,
		ctaUrl,
		ctaStyle,
		secondaryCtaText,
		secondaryCtaUrl,
		minHeight,
		contentAlign,
		verticalAlign,
		textColor,
		decorativeShape,
		decorativeColor,
		socialLinks,
		headingFullWidth,
		headingOverlayImage,
		// Background shapes
		bgShape1Type, bgShape1CustomSvg, bgShape1Color, bgShape1GradientSide, bgShape1Size, bgShape1Position, bgShape1Opacity,
		bgShape2Type, bgShape2CustomSvg, bgShape2Color, bgShape2GradientSide, bgShape2Size, bgShape2Position, bgShape2Opacity,
		bgShape3Type, bgShape3CustomSvg, bgShape3Color, bgShape3GradientSide, bgShape3Size, bgShape3Position, bgShape3Opacity,
		// Image blobs
		imageBlob1Shape, imageBlob1Color, imageBlob1Size, imageBlob1Rotation,
		imageBlob2Shape, imageBlob2Color, imageBlob2Size, imageBlob2Rotation,
		// CTA icons
		ctaIcon, ctaIconPosition,
		secondaryCtaIcon, secondaryCtaIconPosition,
	} = attributes;

	const isSplitLayout = layout === 'split-right' || layout === 'split-left';

	const getBackgroundStyle = () => {
		if ( backgroundType === 'image' && backgroundImage?.url ) {
			return {
				backgroundImage: `url(${ backgroundImage.url })`,
				backgroundSize: 'cover',
				backgroundPosition: 'center',
			};
		}
		return {
			backgroundColor: backgroundColor || '#1040C0',
		};
	};

	const getHeadingClasses = () => {
		const classes = [ 'sfcore-hero__heading' ];
		classes.push( `sfcore-hero__heading--${ headingStyle }` );
		classes.push( `sfcore-hero__heading--${ headingSize }` );
		if ( headingTransform !== 'none' ) {
			classes.push( `sfcore-hero__heading--${ headingTransform }` );
		}
		if ( headingFullWidth ) {
			classes.push( 'sfcore-hero__heading--full-width' );
		}
		if ( headingOverlayImage && isSplitLayout ) {
			classes.push( 'sfcore-hero__heading--overlay' );
		}
		return classes.join( ' ' );
	};

	// Social link management functions
	const addSocialLink = () => {
		const newLinks = [ ...socialLinks, { icon: 'facebook', url: '', customIcon: null } ];
		setAttributes( { socialLinks: newLinks } );
	};

	const updateSocialLink = ( index, field, value ) => {
		const newLinks = [ ...socialLinks ];
		newLinks[ index ] = { ...newLinks[ index ], [ field ]: value };
		setAttributes( { socialLinks: newLinks } );
	};

	const removeSocialLink = ( index ) => {
		const newLinks = socialLinks.filter( ( _, i ) => i !== index );
		setAttributes( { socialLinks: newLinks } );
	};

	const renderSocialIcon = ( iconKey ) => {
		const icon = SOCIAL_ICONS[ iconKey ];
		if ( ! icon ) return null;
		return (
			<span
				className="sfcore-hero__social-icon"
				dangerouslySetInnerHTML={ { __html: icon.svg } }
			/>
		);
	};

	const getImageShadow = () => {
		const shadows = {
			none: 'none',
			small: '4px 4px 0 #121212',
			medium: '8px 8px 0 #121212',
			large: '12px 12px 0 #121212',
			xlarge: '16px 16px 0 #121212',
		};
		return shadows[ imageShadowSize ] || shadows.large;
	};

	// Helper to render a CTA icon in the editor preview
	const renderCtaIcon = ( iconObj ) => {
		if ( ! iconObj || iconObj.type === 'none' ) {
			return null;
		}
		if ( iconObj.type === 'auto' ) {
			return (
				<span className="sfcore-hero__cta-icon">
					<Icon icon={ external } size={ 16 } />
				</span>
			);
		}
		if ( iconObj.type === 'library' && CTA_ICON_LIBRARY[ iconObj.value ] ) {
			return (
				<span className="sfcore-hero__cta-icon">
					<Icon icon={ CTA_ICON_LIBRARY[ iconObj.value ] } size={ 16 } />
				</span>
			);
		}
		if ( iconObj.type === 'custom' && iconObj.value ) {
			return (
				<span className="sfcore-hero__cta-icon">
					<img src={ iconObj.value } alt="" className="sfcore-hero__cta-icon-img" />
				</span>
			);
		}
		return null;
	};

	const blockProps = useBlockProps( {
		className: `sfcore-hero sfcore-hero--layout-${ layout } sfcore-hero--align-${ contentAlign } sfcore-hero--valign-${ verticalAlign }${ headingOverlayImage && isSplitLayout ? ' sfcore-hero--heading-overlay' : '' }`,
		style: {
			...getBackgroundStyle(),
			minHeight: `${ minHeight }px`,
			color: textColor,
		},
	} );

	const renderOverlay = () => {
		if ( backgroundType !== 'image' || ! backgroundImage?.url ) {
			return null;
		}
		return (
			<div
				className="sfcore-hero__overlay"
				style={ {
					backgroundColor: overlayColor,
					opacity: overlayOpacity / 100,
				} }
				aria-hidden="true"
			/>
		);
	};

	const renderDecorativeShape = () => {
		if ( decorativeShape === 'none' || isSplitLayout ) {
			return null;
		}
		return (
			<div
				className={ `sfcore-hero__shape sfcore-hero__shape--${ decorativeShape }` }
				style={ { backgroundColor: decorativeColor } }
				aria-hidden="true"
			/>
		);
	};

	// Render a single background shape
	const renderBgShape = ( type, customSvg, color, gradientSide, size, position, opacity ) => {
		if ( type === 'none' ) {
			return null;
		}

		const shapeClasses = `sfcore-hero__bg-shape sfcore-hero__bg-shape--${ size } sfcore-hero__bg-shape--${ position }`;
		const shapeStyle = {
			opacity: opacity / 100,
			...getGradientMaskStyle( gradientSide ),
		};

		if ( type === 'custom' && customSvg?.url ) {
			return (
				<div className={ shapeClasses } style={ shapeStyle } aria-hidden="true">
					<img src={ customSvg.url } alt="" className="sfcore-hero__bg-shape-img" />
				</div>
			);
		}

		// Built-in shape
		return (
			<div
				className={ `${ shapeClasses } sfcore-hero__bg-shape--${ type }` }
				style={ { ...shapeStyle, backgroundColor: color } }
				aria-hidden="true"
			/>
		);
	};

	// Render blob SVG inside image container
	const renderImageBlob = ( blobShape, blobColor, blobSize, blobRotation, position ) => {
		if ( blobShape === 'none' || ! BLOB_PRESETS[ blobShape ] ) {
			return null;
		}

		const sizeMap = { small: 120, medium: 180, large: 260 };
		const svgSize = sizeMap[ blobSize ] || sizeMap.medium;

		return (
			<div
				className={ `sfcore-hero__blob sfcore-hero__blob--${ position }` }
				style={ { transform: `rotate(${ blobRotation }deg)` } }
				aria-hidden="true"
			>
				<svg viewBox="0 0 200 200" width={ svgSize } height={ svgSize } xmlns="http://www.w3.org/2000/svg">
					<g transform="translate(100,100)">
						<path d={ BLOB_PRESETS[ blobShape ] } fill={ blobColor } />
					</g>
				</svg>
			</div>
		);
	};

	const renderHeroImage = () => {
		if ( ! isSplitLayout ) {
			return null;
		}

		return (
			<div className="sfcore-hero__image-container">
				{ showImageDecoShapes && (
					<>
						{ renderImageBlob( imageBlob1Shape, imageBlob1Color, imageBlob1Size, imageBlob1Rotation, '1' ) }
						{ renderImageBlob( imageBlob2Shape, imageBlob2Color, imageBlob2Size, imageBlob2Rotation, '2' ) }
					</>
				) }
				{ heroImage?.url ? (
					<div
						className="sfcore-hero__image-wrapper"
						style={ {
							borderWidth: `${ imageBorderWidth }px`,
							borderColor: imageBorderColor,
							boxShadow: getImageShadow(),
							transform: `rotate(${ imageRotation }deg)`,
						} }
					>
						<img
							src={ heroImage.url }
							alt={ heroImage.alt || '' }
							className="sfcore-hero__image"
						/>
					</div>
				) : (
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) =>
								setAttributes( {
									heroImage: {
										url: media.url,
										alt: media.alt,
										id: media.id,
									},
								} )
							}
							allowedTypes={ [ 'image' ] }
							render={ ( { open } ) => (
								<div
									className="sfcore-hero__image-placeholder"
									onClick={ open }
									onKeyDown={ ( e ) => e.key === 'Enter' && open() }
									role="button"
									tabIndex={ 0 }
								>
									<span>{ __( 'Click to add image', 'swishfolio-core' ) }</span>
								</div>
							) }
						/>
					</MediaUploadCheck>
				) }
			</div>
		);
	};

	// Background Shape Inspector Panel
	const BgShapePanel = ( { index, type, customSvg, color, gradientSide, size, position, opacity } ) => {
		const prefix = `bgShape${ index }`;
		return (
			<div style={ { padding: '12px', marginBottom: '12px', border: '1px solid #ddd', borderRadius: '4px', background: '#f9f9f9' } }>
				<p style={ { fontWeight: 600, fontSize: '13px', marginBottom: '8px', marginTop: 0 } }>
					{ __( 'Shape', 'swishfolio-core' ) } { index }
				</p>

				<SelectControl
					label={ __( 'Type', 'swishfolio-core' ) }
					value={ type }
					options={ [
						{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
						{ label: __( 'Circle', 'swishfolio-core' ), value: 'circle' },
						{ label: __( 'Square', 'swishfolio-core' ), value: 'square' },
						{ label: __( 'Triangle', 'swishfolio-core' ), value: 'triangle' },
						{ label: __( 'Custom SVG', 'swishfolio-core' ), value: 'custom' },
					] }
					onChange={ ( val ) => setAttributes( { [ `${ prefix }Type` ]: val } ) }
				/>

				{ type === 'custom' && (
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) =>
								setAttributes( { [ `${ prefix }CustomSvg` ]: { url: media.url, id: media.id, alt: media.alt } } )
							}
							allowedTypes={ [ 'image/svg+xml', 'image' ] }
							render={ ( { open } ) => (
								<>
									{ customSvg?.url ? (
										<div className="sfcore-image-preview" style={ { marginBottom: '8px' } }>
											<img src={ customSvg.url } alt="" style={ { maxWidth: '80px', maxHeight: '80px' } } />
											<ButtonGroup>
												<Button variant="secondary" onClick={ open } size="small">
													{ __( 'Replace', 'swishfolio-core' ) }
												</Button>
												<Button variant="tertiary" isDestructive onClick={ () => setAttributes( { [ `${ prefix }CustomSvg` ]: {} } ) } size="small">
													{ __( 'Remove', 'swishfolio-core' ) }
												</Button>
											</ButtonGroup>
										</div>
									) : (
										<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
											{ __( 'Upload SVG', 'swishfolio-core' ) }
										</Button>
									) }
								</>
							) }
						/>
					</MediaUploadCheck>
				) }

				{ type !== 'none' && (
					<>
						{ type !== 'custom' && (
							<>
								<p className="components-base-control__label">
									{ __( 'Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ BAUHAUS_COLORS }
									value={ color }
									onChange={ ( val ) => setAttributes( { [ `${ prefix }Color` ]: val } ) }
								/>
							</>
						) }

						<ToggleGroupControl
							label={ __( 'Gradient Fade', 'swishfolio-core' ) }
							value={ gradientSide }
							onChange={ ( val ) => setAttributes( { [ `${ prefix }GradientSide` ]: val } ) }
							isBlock
						>
							<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="top" label={ __( 'Top', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="bottom" label={ __( 'Btm', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
						</ToggleGroupControl>

						<ToggleGroupControl
							label={ __( 'Size', 'swishfolio-core' ) }
							value={ size }
							onChange={ ( val ) => setAttributes( { [ `${ prefix }Size` ]: val } ) }
							isBlock
						>
							<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
						</ToggleGroupControl>

						<SelectControl
							label={ __( 'Position', 'swishfolio-core' ) }
							value={ position }
							options={ [
								{ label: __( 'Top Left', 'swishfolio-core' ), value: 'top-left' },
								{ label: __( 'Top Right', 'swishfolio-core' ), value: 'top-right' },
								{ label: __( 'Bottom Left', 'swishfolio-core' ), value: 'bottom-left' },
								{ label: __( 'Bottom Right', 'swishfolio-core' ), value: 'bottom-right' },
								{ label: __( 'Center Left', 'swishfolio-core' ), value: 'center-left' },
								{ label: __( 'Center Right', 'swishfolio-core' ), value: 'center-right' },
							] }
							onChange={ ( val ) => setAttributes( { [ `${ prefix }Position` ]: val } ) }
						/>

						<RangeControl
							label={ __( 'Opacity', 'swishfolio-core' ) }
							value={ opacity }
							onChange={ ( val ) => setAttributes( { [ `${ prefix }Opacity` ]: val } ) }
							min={ 0 }
							max={ 100 }
						/>
					</>
				) }
			</div>
		);
	};

	// CTA Icon Settings sub-panel
	const CtaIconPanel = ( { label, icon, setIcon, iconPosition, setIconPosition } ) => (
		<div style={ { padding: '12px', marginBottom: '12px', border: '1px solid #ddd', borderRadius: '4px', background: '#f9f9f9' } }>
			<p style={ { fontWeight: 600, fontSize: '13px', marginBottom: '8px', marginTop: 0 } }>
				{ label }
			</p>

			<SelectControl
				label={ __( 'Icon Source', 'swishfolio-core' ) }
				value={ icon.type }
				options={ [
					{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
					{ label: __( 'Auto-detect External', 'swishfolio-core' ), value: 'auto' },
					{ label: __( 'Icon Library', 'swishfolio-core' ), value: 'library' },
					{ label: __( 'Custom SVG', 'swishfolio-core' ), value: 'custom' },
				] }
				onChange={ ( type ) => setIcon( { type, value: type === 'library' ? 'arrow-right' : '' } ) }
			/>

			{ icon.type === 'library' && (
				<>
					<p className="components-base-control__label">
						{ __( 'Select Icon', 'swishfolio-core' ) }
					</p>
					<div className="sfcore-icon-picker">
						{ Object.keys( CTA_ICON_LIBRARY ).map( ( iconKey ) => (
							<Button
								key={ iconKey }
								className={ `sfcore-icon-picker__item ${ icon.value === iconKey ? 'is-selected' : '' }` }
								onClick={ () => setIcon( { ...icon, value: iconKey } ) }
								label={ iconKey }
							>
								<Icon icon={ CTA_ICON_LIBRARY[ iconKey ] } />
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
									<div className="sfcore-custom-icon-preview" style={ { marginBottom: '8px' } }>
										<img src={ icon.value } alt="" style={ { maxWidth: '40px', maxHeight: '40px' } } />
										<ButtonGroup>
											<Button variant="secondary" onClick={ open } size="small">
												{ __( 'Replace', 'swishfolio-core' ) }
											</Button>
											<Button variant="tertiary" isDestructive onClick={ () => setIcon( { ...icon, value: '' } ) } size="small">
												{ __( 'Remove', 'swishfolio-core' ) }
											</Button>
										</ButtonGroup>
									</div>
								) : (
									<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
										{ __( 'Upload SVG', 'swishfolio-core' ) }
									</Button>
								) }
							</>
						) }
					/>
				</MediaUploadCheck>
			) }

			{ icon.type !== 'none' && (
				<ToggleGroupControl
					label={ __( 'Icon Position', 'swishfolio-core' ) }
					value={ iconPosition }
					onChange={ setIconPosition }
					isBlock
				>
					<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
					<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
				</ToggleGroupControl>
			) }
		</div>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
					<SelectControl
						label={ __( 'Layout Style', 'swishfolio-core' ) }
						value={ layout }
						options={ [
							{ label: __( 'Full Background', 'swishfolio-core' ), value: 'full' },
							{ label: __( 'Split - Image Right', 'swishfolio-core' ), value: 'split-right' },
							{ label: __( 'Split - Image Left', 'swishfolio-core' ), value: 'split-left' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>

					<RangeControl
						label={ __( 'Minimum Height (px)', 'swishfolio-core' ) }
						value={ minHeight }
						onChange={ ( value ) => setAttributes( { minHeight: value } ) }
						min={ 300 }
						max={ 1000 }
						step={ 10 }
					/>

					{ ! isSplitLayout && (
						<>
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

							<ToggleGroupControl
								label={ __( 'Vertical Alignment', 'swishfolio-core' ) }
								value={ verticalAlign }
								onChange={ ( value ) => setAttributes( { verticalAlign: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="top" label={ __( 'Top', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="center" label={ __( 'Center', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="bottom" label={ __( 'Bottom', 'swishfolio-core' ) } />
							</ToggleGroupControl>
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Heading Style', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleGroupControl
						label={ __( 'Style', 'swishfolio-core' ) }
						value={ headingStyle }
						onChange={ ( value ) => setAttributes( { headingStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="normal" label={ __( 'Normal', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="shadow" label={ __( 'Shadow', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Size', 'swishfolio-core' ) }
						value={ headingSize }
						onChange={ ( value ) => setAttributes( { headingSize: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="xlarge" label={ __( 'XL', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Text Transform', 'swishfolio-core' ) }
						value={ headingTransform }
						onChange={ ( value ) => setAttributes( { headingTransform: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="uppercase" label={ __( 'UPPER', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleControl
						label={ __( 'Full Width Heading', 'swishfolio-core' ) }
						help={ __( 'Stretch heading to 100% width', 'swishfolio-core' ) }
						checked={ headingFullWidth }
						onChange={ ( value ) => setAttributes( { headingFullWidth: value } ) }
					/>

					{ isSplitLayout && (
						<ToggleControl
							label={ __( 'Overlay Image', 'swishfolio-core' ) }
							help={ __( 'Let heading extend over the image', 'swishfolio-core' ) }
							checked={ headingOverlayImage }
							onChange={ ( value ) => setAttributes( { headingOverlayImage: value } ) }
						/>
					) }

					<p className="components-base-control__label">
						{ __( 'Text Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ textColor }
						onChange={ ( color ) => setAttributes( { textColor: color } ) }
						clearable
					/>
				</PanelBody>

				<PanelBody title={ __( 'Background', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleGroupControl
						label={ __( 'Background Type', 'swishfolio-core' ) }
						value={ backgroundType }
						onChange={ ( value ) => setAttributes( { backgroundType: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="color" label={ __( 'Color', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="image" label={ __( 'Image', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					{ backgroundType === 'color' && (
						<>
							<p className="components-base-control__label">
								{ __( 'Background Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ BAUHAUS_COLORS }
								value={ backgroundColor }
								onChange={ ( color ) => setAttributes( { backgroundColor: color } ) }
								clearable
							/>
						</>
					) }

					{ backgroundType === 'image' && (
						<>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ ( media ) =>
										setAttributes( {
											backgroundImage: {
												url: media.url,
												alt: media.alt,
												id: media.id,
											},
										} )
									}
									allowedTypes={ [ 'image' ] }
									render={ ( { open } ) => (
										<>
											{ backgroundImage?.url ? (
												<div className="sfcore-image-preview">
													<img src={ backgroundImage.url } alt="" />
													<ButtonGroup>
														<Button variant="secondary" onClick={ open }>
															{ __( 'Replace', 'swishfolio-core' ) }
														</Button>
														<Button
															variant="tertiary"
															isDestructive
															onClick={ () => setAttributes( { backgroundImage: {} } ) }
														>
															{ __( 'Remove', 'swishfolio-core' ) }
														</Button>
													</ButtonGroup>
												</div>
											) : (
												<Button variant="secondary" onClick={ open }>
													{ __( 'Select Background Image', 'swishfolio-core' ) }
												</Button>
											) }
										</>
									) }
								/>
							</MediaUploadCheck>

							{ backgroundImage?.url && (
								<>
									<p className="components-base-control__label" style={ { marginTop: '16px' } }>
										{ __( 'Overlay Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ BAUHAUS_COLORS }
										value={ overlayColor }
										onChange={ ( color ) => setAttributes( { overlayColor: color } ) }
									/>
									<RangeControl
										label={ __( 'Overlay Opacity', 'swishfolio-core' ) }
										value={ overlayOpacity }
										onChange={ ( value ) => setAttributes( { overlayOpacity: value } ) }
										min={ 0 }
										max={ 100 }
									/>
								</>
							) }
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Background Shapes', 'swishfolio-core' ) } initialOpen={ false }>
					<p className="components-base-control__help" style={ { marginTop: 0, marginBottom: '12px' } }>
						{ __( 'Add decorative shapes behind the hero content. Supports SVG upload.', 'swishfolio-core' ) }
					</p>
					<BgShapePanel index={ 1 } type={ bgShape1Type } customSvg={ bgShape1CustomSvg } color={ bgShape1Color } gradientSide={ bgShape1GradientSide } size={ bgShape1Size } position={ bgShape1Position } opacity={ bgShape1Opacity } />
					<BgShapePanel index={ 2 } type={ bgShape2Type } customSvg={ bgShape2CustomSvg } color={ bgShape2Color } gradientSide={ bgShape2GradientSide } size={ bgShape2Size } position={ bgShape2Position } opacity={ bgShape2Opacity } />
					<BgShapePanel index={ 3 } type={ bgShape3Type } customSvg={ bgShape3CustomSvg } color={ bgShape3Color } gradientSide={ bgShape3GradientSide } size={ bgShape3Size } position={ bgShape3Position } opacity={ bgShape3Opacity } />
				</PanelBody>

				{ isSplitLayout && (
					<PanelBody title={ __( 'Hero Image', 'swishfolio-core' ) } initialOpen={ true }>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									setAttributes( {
										heroImage: {
											url: media.url,
											alt: media.alt,
											id: media.id,
										},
									} )
								}
								allowedTypes={ [ 'image' ] }
								render={ ( { open } ) => (
									<>
										{ heroImage?.url ? (
											<div className="sfcore-image-preview">
												<img src={ heroImage.url } alt="" />
												<ButtonGroup>
													<Button variant="secondary" onClick={ open }>
														{ __( 'Replace', 'swishfolio-core' ) }
													</Button>
													<Button
														variant="tertiary"
														isDestructive
														onClick={ () => setAttributes( { heroImage: {} } ) }
													>
														{ __( 'Remove', 'swishfolio-core' ) }
													</Button>
												</ButtonGroup>
											</div>
										) : (
											<Button variant="secondary" onClick={ open }>
												{ __( 'Select Hero Image', 'swishfolio-core' ) }
											</Button>
										) }
									</>
								) }
							/>
						</MediaUploadCheck>

						<RangeControl
							label={ __( 'Border Width', 'swishfolio-core' ) }
							value={ imageBorderWidth }
							onChange={ ( value ) => setAttributes( { imageBorderWidth: value } ) }
							min={ 0 }
							max={ 12 }
						/>

						<p className="components-base-control__label">
							{ __( 'Border Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ BAUHAUS_COLORS }
							value={ imageBorderColor }
							onChange={ ( color ) => setAttributes( { imageBorderColor: color } ) }
						/>

						<ToggleGroupControl
							label={ __( 'Shadow Size', 'swishfolio-core' ) }
							value={ imageShadowSize }
							onChange={ ( value ) => setAttributes( { imageShadowSize: value } ) }
							isBlock
						>
							<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="xlarge" label={ __( 'XL', 'swishfolio-core' ) } />
						</ToggleGroupControl>

						<RangeControl
							label={ __( 'Image Rotation', 'swishfolio-core' ) }
							value={ imageRotation }
							onChange={ ( value ) => setAttributes( { imageRotation: value } ) }
							min={ -10 }
							max={ 10 }
						/>
					</PanelBody>
				) }

				{ isSplitLayout && (
					<PanelBody title={ __( 'Image Blobs', 'swishfolio-core' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show Blobs', 'swishfolio-core' ) }
							checked={ showImageDecoShapes }
							onChange={ ( value ) => setAttributes( { showImageDecoShapes: value } ) }
						/>

						{ showImageDecoShapes && (
							<>
								<div style={ { padding: '12px', marginBottom: '12px', border: '1px solid #ddd', borderRadius: '4px', background: '#f9f9f9' } }>
									<p style={ { fontWeight: 600, fontSize: '13px', marginBottom: '8px', marginTop: 0 } }>
										{ __( 'Blob 1', 'swishfolio-core' ) }
									</p>
									<p className="components-base-control__label">
										{ __( 'Shape', 'swishfolio-core' ) }
									</p>
									<div className="sfcore-blob-picker">
										<Button
											className={ `sfcore-blob-picker__item ${ imageBlob1Shape === 'none' ? 'is-selected' : '' }` }
											onClick={ () => setAttributes( { imageBlob1Shape: 'none' } ) }
											label={ __( 'None', 'swishfolio-core' ) }
										>
											<span style={ { fontSize: '11px' } }>{ __( 'None', 'swishfolio-core' ) }</span>
										</Button>
										{ Object.keys( BLOB_PRESETS ).map( ( blobKey ) => (
											<Button
												key={ blobKey }
												className={ `sfcore-blob-picker__item ${ imageBlob1Shape === blobKey ? 'is-selected' : '' }` }
												onClick={ () => setAttributes( { imageBlob1Shape: blobKey } ) }
												label={ blobKey }
											>
												{ renderBlobSvg( blobKey, imageBlob1Color || '#ccc', 36 ) }
											</Button>
										) ) }
									</div>
									<p className="components-base-control__label">
										{ __( 'Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ BAUHAUS_COLORS }
										value={ imageBlob1Color }
										onChange={ ( color ) => setAttributes( { imageBlob1Color: color } ) }
									/>
									<ToggleGroupControl
										label={ __( 'Size', 'swishfolio-core' ) }
										value={ imageBlob1Size }
										onChange={ ( value ) => setAttributes( { imageBlob1Size: value } ) }
										isBlock
									>
										<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
										<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
										<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
									</ToggleGroupControl>
									<RangeControl
										label={ __( 'Rotation', 'swishfolio-core' ) }
										value={ imageBlob1Rotation }
										onChange={ ( value ) => setAttributes( { imageBlob1Rotation: value } ) }
										min={ -180 }
										max={ 180 }
									/>
								</div>

								<div style={ { padding: '12px', marginBottom: '12px', border: '1px solid #ddd', borderRadius: '4px', background: '#f9f9f9' } }>
									<p style={ { fontWeight: 600, fontSize: '13px', marginBottom: '8px', marginTop: 0 } }>
										{ __( 'Blob 2', 'swishfolio-core' ) }
									</p>
									<p className="components-base-control__label">
										{ __( 'Shape', 'swishfolio-core' ) }
									</p>
									<div className="sfcore-blob-picker">
										<Button
											className={ `sfcore-blob-picker__item ${ imageBlob2Shape === 'none' ? 'is-selected' : '' }` }
											onClick={ () => setAttributes( { imageBlob2Shape: 'none' } ) }
											label={ __( 'None', 'swishfolio-core' ) }
										>
											<span style={ { fontSize: '11px' } }>{ __( 'None', 'swishfolio-core' ) }</span>
										</Button>
										{ Object.keys( BLOB_PRESETS ).map( ( blobKey ) => (
											<Button
												key={ blobKey }
												className={ `sfcore-blob-picker__item ${ imageBlob2Shape === blobKey ? 'is-selected' : '' }` }
												onClick={ () => setAttributes( { imageBlob2Shape: blobKey } ) }
												label={ blobKey }
											>
												{ renderBlobSvg( blobKey, imageBlob2Color || '#ccc', 36 ) }
											</Button>
										) ) }
									</div>
									<p className="components-base-control__label">
										{ __( 'Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ BAUHAUS_COLORS }
										value={ imageBlob2Color }
										onChange={ ( color ) => setAttributes( { imageBlob2Color: color } ) }
									/>
									<ToggleGroupControl
										label={ __( 'Size', 'swishfolio-core' ) }
										value={ imageBlob2Size }
										onChange={ ( value ) => setAttributes( { imageBlob2Size: value } ) }
										isBlock
									>
										<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
										<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
										<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
									</ToggleGroupControl>
									<RangeControl
										label={ __( 'Rotation', 'swishfolio-core' ) }
										value={ imageBlob2Rotation }
										onChange={ ( value ) => setAttributes( { imageBlob2Rotation: value } ) }
										min={ -180 }
										max={ 180 }
									/>
								</div>
							</>
						) }
					</PanelBody>
				) }

				<PanelBody title={ __( 'Call to Action', 'swishfolio-core' ) } initialOpen={ false }>
					<URLInput
						label={ __( 'Primary Button URL', 'swishfolio-core' ) }
						value={ ctaUrl }
						onChange={ ( url ) => setAttributes( { ctaUrl: url } ) }
					/>
					<ToggleGroupControl
						label={ __( 'Primary Button Style', 'swishfolio-core' ) }
						value={ ctaStyle }
						onChange={ ( value ) => setAttributes( { ctaStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="primary" label={ __( 'Primary', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="secondary" label={ __( 'Secondary', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<URLInput
						label={ __( 'Secondary Button URL', 'swishfolio-core' ) }
						value={ secondaryCtaUrl }
						onChange={ ( url ) => setAttributes( { secondaryCtaUrl: url } ) }
					/>

					<CtaIconPanel
						label={ __( 'Primary Button Icon', 'swishfolio-core' ) }
						icon={ ctaIcon }
						setIcon={ ( val ) => setAttributes( { ctaIcon: val } ) }
						iconPosition={ ctaIconPosition }
						setIconPosition={ ( val ) => setAttributes( { ctaIconPosition: val } ) }
					/>

					<CtaIconPanel
						label={ __( 'Secondary Button Icon', 'swishfolio-core' ) }
						icon={ secondaryCtaIcon }
						setIcon={ ( val ) => setAttributes( { secondaryCtaIcon: val } ) }
						iconPosition={ secondaryCtaIconPosition }
						setIconPosition={ ( val ) => setAttributes( { secondaryCtaIconPosition: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Links', 'swishfolio-core' ) } initialOpen={ false }>
					<p className="components-base-control__help" style={ { marginTop: 0, marginBottom: '12px' } }>
						{ __( 'Add social media links that appear after the buttons.', 'swishfolio-core' ) }
					</p>

					{ socialLinks.map( ( link, index ) => (
						<div
							key={ index }
							style={ {
								padding: '12px',
								marginBottom: '12px',
								border: '1px solid #ddd',
								borderRadius: '4px',
								background: '#f9f9f9',
							} }
						>
							<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } }>
								<span style={ { fontWeight: 600, fontSize: '13px' } }>
									{ __( 'Link', 'swishfolio-core' ) } { index + 1 }
								</span>
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => removeSocialLink( index ) }
									icon="no-alt"
									label={ __( 'Remove', 'swishfolio-core' ) }
									style={ { minWidth: 'auto', padding: '0' } }
								/>
							</div>

							<SelectControl
								label={ __( 'Icon', 'swishfolio-core' ) }
								value={ link.icon }
								options={ Object.keys( SOCIAL_ICONS ).map( ( key ) => ( {
									label: SOCIAL_ICONS[ key ].label,
									value: key,
								} ) ) }
								onChange={ ( value ) => updateSocialLink( index, 'icon', value ) }
							/>

							{ link.icon === 'custom' && (
								<MediaUploadCheck>
									<MediaUpload
										onSelect={ ( media ) =>
											updateSocialLink( index, 'customIcon', {
												url: media.url,
												alt: media.alt,
												id: media.id,
											} )
										}
										allowedTypes={ [ 'image' ] }
										render={ ( { open } ) => (
											<>
												{ link.customIcon?.url ? (
													<div className="sfcore-image-preview" style={ { marginBottom: '8px' } }>
														<img
															src={ link.customIcon.url }
															alt=""
															style={ { maxWidth: '40px', maxHeight: '40px' } }
														/>
														<ButtonGroup>
															<Button variant="secondary" onClick={ open } size="small">
																{ __( 'Replace', 'swishfolio-core' ) }
															</Button>
															<Button
																variant="tertiary"
																isDestructive
																onClick={ () => updateSocialLink( index, 'customIcon', null ) }
																size="small"
															>
																{ __( 'Remove', 'swishfolio-core' ) }
															</Button>
														</ButtonGroup>
													</div>
												) : (
													<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
														{ __( 'Upload Custom Icon', 'swishfolio-core' ) }
													</Button>
												) }
											</>
										) }
									/>
								</MediaUploadCheck>
							) }

							<TextControl
								label={ __( 'URL', 'swishfolio-core' ) }
								value={ link.url }
								onChange={ ( value ) => updateSocialLink( index, 'url', value ) }
								placeholder="https://"
							/>
						</div>
					) ) }

					<Button
						variant="secondary"
						onClick={ addSocialLink }
						icon="plus"
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ __( 'Add Link', 'swishfolio-core' ) }
					</Button>
				</PanelBody>

				{ ! isSplitLayout && (
					<PanelBody title={ __( 'Decorative Shape', 'swishfolio-core' ) } initialOpen={ false }>
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
								/>
							</>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ renderOverlay() }
				{ renderDecorativeShape() }
				{ renderBgShape( bgShape1Type, bgShape1CustomSvg, bgShape1Color, bgShape1GradientSide, bgShape1Size, bgShape1Position, bgShape1Opacity ) }
				{ renderBgShape( bgShape2Type, bgShape2CustomSvg, bgShape2Color, bgShape2GradientSide, bgShape2Size, bgShape2Position, bgShape2Opacity ) }
				{ renderBgShape( bgShape3Type, bgShape3CustomSvg, bgShape3Color, bgShape3GradientSide, bgShape3Size, bgShape3Position, bgShape3Opacity ) }

				<div className="sfcore-hero__inner">
					<div className="sfcore-hero__content">
						<RichText
							tagName="h1"
							className={ getHeadingClasses() }
							value={ heading }
							onChange={ ( value ) => setAttributes( { heading: value } ) }
							placeholder={ __( 'Hero Heading', 'swishfolio-core' ) }
							style={ { color: textColor } }
						/>
						<RichText
							tagName="p"
							className="sfcore-hero__subheading"
							value={ subheading }
							onChange={ ( value ) => setAttributes( { subheading: value } ) }
							placeholder={ __( 'Add a subheading...', 'swishfolio-core' ) }
							style={ { color: textColor } }
						/>
						<div className="sfcore-hero__buttons">
							<span className={ `sfcore-hero__cta sfcore-hero__cta--${ ctaStyle } sfcore-hero__cta--has-icon` }>
								{ ctaIconPosition === 'left' && renderCtaIcon( ctaIcon ) }
								<RichText
									tagName="span"
									className="sfcore-hero__cta-text"
									value={ ctaText }
									onChange={ ( value ) => setAttributes( { ctaText: value } ) }
									placeholder={ __( 'Primary Button', 'swishfolio-core' ) }
								/>
								{ ctaIconPosition === 'right' && renderCtaIcon( ctaIcon ) }
							</span>
							<span className="sfcore-hero__cta sfcore-hero__cta--outline sfcore-hero__cta--has-icon">
								{ secondaryCtaIconPosition === 'left' && renderCtaIcon( secondaryCtaIcon ) }
								<RichText
									tagName="span"
									className="sfcore-hero__cta-text"
									value={ secondaryCtaText }
									onChange={ ( value ) => setAttributes( { secondaryCtaText: value } ) }
									placeholder={ __( 'Secondary Button', 'swishfolio-core' ) }
								/>
								{ secondaryCtaIconPosition === 'right' && renderCtaIcon( secondaryCtaIcon ) }
							</span>
						</div>

						{ socialLinks && socialLinks.length > 0 && (
							<div className="sfcore-hero__social-links">
								{ socialLinks.map( ( link, index ) => (
									<span key={ index } className="sfcore-hero__social-link">
										{ link.icon === 'custom' && link.customIcon?.url ? (
											<img
												src={ link.customIcon.url }
												alt={ link.customIcon.alt || '' }
												className="sfcore-hero__social-custom-icon"
											/>
										) : (
											renderSocialIcon( link.icon )
										) }
									</span>
								) ) }
							</div>
						) }
					</div>

					{ renderHeroImage() }
				</div>
			</div>
		</>
	);
}

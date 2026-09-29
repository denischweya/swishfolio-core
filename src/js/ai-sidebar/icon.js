/**
 * Sidebar and toolbar icon.
 *
 * Inline rather than from `@wordpress/icons`: that package is bundled rather
 * than externalised by the build, so importing it for one glyph would ship the
 * whole icon set inside this entry point.
 */

export const AiIcon = () => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
		aria-hidden="true"
		focusable="false"
	>
		<path
			fill="currentColor"
			d="M11 2.5l1.6 4L16.5 8l-3.9 1.5L11 13.5 9.4 9.5 5.5 8l3.9-1.5L11 2.5zm6.5 9l.9 2.2 2.1.8-2.1.8-.9 2.2-.9-2.2-2.1-.8 2.1-.8.9-2.2zM7 15l.7 1.8L9.5 17.5l-1.8.7L7 20l-.7-1.8L4.5 17.5l1.8-.7L7 15z"
		/>
	</svg>
);

export default AiIcon;

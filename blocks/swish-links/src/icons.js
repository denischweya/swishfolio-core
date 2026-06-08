/**
 * Icon library shared between edit.js and save.js so the editor preview
 * matches what's rendered on the frontend.
 *
 * Slugs match ButtonExtension.php's PHP ICONS array — when you add an icon
 * here, mirror it there so the button extension stays in sync.
 */

const svgProps = {
	width: 16,
	height: 16,
	viewBox: '0 0 24 24',
	fill: 'none',
	stroke: 'currentColor',
	strokeWidth: 2,
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
	'aria-hidden': true,
	focusable: false,
};

export const ICONS = {
	'arrow-right': (
		<svg { ...svgProps }>
			<line x1="5" y1="12" x2="19" y2="12" />
			<polyline points="12 5 19 12 12 19" />
		</svg>
	),
	'arrow-left': (
		<svg { ...svgProps }>
			<line x1="19" y1="12" x2="5" y2="12" />
			<polyline points="12 19 5 12 12 5" />
		</svg>
	),
	'arrow-up-right': (
		<svg { ...svgProps }>
			<line x1="7" y1="17" x2="17" y2="7" />
			<polyline points="7 7 17 7 17 17" />
		</svg>
	),
	'arrow-down': (
		<svg { ...svgProps }>
			<line x1="12" y1="5" x2="12" y2="19" />
			<polyline points="19 12 12 19 5 12" />
		</svg>
	),
	'chevron-right': (
		<svg { ...svgProps }>
			<polyline points="9 6 15 12 9 18" />
		</svg>
	),
	plus: (
		<svg { ...svgProps }>
			<line x1="12" y1="5" x2="12" y2="19" />
			<line x1="5" y1="12" x2="19" y2="12" />
		</svg>
	),
	check: (
		<svg { ...svgProps }>
			<polyline points="20 6 9 17 4 12" />
		</svg>
	),
	download: (
		<svg { ...svgProps }>
			<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
			<polyline points="7 10 12 15 17 10" />
			<line x1="12" y1="15" x2="12" y2="3" />
		</svg>
	),
	external: (
		<svg { ...svgProps }>
			<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
			<polyline points="15 3 21 3 21 9" />
			<line x1="10" y1="14" x2="21" y2="3" />
		</svg>
	),
};

export const ICON_OPTIONS = [
	{ value: '', label: '— None —' },
	{ value: 'arrow-right', label: 'Arrow right' },
	{ value: 'arrow-left', label: 'Arrow left' },
	{ value: 'arrow-up-right', label: 'Arrow up-right (external)' },
	{ value: 'arrow-down', label: 'Arrow down (download)' },
	{ value: 'chevron-right', label: 'Chevron right' },
	{ value: 'plus', label: 'Plus' },
	{ value: 'check', label: 'Check' },
	{ value: 'download', label: 'Download' },
	{ value: 'external', label: 'External' },
];

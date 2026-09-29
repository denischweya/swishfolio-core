/**
 * Jest configuration for the AI editor UI tests (tests/js/).
 *
 * Run it with `wp-scripts test-unit-js` (see requiredWiring for the npm script).
 * Because this file exists, wp-scripts' own config/jest-unit.config.js is not
 * applied — `hasJestConfig()` short-circuits it — so the two things it would
 * have supplied are set here explicitly: the WordPress jest preset, and the
 * babel transform (this project has no babel config of its own, so babel-jest
 * would otherwise run with no presets and choke on JSX).
 *
 * Every `@wordpress/*` package the modules under test import is externalised at
 * build time and therefore absent from node_modules. Each is mapped to a mock
 * in tests/js/mocks/. `@wordpress/element` maps to `react` because that is what
 * it is: a re-export of React, which the editor exposes as `wp.element`.
 */

const path = require( 'path' );

const mock = ( name ) => path.join( __dirname, 'tests', 'js', 'mocks', name );

module.exports = {
	preset: '@wordpress/jest-preset-default',
	rootDir: __dirname,
	testMatch: [ '<rootDir>/tests/js/**/*.test.js' ],
	transform: {
		'\\.[jt]sx?$': require.resolve(
			'@wordpress/scripts/config/babel-transform'
		),
	},
	moduleNameMapper: {
		'^@wordpress/element$': 'react',
		'^@wordpress/i18n$': mock( 'wp-i18n.js' ),
		'^@wordpress/api-fetch$': mock( 'wp-api-fetch.js' ),
		'^@wordpress/data$': mock( 'wp-data.js' ),
		'^@wordpress/blocks$': mock( 'wp-blocks.js' ),
		'^@wordpress/components$': mock( 'wp-components.js' ),
		'^@wordpress/block-editor$': mock( 'wp-block-editor.js' ),
		'^@wordpress/editor$': mock( 'wp-editor.js' ),
		'^@wordpress/notices$': mock( 'wp-notices.js' ),
		'^@wordpress/compose$': mock( 'wp-compose.js' ),
		'^@wordpress/hooks$': mock( 'wp-hooks.js' ),
		'^@wordpress/plugins$': mock( 'wp-plugins.js' ),
	},
};

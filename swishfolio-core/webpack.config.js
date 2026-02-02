// Webpack Configuration for Swishfolio Core

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const fs = require( 'fs' );

// Find all block directories
const blocksDir = path.resolve( process.cwd(), 'blocks' );
const blockFolders = fs.readdirSync( blocksDir ).filter( ( folder ) => {
	const indexPath = path.join( blocksDir, folder, 'src', 'index.js' );
	return fs.existsSync( indexPath );
} );

// Build entry points for each block
const blockEntries = {};
blockFolders.forEach( ( blockName ) => {
	const srcDir = path.join( blocksDir, blockName, 'src' );
	blockEntries[ `${ blockName }/build/index` ] = path.join( srcDir, 'index.js' );

	// View script if exists
	const viewPath = path.join( srcDir, 'view.js' );
	if ( fs.existsSync( viewPath ) ) {
		blockEntries[ `${ blockName }/build/view` ] = viewPath;
	}
} );

// Find all editor plugins in src/js/
const jsDir = path.resolve( process.cwd(), 'src', 'js' );
if ( fs.existsSync( jsDir ) ) {
	const jsFiles = fs.readdirSync( jsDir ).filter( ( file ) => file.endsWith( '.js' ) );
	jsFiles.forEach( ( file ) => {
		const name = file.replace( '.js', '' );
		blockEntries[ `../assets/js/${ name }` ] = path.join( jsDir, file );
	} );
}

// Remove CleanWebpackPlugin to prevent deleting source files
// (output path contains source files in blocks/ directory)
const filteredPlugins = defaultConfig.plugins.filter(
	( plugin ) => plugin.constructor.name !== 'CleanWebpackPlugin'
);

module.exports = {
	...defaultConfig,
	entry: blockEntries,
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'blocks' ),
		filename: '[name].js',
	},
	plugins: filteredPlugins,
};

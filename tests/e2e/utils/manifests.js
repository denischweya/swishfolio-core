/**
 * Reads the capability manifests from disk, the way BlockRegistry does.
 *
 * PHP localises these into the editor as `window.sfcoreAiEditor`. When that
 * localisation is not present on the site under test, `ensureAiEditorLoaded()`
 * supplies the same payload from these files so the loop under test is still
 * driven by the real manifests rather than an invented fixture.
 *
 * The block-name rules mirror `BlockRegistry::readFromDisk()` exactly: a
 * `<dir>/<block>/ai.json` is named by its directory unless it declares
 * `blockName`, and a flat `<dir>/*.json` counts only when it declares one.
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );

const PLUGIN_ROOT = path.resolve( __dirname, '..', '..', '..' );

const MANIFEST_DIRS = [
	path.join( PLUGIN_ROOT, 'blocks' ),
	path.join( PLUGIN_ROOT, 'src', 'AI', 'Manifests', 'core' ),
];

function readJson( file ) {
	try {
		const parsed = JSON.parse( fs.readFileSync( file, 'utf8' ) );

		return parsed && typeof parsed === 'object' && ! Array.isArray( parsed )
			? parsed
			: null;
	} catch ( error ) {
		return null;
	}
}

function entries( dir ) {
	try {
		return fs.readdirSync( dir, { withFileTypes: true } );
	} catch ( error ) {
		return [];
	}
}

/**
 * @return {Object} Raw manifests keyed by full block name.
 */
function collectCapabilityManifests() {
	const manifests = {};

	MANIFEST_DIRS.forEach( ( dir ) => {
		entries( dir ).forEach( ( entry ) => {
			if ( entry.isDirectory() ) {
				const manifest = readJson(
					path.join( dir, entry.name, 'ai.json' )
				);

				if ( manifest ) {
					manifests[
						manifest.blockName ||
							'swishfolio-core/' + entry.name
					] = manifest;
				}

				return;
			}

			if ( ! entry.name.endsWith( '.json' ) ) {
				return;
			}

			const manifest = readJson( path.join( dir, entry.name ) );

			if ( manifest && manifest.blockName ) {
				manifests[ manifest.blockName ] = manifest;
			}
		} );
	} );

	return manifests;
}

module.exports = { collectCapabilityManifests, PLUGIN_ROOT };

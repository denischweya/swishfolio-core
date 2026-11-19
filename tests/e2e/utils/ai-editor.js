/**
 * Page-side helpers for the AI editing end-to-end test.
 *
 * Block selection and attribute reads go through the block editor's own data
 * store rather than the canvas DOM. The assertions in this suite are about
 * attribute values and about how many undo steps a change occupies — both live
 * in the store, and reading them there removes a whole class of markup-shaped
 * flakiness from a test whose real subject is the apply/undo contract.
 *
 * The one thing that must go through the DOM is the undo keystroke: it is the
 * behaviour under test, so it is pressed for real, from the canvas, where a
 * user would press it.
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );

const { collectCapabilityManifests, PLUGIN_ROOT } = require( './manifests' );

/** Mirrors src/js/ai/constants.js. */
const PLUGIN_NAME = 'sfcore-ai';
const GLOBAL_DATA_KEY = 'sfcoreAiEditor';
const DEFAULT_MAX_ACTIONS = 25;

const BUNDLE_PATH = path.join( PLUGIN_ROOT, 'assets', 'js', 'ai-sidebar.js' );

/**
 * Guarantee the AI editing UI is running in this editor page.
 *
 * Preferred path: PHP enqueued `assets/js/ai-sidebar.js` and localised the
 * manifests, and this is a no-op. Fallback path: the enqueue wiring is not on
 * the site yet, so the built bundle is injected and the manifests are supplied
 * from disk. The fallback exercises the very same bundle a browser would load;
 * what it does not prove is the enqueue itself, so the path taken is returned
 * and recorded as a test annotation.
 *
 * @param {import('@playwright/test').Page} page Editor page.
 *
 * @return {Promise<Object>} { enqueuedByPlugin, localisedByPlugin }.
 */
async function ensureAiEditorLoaded( page ) {
	await page.waitForFunction(
		() => !! ( window.wp && window.wp.plugins && window.wp.data )
	);

	const before = await page.evaluate(
		( [ pluginName, dataKey ] ) => {
			const data = window[ dataKey ];

			return {
				enqueuedByPlugin: !! window.wp.plugins.getPlugin( pluginName ),
				localisedByPlugin: !! (
					data &&
					data.capabilities &&
					Object.keys( data.capabilities ).length > 0
				),
				hasJsxRuntime: !! window.ReactJSXRuntime,
			};
		},
		[ PLUGIN_NAME, GLOBAL_DATA_KEY ]
	);

	if ( ! before.localisedByPlugin ) {
		await page.evaluate(
			( [ dataKey, payload ] ) => {
				window[ dataKey ] = payload;
			},
			[
				GLOBAL_DATA_KEY,
				{
					capabilities: collectCapabilityManifests(),
					maxActions: DEFAULT_MAX_ACTIONS,
				},
			]
		);
	}

	if ( ! before.enqueuedByPlugin ) {
		if ( ! fs.existsSync( BUNDLE_PATH ) ) {
			throw new Error(
				'assets/js/ai-sidebar.js is missing — run `npm run build` before the e2e suite.'
			);
		}

		if ( ! before.hasJsxRuntime ) {
			throw new Error(
				'window.ReactJSXRuntime is absent, so the AI bundle cannot be injected. ' +
					'Enqueue assets/js/ai-sidebar.js from PHP with its .asset.php dependencies.'
			);
		}

		await page.addScriptTag( {
			content: fs.readFileSync( BUNDLE_PATH, 'utf8' ),
		} );

		await page.waitForFunction(
			( pluginName ) => !! window.wp.plugins.getPlugin( pluginName ),
			PLUGIN_NAME
		);
	}

	return {
		enqueuedByPlugin: before.enqueuedByPlugin,
		localisedByPlugin: before.localisedByPlugin,
	};
}

/**
 * Select the first block with this name and return its clientId.
 *
 * @param {import('@playwright/test').Page} page      Editor page.
 * @param {string}                          blockName Full block name.
 *
 * @return {Promise<string>} The selected clientId.
 */
async function selectBlockByName( page, blockName ) {
	const clientId = await page.evaluate( ( name ) => {
		const find = ( blocks ) => {
			for ( const block of blocks ) {
				if ( block.name === name ) {
					return block.clientId;
				}

				const inner = find( block.innerBlocks || [] );

				if ( inner ) {
					return inner;
				}
			}

			return null;
		};

		const found = find(
			window.wp.data.select( 'core/block-editor' ).getBlocks()
		);

		if ( found ) {
			window.wp.data
				.dispatch( 'core/block-editor' )
				.selectBlock( found );
		}

		return found;
	}, blockName );

	if ( ! clientId ) {
		throw new Error( 'No ' + blockName + ' block is in the editor.' );
	}

	return clientId;
}

/**
 * @param {import('@playwright/test').Page} page     Editor page.
 * @param {string}                          clientId Block to read.
 *
 * @return {Promise<Object|null>} Plain-JSON attributes, or null when gone.
 */
async function getBlockAttributes( page, clientId ) {
	return page.evaluate( ( id ) => {
		const attributes = window.wp.data
			.select( 'core/block-editor' )
			.getBlockAttributes( id );

		if ( ! attributes ) {
			return null;
		}

		const RichTextData =
			window.wp.richText && window.wp.richText.RichTextData;

		return Object.fromEntries(
			Object.entries( attributes ).map( ( [ key, value ] ) => [
				key,
				RichTextData && value instanceof RichTextData
					? value.toString()
					: value,
			] )
		);
	}, clientId );
}

/**
 * @param {import('@playwright/test').Page} page     Editor page.
 * @param {string}                          clientId Parent block.
 *
 * @return {Promise<string[]>} Child clientIds, in order.
 */
async function getChildClientIds( page, clientId ) {
	return page.evaluate(
		( id ) =>
			window.wp.data.select( 'core/block-editor' ).getBlockOrder( id ),
		clientId
	);
}

/**
 * Edit a block the way a user would: one persistent change, one undo level.
 *
 * Used to put a known edit on the undo stack *before* the AI change, so the
 * single undo that follows can be shown to have stopped at the right place
 * rather than swallowing whatever came before it.
 *
 * @param {import('@playwright/test').Page} page     Editor page.
 * @param {string}                          clientId Block to edit.
 * @param {Object}                          changes  Attributes to set.
 *
 * @return {Promise<void>} Resolves once dispatched.
 */
async function editBlockAttributes( page, clientId, changes ) {
	await page.evaluate(
		( [ id, attributes ] ) => {
			window.wp.data
				.dispatch( 'core/block-editor' )
				.updateBlockAttributes( id, attributes );
		},
		[ clientId, changes ]
	);
}

/**
 * Put keyboard focus on a block in the canvas.
 *
 * Undo is a global editor shortcut, delivered through the ShortcutProvider that
 * wraps the editor UI. After an apply the AI sidebar has torn down the button
 * that had focus, leaving it on `document.body` — an ancestor of that provider,
 * so a keydown there never reaches it. Focusing the selected block's wrapper
 * puts the keystroke where a user's would be: inside the canvas, whose events
 * the editor iframe re-dispatches to the top document.
 *
 * @param {Object} editor   The `editor` fixture.
 * @param {string} clientId Block to focus.
 *
 * @return {Promise<void>} Resolves once focused.
 */
async function focusBlockInCanvas( editor, clientId ) {
	await editor.canvas
		.locator( '[data-block="' + clientId + '"]' )
		.first()
		.focus();
}

module.exports = {
	editBlockAttributes,
	ensureAiEditorLoaded,
	focusBlockInCanvas,
	getBlockAttributes,
	getChildClientIds,
	selectBlockByName,
};

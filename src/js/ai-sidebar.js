/**
 * Webpack entry point for the AI block editing UI.
 *
 * webpack.config.js discovers every src/js/*.js and emits it to
 * assets/js/<name>.js plus a matching <name>.asset.php, so this file's name is
 * the script's name: assets/js/ai-sidebar.js.
 */

import { initAiEditor } from './ai-sidebar/index';

initAiEditor();

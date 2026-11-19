#!/usr/bin/env bash
#
# Prove every theme section pattern renders on the front end.
#
# Builds a throwaway draft containing every swishfolio/section-* pattern, loads
# it in a browser, measures the vertical space each one occupies, then removes
# the draft. A pattern that parses cleanly and emits markup can still collapse
# to nothing for a visitor; this is the check that catches that.
#
# Usage:  npm run test:e2e:patterns

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_DIR="$(cd "${PLUGIN_DIR}/../../.." && pwd)"
SITE="${SFCORE_E2E_URL:-https://swishfolio.ddev.site}"
HOST="${SITE#https://}"
USE_DDEV=0

if command -v ddev >/dev/null 2>&1 && [ -f "${WP_DIR}/../.ddev/config.yaml" ]; then
	USE_DDEV=1
fi

# wp eval-file avoids quoting a PHP heredoc through ddev exec.
run_php() {
	local script_name="$1"
	if [ "${USE_DDEV}" -eq 1 ]; then
		( cd "${WP_DIR}/.." && ddev mutagen sync >/dev/null 2>&1 || true )
		ddev exec -d /var/www/html/wordpress wp eval-file "${script_name}" --url="${HOST}"
	else
		wp eval-file "${WP_DIR}/${script_name}" --path="${WP_DIR}" --url="${HOST}"
	fi
}

BUILD="${WP_DIR}/sfcore-pattern-build.php"
TEARDOWN="${WP_DIR}/sfcore-pattern-teardown.php"

cat > "${TEARDOWN}" <<'PHP'
<?php
// Match on title as well as slug: WordPress suffixes a duplicate slug, so a
// name-only lookup silently leaves earlier runs behind.
$found = get_posts( [
	'post_type'   => 'page',
	'post_status' => 'any',
	'numberposts' => -1,
	's'           => 'SFCORE pattern proof',
] );

foreach ( $found as $page ) {
	if ( strpos( $page->post_title, 'SFCORE pattern proof' ) === 0 ) {
		wp_delete_post( $page->ID, true );
		echo "removed draft {$page->ID}\n";
	}
}
PHP

cleanup() {
	run_php "$(basename "${TEARDOWN}")" >/dev/null 2>&1 || true
	rm -f "${BUILD}" "${TEARDOWN}"
}
trap cleanup EXIT

cat > "${BUILD}" <<'PHP'
<?php
$registry = WP_Block_Patterns_Registry::get_instance();
$sections = array_filter( $registry->get_all_registered(), function ( $pattern ) {
	return isset( $pattern['name'] ) && strpos( $pattern['name'], 'swishfolio/section-' ) === 0;
} );
ksort( $sections );

$content = '';
foreach ( $sections as $pattern ) {
	$label    = str_replace( 'swishfolio/', '', $pattern['name'] );
	$content .= '<!-- wp:heading {"level":2} --><h2 id="' . $label . '">' . $label . '</h2><!-- /wp:heading -->' . "\n";
	$content .= $pattern['content'] . "\n";
}

$existing = get_posts( [ 'post_type' => 'page', 'post_status' => 'any', 'name' => 'sfcore-pattern-proof', 'numberposts' => 1 ] );

$args = [
	'post_title'   => 'SFCORE pattern proof (temporary)',
	'post_name'    => 'sfcore-pattern-proof',
	'post_content' => $content,
	'post_status'  => 'draft',
	'post_type'    => 'page',
];

if ( $existing ) {
	$args['ID'] = $existing[0]->ID;
}

$id = wp_insert_post( $args, true );

echo is_wp_error( $id ) ? '' : (int) $id;
PHP

ID="$(run_php "$(basename "${BUILD}")" | tr -dc '0-9')"

if [ -z "${ID}" ]; then
	echo "Could not create the proof draft — is the site running?" >&2
	exit 1
fi

SFCORE_PREVIEW_URL="${SITE}/?page_id=${ID}&preview=true" \
	npx playwright test --config=playwright.smoke.config.js tests/e2e/smoke/pattern-render.spec.js

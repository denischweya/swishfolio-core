#!/usr/bin/env bash
#
# Mint a Playwright storage state for an existing administrator.
#
# The bundled wp-scripts global setup logs in with WP_USERNAME/WP_PASSWORD. This
# needs neither: it asks WordPress to issue the same session a normal login
# would, so the browser suites can run without a password in the environment and
# without creating a throwaway admin user.
#
# Usage:  npm run test:e2e:session
#         SFCORE_E2E_USER=2 SFCORE_E2E_URL=https://example.test npm run test:e2e:session

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_DIR="$(cd "${PLUGIN_DIR}/../../.." && pwd)"
SITE_URL="${SFCORE_E2E_URL:-https://swishfolio.ddev.site}"
USER_ID="${SFCORE_E2E_USER:-1}"
OUT="${PLUGIN_DIR}/artifacts/storage-states/admin.json"

SCRIPT="${WP_DIR}/sfcore-e2e-session.php"
cat > "${SCRIPT}" <<'PHP'
<?php
$uid  = (int) ( getenv( 'SFCORE_E2E_USER' ) ?: 1 );
$user = get_user_by( 'id', $uid );

if ( ! $user ) {
    fwrite( STDERR, "No such user: {$uid}\n" );
    exit( 1 );
}

$expiration = time() + 2 * DAY_IN_SECONDS;
$token      = WP_Session_Tokens::get_instance( $uid )->create( $expiration );
$host       = parse_url( home_url(), PHP_URL_HOST );

$cookies = [
    [ 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $expiration, 'logged_in', $token ), 'path' => '/' ],
    [ 'name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $expiration, 'secure_auth', $token ), 'path' => rtrim( parse_url( admin_url(), PHP_URL_PATH ), '/' ) ?: '/' ],
    [ 'name' => 'wordpress_test_cookie', 'value' => 'WP+Cookie+check', 'path' => '/' ],
];

foreach ( $cookies as &$cookie ) {
    $cookie['domain']   = '.' . $host;
    $cookie['expires']  = $expiration;
    $cookie['httpOnly'] = true;
    $cookie['secure']   = true;
    $cookie['sameSite'] = 'Lax';
}

echo wp_json_encode( [ 'cookies' => $cookies, 'origins' => [] ] );
PHP

mkdir -p "$(dirname "${OUT}")"

if command -v ddev >/dev/null 2>&1 && [ -f "${WP_DIR}/../.ddev/config.yaml" ]; then
	( cd "${WP_DIR}/.." && ddev mutagen sync >/dev/null 2>&1 || true )
	ddev exec -d /var/www/html/wordpress \
		env SFCORE_E2E_USER="${USER_ID}" \
		wp eval-file sfcore-e2e-session.php --url="${SITE_URL#https://}" > "${OUT}"
else
	SFCORE_E2E_USER="${USER_ID}" wp eval-file "${SCRIPT}" --path="${WP_DIR}" --url="${SITE_URL#https://}" > "${OUT}"
fi

rm -f "${SCRIPT}"

if [ ! -s "${OUT}" ]; then
	echo "Failed to mint a session — is the site running?" >&2
	exit 1
fi

echo "Session written to ${OUT}"

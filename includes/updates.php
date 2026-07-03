<?php
/**
 * Self-updates from GitHub releases via the WP 5.8+ Update URI mechanism.
 *
 * The `Update URI` plugin header points at the GitHub repo, which makes
 * WordPress fire the `update_plugins_github.com` filter for this plugin
 * during its normal update checks. We answer it from the GitHub releases
 * API — no external updater library needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WHPL_GITHUB_REPO', 'alhoniko/warehouse-picklist-for-woocommerce' );

/**
 * Plugin basename resolved at runtime, so updates keep working even if the
 * plugin folder is named differently (e.g. installed from a repo ZIP).
 *
 * @return string
 */
function whpl_plugin_basename() {
	return plugin_basename( WHPL_PLUGIN_FILE );
}

/**
 * Fetch the latest GitHub release (cached for 12 hours).
 *
 * @return array|null { tag: string, package: string, url: string } or null if unavailable.
 */
function whpl_get_latest_release() {
	$cached = get_transient( 'whpl_latest_release' );
	if ( is_array( $cached ) ) {
		return empty( $cached ) ? null : $cached;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . WHPL_GITHUB_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'warehouse-picklist/' . WHPL_VERSION,
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// Negative cache so a missing repo or rate limit doesn't hammer the API.
		set_transient( 'whpl_latest_release', array(), HOUR_IN_SECONDS );
		return null;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['tag_name'] ) ) {
		set_transient( 'whpl_latest_release', array(), HOUR_IN_SECONDS );
		return null;
	}

	// Prefer an uploaded .zip asset (has the correct plugin folder name);
	// fall back to the auto-generated zipball.
	$package = '';
	if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
		foreach ( $body['assets'] as $asset ) {
			if ( ! empty( $asset['browser_download_url'] ) && '.zip' === substr( $asset['browser_download_url'], -4 ) ) {
				$package = $asset['browser_download_url'];
				break;
			}
		}
	}
	if ( '' === $package && ! empty( $body['zipball_url'] ) ) {
		$package = $body['zipball_url'];
	}

	$release = array(
		'tag'     => (string) $body['tag_name'],
		'package' => $package,
		'url'     => ! empty( $body['html_url'] ) ? $body['html_url'] : 'https://github.com/' . WHPL_GITHUB_REPO,
	);

	set_transient( 'whpl_latest_release', $release, 12 * HOUR_IN_SECONDS );

	return $release;
}

add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( whpl_plugin_basename() !== $plugin_file ) {
		return $update;
	}

	$release = whpl_get_latest_release();
	if ( ! $release || '' === $release['package'] ) {
		return $update;
	}

	$version = ltrim( $release['tag'], 'vV' );
	if ( version_compare( $version, $plugin_data['Version'], '<=' ) ) {
		return $update;
	}

	return array(
		'id'      => 'https://github.com/' . WHPL_GITHUB_REPO,
		'slug'    => 'warehouse-picklist',
		'plugin'  => $plugin_file,
		'version' => $version,
		'url'     => $release['url'],
		'package' => $release['package'],
	);
}, 10, 3 );

/**
 * GitHub zipballs extract to an `alhoniko-warehouse-picklist-…-<hash>` folder,
 * which would change the plugin directory on update. Rename it back to the
 * currently installed folder name.
 */
add_filter( 'upgrader_source_selection', function ( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['plugin'] ) || whpl_plugin_basename() !== $hook_extra['plugin'] ) {
		return $source;
	}

	$plugin_dir = dirname( whpl_plugin_basename() );
	if ( '.' === $plugin_dir ) {
		return $source;
	}

	global $wp_filesystem;

	$desired = trailingslashit( $remote_source ) . $plugin_dir;
	if ( untrailingslashit( $source ) === $desired ) {
		return $source;
	}

	if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), $desired ) ) {
		return trailingslashit( $desired );
	}

	return $source;
}, 10, 4 );

/**
 * Clear the release cache when the user clicks "Check again" on the
 * Updates screen, so a fresh release is picked up immediately.
 */
add_action( 'load-update-core.php', function () {
	if ( isset( $_GET['force-check'] ) ) {
		delete_transient( 'whpl_latest_release' );
	}
} );

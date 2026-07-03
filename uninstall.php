<?php
/**
 * Clean up plugin options, roles and capabilities on uninstall.
 * Per-order pick history (_whpl_* order meta) is left in place.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'whpl_settings' );
delete_option( 'whpl_category_order' );
delete_option( 'whpl_version' );
delete_transient( 'whpl_latest_release' );

remove_role( 'whpl_picker' );

foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		$role->remove_cap( 'whpl_pick' );
	}
}

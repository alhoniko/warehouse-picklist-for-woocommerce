<?php
/**
 * Picking capability and the Warehouse Picker role.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Can the current user use pick mode?
 *
 * @return bool
 */
function whpl_user_can_pick() {
	return current_user_can( 'whpl_pick' ) || current_user_can( 'manage_woocommerce' );
}

/**
 * One-time upgrade routine per plugin version: registers the picker role
 * and grants the capability to store managers. Runs on plugins_loaded so
 * it also fires on installs deployed by file copy (no activation hook).
 */
add_action( 'plugins_loaded', function () {
	if ( get_option( 'whpl_version' ) === WHPL_VERSION ) {
		return;
	}

	if ( ! get_role( 'whpl_picker' ) ) {
		add_role( 'whpl_picker', __( 'Warehouse Picker', 'warehouse-picklist' ), array(
			'read'      => true,
			'whpl_pick' => true,
		) );
	}

	foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role && ! $role->has_cap( 'whpl_pick' ) ) {
			$role->add_cap( 'whpl_pick' );
		}
	}

	update_option( 'whpl_version', WHPL_VERSION );
}, 20 );

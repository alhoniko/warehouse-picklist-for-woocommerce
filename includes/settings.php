<?php
/**
 * Admin page (Settings + Category order tabs) and settings storage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get plugin settings merged with defaults.
 *
 * @return array
 */
function whpl_get_settings() {
	$defaults = array(
		'logo_id'          => 0,
		'business_name'    => get_bloginfo( 'name' ),
		'footer_note'      => '',
		'show_sku'         => 1,
		'show_checkboxes'  => 1,
		'package_meta_key' => '',
	);

	$saved = get_option( WHPL_SETTINGS_OPTION, array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Warehouse Picklist', 'warehouse-picklist' ),
		__( 'Picklist', 'warehouse-picklist' ),
		'manage_woocommerce',
		'whpl-picklist',
		'whpl_render_admin_page'
	);
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( strpos( (string) $hook, 'whpl-picklist' ) === false ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script(
		'whpl-admin',
		WHPL_PLUGIN_URL . 'assets/admin.js',
		array( 'jquery', 'jquery-ui-sortable' ),
		WHPL_VERSION,
		true
	);
	wp_localize_script( 'whpl-admin', 'whplAdmin', array(
		'nonce'       => wp_create_nonce( 'whpl_admin' ),
		'i18n'        => array(
			'saving'     => __( 'Saving…', 'warehouse-picklist' ),
			'saved'      => __( 'Saved.', 'warehouse-picklist' ),
			'error'      => __( 'Error saving.', 'warehouse-picklist' ),
			'selectLogo' => __( 'Select logo', 'warehouse-picklist' ),
		),
	) );
} );

/**
 * Handle the settings form submit.
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['whpl_settings_nonce'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	check_admin_referer( 'whpl_save_settings', 'whpl_settings_nonce' );

	$settings = array(
		'logo_id'          => isset( $_POST['whpl_logo_id'] ) ? absint( $_POST['whpl_logo_id'] ) : 0,
		'business_name'    => isset( $_POST['whpl_business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['whpl_business_name'] ) ) : '',
		'footer_note'      => isset( $_POST['whpl_footer_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['whpl_footer_note'] ) ) : '',
		'show_sku'         => empty( $_POST['whpl_show_sku'] ) ? 0 : 1,
		'show_checkboxes'  => empty( $_POST['whpl_show_checkboxes'] ) ? 0 : 1,
		'package_meta_key' => isset( $_POST['whpl_package_meta_key'] ) ? sanitize_text_field( wp_unslash( $_POST['whpl_package_meta_key'] ) ) : '',
	);

	update_option( WHPL_SETTINGS_OPTION, $settings );
	add_settings_error( 'whpl', 'whpl_saved', __( 'Settings saved.', 'warehouse-picklist' ), 'success' );
} );

/**
 * Render the admin page with tabs.
 */
function whpl_render_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
	$base_url = admin_url( 'admin.php?page=whpl-picklist' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Warehouse Picklist', 'warehouse-picklist' ); ?></h1>

		<nav class="nav-tab-wrapper" style="margin-bottom:16px;">
			<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Settings', 'warehouse-picklist' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'order', $base_url ) ); ?>" class="nav-tab <?php echo 'order' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Category order', 'warehouse-picklist' ); ?>
			</a>
		</nav>

		<?php
		settings_errors( 'whpl' );

		if ( 'order' === $tab ) {
			whpl_render_category_order_tab();
		} else {
			whpl_render_settings_tab();
		}
		?>
	</div>
	<?php
}

/**
 * Render the settings tab.
 */
function whpl_render_settings_tab() {
	$settings = whpl_get_settings();
	$logo_url = $settings['logo_id'] ? wp_get_attachment_image_url( $settings['logo_id'], 'medium' ) : '';
	?>
	<form method="post">
		<?php wp_nonce_field( 'whpl_save_settings', 'whpl_settings_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Logo', 'warehouse-picklist' ); ?></th>
				<td>
					<input type="hidden" name="whpl_logo_id" id="whpl-logo-id" value="<?php echo esc_attr( $settings['logo_id'] ); ?>">
					<div id="whpl-logo-preview" style="margin-bottom:8px;">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" style="max-height:60px;max-width:240px;" alt="">
						<?php endif; ?>
					</div>
					<button type="button" class="button" id="whpl-logo-select"><?php esc_html_e( 'Select logo', 'warehouse-picklist' ); ?></button>
					<button type="button" class="button" id="whpl-logo-remove" <?php echo $settings['logo_id'] ? '' : 'style="display:none;"'; ?>>
						<?php esc_html_e( 'Remove logo', 'warehouse-picklist' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'Shown at the top of the printed pick list.', 'warehouse-picklist' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="whpl-business-name"><?php esc_html_e( 'Business name', 'warehouse-picklist' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="whpl-business-name" name="whpl_business_name" value="<?php echo esc_attr( $settings['business_name'] ); ?>">
					<p class="description"><?php esc_html_e( 'Printed as a text logo in the header when no logo image is set. Defaults to the site title.', 'warehouse-picklist' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="whpl-footer-note"><?php esc_html_e( 'Footer note', 'warehouse-picklist' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="whpl-footer-note" name="whpl_footer_note"><?php echo esc_textarea( $settings['footer_note'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Free-form text printed at the bottom of the pick list, e.g. a thank-you note for shipment inserts.', 'warehouse-picklist' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Columns', 'warehouse-picklist' ); ?></th>
				<td>
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="whpl_show_sku" value="1" <?php checked( $settings['show_sku'] ); ?>>
						<?php esc_html_e( 'Show SKU column', 'warehouse-picklist' ); ?>
					</label>
					<label style="display:block;">
						<input type="checkbox" name="whpl_show_checkboxes" value="1" <?php checked( $settings['show_checkboxes'] ); ?>>
						<?php esc_html_e( 'Show pick checkboxes', 'warehouse-picklist' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="whpl-package-meta-key"><?php esc_html_e( 'Package size meta key', 'warehouse-picklist' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="whpl-package-meta-key" name="whpl_package_meta_key" value="<?php echo esc_attr( $settings['package_meta_key'] ); ?>" placeholder="product_package_size">
					<p class="description"><?php esc_html_e( 'Product meta key for a package size column (ACF field names work). Leave empty to hide the column.', 'warehouse-picklist' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save settings', 'warehouse-picklist' ) ); ?>
	</form>
	<?php
}

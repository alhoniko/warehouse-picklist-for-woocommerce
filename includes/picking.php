<?php
/**
 * Tablet pick mode: order queue, tap-to-pick view, and the audit trail
 * (per-item pick meta + WooCommerce order notes).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get per-item pick states for an order.
 *
 * @param WC_Order $order Order.
 * @return array<int, array{status: string, user_id: int, user: string, time: int}>
 */
function whpl_get_picks( $order ) {
	$picks = $order->get_meta( '_whpl_picks' );

	return is_array( $picks ) ? $picks : array();
}

/**
 * Pick progress counters for an order.
 *
 * @param WC_Order $order Order.
 * @return array{total: int, picked: int, missing: int, resolved: int}
 */
function whpl_pick_progress( $order ) {
	$picks  = whpl_get_picks( $order );
	$total  = count( $order->get_items() );
	$picked = 0;
	$missing = 0;

	foreach ( $order->get_items() as $item_id => $item ) {
		$status = isset( $picks[ $item_id ]['status'] ) ? $picks[ $item_id ]['status'] : '';
		if ( 'picked' === $status ) {
			$picked++;
		} elseif ( 'missing' === $status ) {
			$missing++;
		}
	}

	return array(
		'total'    => $total,
		'picked'   => $picked,
		'missing'  => $missing,
		'resolved' => $picked + $missing,
	);
}

/**
 * Set (or clear) the pick state of one order item.
 *
 * @param WC_Order $order   Order.
 * @param int      $item_id Order item ID.
 * @param string   $status  'picked', 'missing' or '' to clear.
 */
function whpl_set_pick( $order, $item_id, $status ) {
	$picks = whpl_get_picks( $order );

	if ( '' === $status ) {
		unset( $picks[ $item_id ] );
	} else {
		$user = wp_get_current_user();

		$picks[ $item_id ] = array(
			'status'  => $status,
			'user_id' => (int) $user->ID,
			'user'    => $user->display_name,
			'time'    => time(),
		);
	}

	$order->update_meta_data( '_whpl_picks', $picks );
	if ( ! $order->get_meta( '_whpl_pick_started' ) ) {
		$order->update_meta_data( '_whpl_pick_started', time() );
	}
	$order->save();
}

/**
 * Mark the whole order as picked: completion meta + order note.
 *
 * @param WC_Order $order Order.
 */
function whpl_complete_pick( $order ) {
	$progress = whpl_pick_progress( $order );
	$user     = wp_get_current_user();

	$order->update_meta_data( '_whpl_pick_completed', array(
		'user_id' => (int) $user->ID,
		'user'    => $user->display_name,
		'time'    => time(),
	) );
	$order->save();

	$note = sprintf(
		/* translators: 1: picked row count, 2: total row count */
		__( 'Picking completed: %1$d of %2$d rows picked', 'warehouse-picklist' ),
		$progress['picked'],
		$progress['total']
	);

	if ( $progress['missing'] > 0 ) {
		$picks   = whpl_get_picks( $order );
		$missing = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( isset( $picks[ $item_id ]['status'] ) && 'missing' === $picks[ $item_id ]['status'] ) {
				$missing[] = $item->get_name();
			}
		}
		$note .= sprintf(
			/* translators: %s: comma-separated product names */
			__( ', missing: %s', 'warehouse-picklist' ),
			implode( ', ', $missing )
		);
	}

	$started = (int) $order->get_meta( '_whpl_pick_started' );
	if ( $started ) {
		$note .= sprintf( ' · %d min', max( 1, round( ( time() - $started ) / 60 ) ) );
	}

	$order->add_order_note( $note . ' · ' . $user->display_name );

	/**
	 * Fires when an order has been marked as picked.
	 *
	 * @param WC_Order $order    Order.
	 * @param array    $progress Progress counters (total/picked/missing/resolved).
	 */
	do_action( 'whpl_pick_completed', $order, $progress );
}

/**
 * Reopen a completed pick.
 *
 * @param WC_Order $order Order.
 */
function whpl_reopen_pick( $order ) {
	$order->delete_meta_data( '_whpl_pick_completed' );
	$order->save();

	$user = wp_get_current_user();
	$order->add_order_note( __( 'Picking reopened.', 'warehouse-picklist' ) . ' · ' . $user->display_name );
}

/**
 * URL of the picking queue view.
 *
 * @return string
 */
function whpl_pick_queue_url() {
	return admin_url( 'admin-post.php?action=whpl_pick_queue' );
}

// Logged-out users hitting the pick views are sent to wp-login and back.
add_action( 'admin_post_nopriv_whpl_pick_queue', 'auth_redirect' );
add_action( 'admin_post_nopriv_whpl_pick', 'auth_redirect' );

/**
 * Capability gate for pick views and AJAX.
 */
function whpl_pick_auth() {
	if ( ! whpl_user_can_pick() ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'warehouse-picklist' ) );
	}
}

// "Picking" link under the WooCommerce menu (a plain link to the queue view).
add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Picking', 'warehouse-picklist' ),
		__( 'Picking', 'warehouse-picklist' ),
		'whpl_pick',
		whpl_pick_queue_url()
	);
}, 20 );

/**
 * Shared <head> boilerplate for the standalone pick views.
 *
 * @param string $title Page title.
 */
function whpl_pick_view_head( $title ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo esc_html( $title ); ?></title>
		<style>
			* { box-sizing: border-box; }
			body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f4f5f7; color: #1d2327; }
			.bar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #dcdcde; }
			.bar .back { font-size: 22px; text-decoration: none; color: #1d2327; padding: 4px 10px; }
			.bar .who { flex: 1; min-width: 0; }
			.bar .who strong { display: block; font-size: 16px; }
			.bar .who span { display: block; font-size: 13px; color: #646970; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.bar .count { font-size: 18px; font-weight: 700; white-space: nowrap; }
			main { max-width: 760px; margin: 0 auto; padding: 12px 12px 96px; }
			h2 { position: sticky; top: 60px; z-index: 5; font-size: 14px; text-transform: uppercase; letter-spacing: .03em; color: #646970; margin: 20px 0 8px; padding: 6px 4px; background: #f4f5f7; }
			.row { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 12px; margin-bottom: 8px; min-height: 60px; cursor: pointer; -webkit-tap-highlight-color: transparent; }
			.row .tick { position: relative; flex: 0 0 28px; height: 28px; border: 2px solid #8c8f94; border-radius: 6px; text-align: center; line-height: 24px; font-size: 18px; color: transparent; }
			.row .qty { flex: 0 0 44px; font-size: 20px; font-weight: 700; text-align: center; }
			.row .info { flex: 1; min-width: 0; }
			.row .info .name { font-size: 15px; font-weight: 600; }
			.row .info .sub { font-size: 13px; color: #646970; }
			.row .miss { flex: 0 0 auto; height: 44px; padding: 0 14px; border: 1px solid #dcdcde; border-radius: 999px; background: #f6f7f7; font-size: 14px; font-weight: 600; color: #996800; cursor: pointer; white-space: nowrap; }
			.row[data-status="picked"] { background: #e7f6ec; border-color: #7ad03a; }
			.row[data-status="picked"] .tick { background: #2fb344; border-color: #2fb344; color: #fff; }
			.row[data-status="missing"] { background: #fdecea; border-color: #d63638; }
			.row[data-status="missing"] .tick { border-color: #d63638; }
			.row[data-status="missing"] .tick::after { content: "!"; position: absolute; inset: 0; line-height: 24px; color: #d63638; }
			.row[data-status="missing"] .miss { background: #d63638; border-color: #d63638; color: #fff; }
			#whpl-complete.ready { background: #2fb344; }
			.actionbar { position: fixed; left: 0; right: 0; bottom: 0; background: #fff; border-top: 1px solid #dcdcde; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); }
			.actionbar .inner { max-width: 760px; margin: 0 auto; display: flex; align-items: center; gap: 12px; }
			.progress-track { flex: 1; height: 8px; background: #dcdcde; border-radius: 4px; overflow: hidden; }
			.progress-fill { height: 100%; width: 0; background: #2fb344; transition: width .2s; }
			.btn { border: 0; border-radius: 8px; padding: 12px 18px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
			.btn-primary { background: #2271b1; color: #fff; }
			.btn-secondary { background: #f0f0f1; color: #1d2327; }
			.banner { background: #e7f6ec; border: 1px solid #7ad03a; border-radius: 10px; padding: 12px 16px; margin: 12px 0; font-size: 14px; }
			.locked .row, .locked .row .miss { pointer-events: none; opacity: .85; cursor: default; }
			.queue-item { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 14px; margin-bottom: 8px; text-decoration: none; color: inherit; min-height: 64px; }
			.queue-item .num { font-size: 16px; font-weight: 700; }
			.queue-item .sub { font-size: 13px; color: #646970; }
			.queue-item .grow { flex: 1; min-width: 0; }
			.badge { font-size: 13px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: #f0f0f1; white-space: nowrap; }
			.badge.done { background: #e7f6ec; color: #1a7a2e; }
			.empty { text-align: center; color: #646970; padding: 48px 16px; }
			.toast { position: fixed; bottom: 84px; left: 50%; transform: translateX(-50%); background: #d63638; color: #fff; padding: 10px 16px; border-radius: 8px; font-size: 14px; display: none; z-index: 20; }
		</style>
	</head>
	<body>
	<?php
}

/**
 * Picking queue: processing orders, unpicked first, oldest first.
 */
add_action( 'admin_post_whpl_pick_queue', function () {
	whpl_pick_auth();

	$statuses = apply_filters( 'whpl_pick_queue_statuses', array( 'processing' ) );
	$orders   = wc_get_orders( array(
		'status'  => $statuses,
		'limit'   => 50,
		'orderby' => 'date',
		'order'   => 'ASC',
	) );

	usort( $orders, function ( $a, $b ) {
		$a_done = (int) (bool) $a->get_meta( '_whpl_pick_completed' );
		$b_done = (int) (bool) $b->get_meta( '_whpl_pick_completed' );
		return $a_done - $b_done;
	} );

	whpl_pick_view_head( __( 'Picking', 'warehouse-picklist' ) );
	?>
	<header class="bar">
		<div class="who">
			<strong><?php esc_html_e( 'Picking', 'warehouse-picklist' ); ?></strong>
			<span><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
		</div>
		<div class="count"><?php echo esc_html( count( $orders ) ); ?></div>
	</header>
	<main>
		<?php if ( empty( $orders ) ) : ?>
			<p class="empty"><?php esc_html_e( 'No orders waiting to be picked.', 'warehouse-picklist' ); ?></p>
		<?php endif; ?>

		<?php foreach ( $orders as $order ) :
			$progress  = whpl_pick_progress( $order );
			$completed = $order->get_meta( '_whpl_pick_completed' );
			$url       = admin_url( 'admin-post.php?action=whpl_pick&order_id=' . $order->get_id() );
			?>
			<a class="queue-item" href="<?php echo esc_url( $url ); ?>">
				<div class="grow">
					<div class="num">#<?php echo esc_html( $order->get_order_number() ); ?> &middot; <?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></div>
					<div class="sub">
						<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
						&middot; <?php echo esc_html( sprintf(
							/* translators: %s: number of order rows */
							__( 'Items: %s', 'warehouse-picklist' ),
							$progress['total']
						) ); ?>
					</div>
				</div>
				<?php if ( $completed ) : ?>
					<span class="badge done"><?php esc_html_e( 'Picked', 'warehouse-picklist' ); ?> &#10003;</span>
				<?php elseif ( $progress['resolved'] > 0 ) : ?>
					<span class="badge"><?php echo esc_html( $progress['resolved'] . '/' . $progress['total'] ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</main>
	</body>
	</html>
	<?php
	exit;
} );

/**
 * The tap-to-pick view for one order.
 */
add_action( 'admin_post_whpl_pick', function () {
	whpl_pick_auth();

	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		wp_die( esc_html__( 'Order not found.', 'warehouse-picklist' ) );
	}

	$collected = whpl_collect_picklist_rows( $order );
	$progress  = whpl_pick_progress( $order );
	$completed = $order->get_meta( '_whpl_pick_completed' );
	$print_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=whpl_print_picklist&order_id=' . $order->get_id() ),
		'whpl_print_picklist_' . $order->get_id()
	);

	$title = sprintf(
		/* translators: %s: order number */
		__( 'Picking — Order #%s', 'warehouse-picklist' ),
		$order->get_order_number()
	);

	$render_rows = function ( $rows ) {
		foreach ( $rows as $row ) :
			// Product names often already end with the package size — don't repeat it.
			$package = $row['package'];
			if ( '' !== $package && false !== stripos( $row['name'], $package ) ) {
				$package = '';
			}
			?>
			<div class="row" data-item="<?php echo esc_attr( $row['item_id'] ); ?>" data-status="<?php echo esc_attr( $row['pick'] ); ?>" role="button" tabindex="0">
				<span class="tick">&#10003;</span>
				<span class="qty"><?php echo esc_html( $row['qty'] ); ?></span>
				<span class="info">
					<span class="name"><?php echo esc_html( $row['name'] ); ?></span>
					<span class="sub"><?php echo esc_html( trim( $package . ( $package && $row['sku'] ? ' · ' : '' ) . $row['sku'] ) ); ?></span>
				</span>
				<button type="button" class="miss" title="<?php esc_attr_e( 'Mark as missing', 'warehouse-picklist' ); ?>"><?php esc_html_e( 'Missing', 'warehouse-picklist' ); ?></button>
			</div>
			<?php
		endforeach;
	};

	whpl_pick_view_head( $title );
	?>
	<header class="bar">
		<a class="back" href="<?php echo esc_url( whpl_pick_queue_url() ); ?>" aria-label="<?php esc_attr_e( 'Back to queue', 'warehouse-picklist' ); ?>">&#8592;</a>
		<div class="who">
			<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
			<span><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></span>
		</div>
		<div class="count" id="whpl-count"><?php echo esc_html( $progress['resolved'] . '/' . $progress['total'] ); ?></div>
	</header>
	<main class="<?php echo $completed ? 'locked' : ''; ?>" id="whpl-main">
		<?php if ( ! empty( $completed['user'] ) ) : ?>
			<div class="banner">
				<?php echo esc_html( sprintf(
					/* translators: 1: name, 2: date */
					__( 'Picking completed by %1$s · %2$s', 'warehouse-picklist' ),
					$completed['user'],
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $completed['time'] )
				) ); ?>
			</div>
		<?php endif; ?>

		<?php foreach ( $collected['category_order'] as $term_id ) :
			if ( empty( $collected['buckets'][ $term_id ] ) ) {
				continue;
			}
			?>
			<h2><?php echo esc_html( isset( $collected['terms_by_id'][ $term_id ] ) ? $collected['terms_by_id'][ $term_id ] : '' ); ?></h2>
			<?php $render_rows( $collected['buckets'][ $term_id ] ); ?>
		<?php endforeach; ?>

		<?php if ( ! empty( $collected['other'] ) ) : ?>
			<h2><?php esc_html_e( 'Other', 'warehouse-picklist' ); ?></h2>
			<?php $render_rows( $collected['other'] ); ?>
		<?php endif; ?>
	</main>
	<footer class="actionbar">
		<div class="inner">
			<?php if ( $completed ) : ?>
				<a class="btn btn-secondary" href="<?php echo esc_url( $print_url ); ?>" target="_blank"><?php esc_html_e( 'Print pick list', 'warehouse-picklist' ); ?></a>
				<button type="button" class="btn btn-secondary" id="whpl-reopen"><?php esc_html_e( 'Reopen picking', 'warehouse-picklist' ); ?></button>
			<?php else : ?>
				<div class="progress-track"><div class="progress-fill" id="whpl-fill"></div></div>
				<button type="button" class="btn btn-primary" id="whpl-complete"><?php esc_html_e( 'Mark order as picked', 'warehouse-picklist' ); ?></button>
			<?php endif; ?>
		</div>
	</footer>
	<div class="toast" id="whpl-toast"><?php esc_html_e( 'Connection error, try again.', 'warehouse-picklist' ); ?></div>

	<script>
	(function () {
		'use strict';

		var cfg = {
			ajax: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			nonce: <?php echo wp_json_encode( wp_create_nonce( 'whpl_pick' ) ); ?>,
			orderId: <?php echo (int) $order->get_id(); ?>,
			total: <?php echo (int) $progress['total']; ?>,
			resolved: <?php echo (int) $progress['resolved']; ?>,
			confirmText: <?php echo wp_json_encode( __( 'Some rows are not resolved yet. Complete anyway?', 'warehouse-picklist' ) ); ?>,
			locked: <?php echo $completed ? 'true' : 'false'; ?>
		};

		var toastTimer;
		function toast() {
			var el = document.getElementById('whpl-toast');
			el.style.display = 'block';
			clearTimeout(toastTimer);
			toastTimer = setTimeout(function () { el.style.display = 'none'; }, 2500);
		}

		function post(action, data) {
			var body = new URLSearchParams(data || {});
			body.set('action', action);
			body.set('nonce', cfg.nonce);
			body.set('order_id', cfg.orderId);
			return fetch(cfg.ajax, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			}).then(function (r) { return r.json(); });
		}

		function refresh(progress) {
			cfg.resolved = progress.resolved;
			var count = document.getElementById('whpl-count');
			if (count) { count.textContent = progress.resolved + '/' + progress.total; }
			var fill = document.getElementById('whpl-fill');
			if (fill) { fill.style.width = (progress.total ? Math.round(100 * progress.resolved / progress.total) : 0) + '%'; }
			var complete = document.getElementById('whpl-complete');
			if (complete) { complete.classList.toggle('ready', progress.total > 0 && progress.resolved >= progress.total); }
		}

		function localProgress() {
			var resolved = 0;
			document.querySelectorAll('.row').forEach(function (r) {
				if (r.dataset.status === 'picked' || r.dataset.status === 'missing') { resolved++; }
			});
			return { resolved: resolved, total: cfg.total };
		}

		function setPick(row, status) {
			var prev = row.dataset.status;
			// Optimistic: flip the row immediately, revert if the save fails.
			row.dataset.status = status;
			refresh(localProgress());

			post('whpl_set_pick', { item_id: row.dataset.item, status: status }).then(function (resp) {
				if (resp && resp.success) {
					row.dataset.status = resp.data.status;
					refresh(resp.data.progress);
				} else {
					row.dataset.status = prev;
					refresh(localProgress());
					toast();
				}
			}).catch(function () {
				row.dataset.status = prev;
				refresh(localProgress());
				toast();
			});
		}

		if (!cfg.locked) {
			document.querySelectorAll('.row').forEach(function (row) {
				row.addEventListener('click', function () {
					setPick(row, row.dataset.status === 'picked' ? '' : 'picked');
				});
				row.querySelector('.miss').addEventListener('click', function (e) {
					e.stopPropagation();
					setPick(row, row.dataset.status === 'missing' ? '' : 'missing');
				});
			});

			refresh({ resolved: cfg.resolved, total: cfg.total });

			document.getElementById('whpl-complete').addEventListener('click', function () {
				if (cfg.resolved < cfg.total && !window.confirm(cfg.confirmText)) {
					return;
				}
				post('whpl_complete_pick').then(function (resp) {
					if (resp && resp.success) { window.location.reload(); } else { toast(); }
				}).catch(toast);
			});
		} else {
			var reopen = document.getElementById('whpl-reopen');
			if (reopen) {
				reopen.addEventListener('click', function () {
					post('whpl_reopen_pick').then(function (resp) {
						if (resp && resp.success) { window.location.reload(); } else { toast(); }
					}).catch(toast);
				});
			}
		}
	})();
	</script>
	</body>
	</html>
	<?php
	exit;
} );

/**
 * AJAX: set one item's pick state.
 */
add_action( 'wp_ajax_whpl_set_pick', function () {
	check_ajax_referer( 'whpl_pick', 'nonce' );
	if ( ! whpl_user_can_pick() ) {
		wp_send_json_error();
	}

	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
	$status   = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';

	$order = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order || ! $order->get_item( $item_id ) || ! in_array( $status, array( 'picked', 'missing', '' ), true ) ) {
		wp_send_json_error();
	}

	whpl_set_pick( $order, $item_id, $status );

	wp_send_json_success( array(
		'status'   => $status,
		'progress' => whpl_pick_progress( $order ),
	) );
} );

/**
 * AJAX: mark the order as picked.
 */
add_action( 'wp_ajax_whpl_complete_pick', function () {
	check_ajax_referer( 'whpl_pick', 'nonce' );
	if ( ! whpl_user_can_pick() ) {
		wp_send_json_error();
	}

	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		wp_send_json_error();
	}

	whpl_complete_pick( $order );
	wp_send_json_success();
} );

/**
 * AJAX: reopen a completed pick.
 */
add_action( 'wp_ajax_whpl_reopen_pick', function () {
	check_ajax_referer( 'whpl_pick', 'nonce' );
	if ( ! whpl_user_can_pick() ) {
		wp_send_json_error();
	}

	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		wp_send_json_error();
	}

	whpl_reopen_pick( $order );
	wp_send_json_success();
} );

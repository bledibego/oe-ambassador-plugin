<?php
/**
 * Commission engine.
 *
 * Hooks into WooCommerce order status changes. When an order reaches the
 * configured trigger status (default: "completed"), checks whether any coupon
 * used belongs to an ambassador and if so records the commission.
 *
 * NET formula: order_total − tax − shipping  → then × tier %
 * This gives the "income" the store earns from the order, which the
 * ambassador earns a percentage of.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_Commission {

	private static ?OE_Amb_Commission $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		// Hook into order status changes
		$trigger = OE_Ambassador::setting( 'commission_trigger', 'completed' );
		add_action( "woocommerce_order_status_{$trigger}", [ $this, 'handle_order' ], 10, 2 );

		// If order is refunded / cancelled, cancel the commission
		add_action( 'woocommerce_order_status_refunded',  [ $this, 'cancel_commission' ], 10, 2 );
		add_action( 'woocommerce_order_status_cancelled',  [ $this, 'cancel_commission' ], 10, 2 );

		// Admin AJAX: approve / mark-paid a commission
		add_action( 'wp_ajax_oe_amb_approve_commission', [ $this, 'ajax_approve_commission' ] );
		add_action( 'wp_ajax_oe_amb_create_payout',      [ $this, 'ajax_create_payout' ] );
	}

	// ── Order handling ────────────────────────────────────────────────────────

	/**
	 * Called when an order reaches the trigger status.
	 *
	 * @param int      $order_id
	 * @param WC_Order $order
	 */
	public function handle_order( int $order_id, WC_Order $order ): void {
		// Skip if already recorded
		if ( OE_Amb_DB::get_commission_by_order( $order_id ) ) {
			return;
		}

		// Find ambassador from coupons used on this order
		$ambassador_id = OE_Amb_Coupon::get_ambassador_id_from_order( $order );
		if ( ! $ambassador_id ) {
			return;
		}

		$ambassador = OE_Amb_Ambassador::find( $ambassador_id );
		if ( ! $ambassador || ! $ambassador->is_approved() ) {
			return;
		}

		// ── Financial breakdown ───────────────────────────────────────────────
		$order_total     = (float) $order->get_total();          // total paid (incl tax, shipping, after discount)
		$tax_amount      = (float) $order->get_total_tax();
		$shipping_amount = (float) $order->get_shipping_total();
		$discount_amount = (float) $order->get_discount_total(); // already subtracted from total, but track it

		// NET = what the store actually earns on products (excl. tax & shipping)
		$net_amount = $order_total - $tax_amount - $shipping_amount;
		$net_amount = max( 0.0, $net_amount );

		// ── Tier determination ────────────────────────────────────────────────
		// Count how many commissions this ambassador has THIS calendar month
		// (including the one we're about to insert = so +1 to the query result)
		$month_start  = wp_date( 'Y-m-01 00:00:00' );
		$month_end    = wp_date( 'Y-m-t 23:59:59' );
		$monthly_count = OE_Amb_DB::count_monthly_commissions( $ambassador_id, $month_start, $month_end );
		$monthly_count++; // this order is the next one

		$tier_pct   = OE_Ambassador::tier_pct( $monthly_count );
		$commission = round( $net_amount * ( $tier_pct / 100 ), 4 );

		// ── Store commission ──────────────────────────────────────────────────
		$commission_id = OE_Amb_DB::insert_commission( [
			'ambassador_id'   => $ambassador_id,
			'order_id'        => $order_id,
			'order_total'     => $order_total,
			'tax_amount'      => $tax_amount,
			'shipping_amount' => $shipping_amount,
			'discount_amount' => $discount_amount,
			'net_amount'      => $net_amount,
			'tier_pct'        => $tier_pct,
			'commission'      => $commission,
			'status'          => 'pending',
			'currency'        => $order->get_currency(),
			'order_date'      => $order->get_date_created()?->date( 'Y-m-d H:i:s' ) ?? current_time( 'mysql' ),
		] );

		if ( $commission_id ) {
			// Store the ambassador ID on the order for reference
			$order->update_meta_data( '_oe_amb_ambassador_id', $ambassador_id );
			$order->update_meta_data( '_oe_amb_commission_id', $commission_id );
			$order->save();

			do_action( 'oe_amb_commission_created', $commission_id, $ambassador_id, $order );
		}
	}

	/**
	 * Cancel commission when an order is refunded or cancelled.
	 */
	public function cancel_commission( int $order_id, WC_Order $order ): void {
		$commission = OE_Amb_DB::get_commission_by_order( $order_id );
		if ( $commission && $commission->status !== 'paid' ) {
			OE_Amb_DB::update_commission( (int) $commission->id, [ 'status' => 'cancelled' ] );
		}
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	/**
	 * Admin can approve a pending commission.
	 */
	public function ajax_approve_commission(): void {
		check_ajax_referer( 'oe_amb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_oe_ambassadors' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$id = absint( wp_unslash( $_POST['commission_id'] ?? 0 ) );
		if ( ! $id ) {
			wp_send_json_error( 'Invalid ID.' );
		}

		$ok = OE_Amb_DB::update_commission( $id, [ 'status' => 'approved' ] );
		wp_send_json_success( [ 'updated' => $ok ] );
	}

	/**
	 * Admin creates a payout for an ambassador for a given period.
	 * Marks all approved commissions as paid and creates a payout record.
	 */
	public function ajax_create_payout(): void {
		check_ajax_referer( 'oe_amb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_oe_ambassadors' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		// Pro feature: payout management
		if ( ! oe_amb_is_pro() ) {
			wp_send_json_error( esc_html__( 'Payout management requires Pro. Upgrade to unlock this feature.', 'oe-brand-ambassador-management' ) );
			return;
		}

		$ambassador_id = absint( wp_unslash( $_POST['ambassador_id'] ?? 0 ) );
		$date_from     = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
		$date_to       = sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) );
		$notes         = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

		if ( ! $ambassador_id || ! $date_from || ! $date_to ) {
			wp_send_json_error( 'Missing parameters.' );
		}

		// Fetch approved commissions in the period
		$result = OE_Amb_DB::get_commissions( $ambassador_id, [
			'status'    => 'approved',
			'date_from' => $date_from . ' 00:00:00',
			'date_to'   => $date_to   . ' 23:59:59',
			'per_page'  => 9999,
		] );

		if ( $result['total'] === 0 ) {
			wp_send_json_error( 'No approved commissions in that period.' );
		}

		$payout_id = OE_Amb_DB::insert_payout( [
			'ambassador_id' => $ambassador_id,
			'period_start'  => $date_from,
			'period_end'    => $date_to,
			'total_sales'   => $result['total'],
			'net_revenue'   => $result['sum_net'],
			'payout_amount' => $result['sum_commission'],
			'currency'      => OE_Ambassador::setting( 'currency', 'SEK' ),
			'status'        => 'pending',
			'notes'         => $notes,
		] );

		if ( ! $payout_id ) {
			wp_send_json_error( 'Failed to create payout record.' );
		}

		// Mark commissions as paid
		OE_Amb_DB::mark_commissions_paid(
			$ambassador_id,
			$date_from . ' 00:00:00',
			$date_to   . ' 23:59:59',
			$payout_id
		);

		// Trigger notification email
		do_action( 'oe_amb_payout_created', $payout_id, $ambassador_id );

		wp_send_json_success( [ 'payout_id' => $payout_id, 'amount' => $result['sum_commission'] ] );
	}
}

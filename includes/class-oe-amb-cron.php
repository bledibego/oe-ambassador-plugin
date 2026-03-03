<?php
/**
 * WP-Cron tasks for OE Ambassador.
 *
 * - Daily:   approve commissions older than N days (optional auto-approve).
 * - Monthly: send monthly performance report emails to all active ambassadors.
 * - Monthly: send admin summary of pending payouts.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_Cron {

	private static ?OE_Amb_Cron $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action( 'oe_amb_monthly_reports', [ $this, 'send_monthly_reports' ] );
		add_action( 'oe_amb_auto_approve',    [ $this, 'auto_approve_old_commissions' ] );
	}

	// ── Schedule / unschedule ─────────────────────────────────────────────────

	public static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'oe_amb_monthly_reports' ) ) {
			// Schedule to run on the 1st of next month at 08:00 UTC
			$next = mktime( 8, 0, 0, (int) date('n') + 1, 1 );
			wp_schedule_event( $next, 'monthly', 'oe_amb_monthly_reports' );
		}

		if ( ! wp_next_scheduled( 'oe_amb_auto_approve' ) ) {
			wp_schedule_event( time(), 'daily', 'oe_amb_auto_approve' );
		}

		// Register the 'monthly' interval if not present
		add_filter( 'cron_schedules', [ self::class, 'add_monthly_schedule' ] );
	}

	public static function unschedule_events(): void {
		wp_clear_scheduled_hook( 'oe_amb_monthly_reports' );
		wp_clear_scheduled_hook( 'oe_amb_auto_approve' );
	}

	public static function add_monthly_schedule( array $schedules ): array {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = [
				'interval' => MONTH_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'oe-ambassador' ),
			];
		}
		return $schedules;
	}

	// ── Monthly reports ───────────────────────────────────────────────────────

	/**
	 * Send monthly performance report to every approved ambassador.
	 * Runs on the 1st of each month for the previous month's data.
	 */
	public function send_monthly_reports(): void {
		// Previous month
		$prev_month  = date( 'Y-m', strtotime( 'first day of last month' ) );
		[ $y, $m ]   = explode( '-', $prev_month );
		$date_from   = "$y-$m-01 00:00:00";
		$date_to     = date( 'Y-m-t 23:59:59', mktime( 0, 0, 0, (int) $m, 1, (int) $y ) );

		$result = OE_Amb_DB::get_ambassadors( [ 'status' => 'approved', 'per_page' => 9999 ] );

		foreach ( $result['items'] as $row ) {
			$ambassador = OE_Amb_Ambassador::find( (int) $row->id );
			if ( ! $ambassador ) {
				continue;
			}

			$commissions = OE_Amb_DB::get_commissions( $ambassador->id, [
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'per_page'  => 9999,
			] );

			// Only email if there were sales
			if ( $commissions['total'] > 0 ) {
				OE_Amb_Email::send_monthly_report( $ambassador, $prev_month, $commissions );
			}
		}

		// Admin summary
		$pending_summary = OE_Amb_DB::get_commission_summary();
		if ( ! empty( $pending_summary ) ) {
			OE_Amb_Email::send_admin_monthly_summary( $pending_summary, $prev_month );
		}
	}

	// ── Auto-approve commissions ──────────────────────────────────────────────

	/**
	 * Optionally auto-approve commissions older than N days.
	 * Only runs if 'auto_approve_days' setting > 0.
	 */
	public function auto_approve_old_commissions(): void {
		$days = (int) OE_Ambassador::setting( 'auto_approve_days', 0 );
		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;
		$cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . OE_Amb_DB::com_table() .
			" SET status = 'approved'
			  WHERE status = 'pending' AND order_date < %s",
			$cutoff
		) );
	}
}

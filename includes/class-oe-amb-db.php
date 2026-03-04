<?php
/**
 * Database abstraction layer for OE Ambassador.
 *
 * All SQL goes through this class; the rest of the plugin uses
 * these static helpers so table names are only defined here.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_DB {

	// ── Table name helpers ────────────────────────────────────────────────────

	public static function amb_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'oe_ambassadors';
	}

	public static function com_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'oe_amb_commissions';
	}

	public static function pay_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'oe_amb_payouts';
	}

	// ── Ambassador CRUD ───────────────────────────────────────────────────────

	public static function get_ambassador( int $id ): ?object {
		global $wpdb;
		$table = self::amb_table();
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: null;
	}

	public static function get_ambassador_by_user( int $user_id ): ?object {
		global $wpdb;
		$table = self::amb_table();
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d", $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: null;
	}

	public static function get_ambassador_by_email( string $email ): ?object {
		global $wpdb;
		$table = self::amb_table();
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE email = %s", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: null;
	}

	public static function get_ambassador_by_coupon( string $code ): ?object {
		global $wpdb;
		$table = self::amb_table();
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE coupon_code = %s OR self_code = %s", strtolower( $code ), strtolower( $code ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: null;
	}

	/**
	 * List ambassadors with optional filtering.
	 *
	 * @param array{status?:string, search?:string, per_page?:int, offset?:int, orderby?:string, order?:string} $args
	 * @return array{items:object[], total:int}
	 */
	public static function get_ambassadors( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status'   => '',
			'search'   => '',
			'per_page' => 25,
			'offset'   => 0,
			'orderby'  => 'applied_at',
			'order'    => 'DESC',
		];
		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$params = [];

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR coupon_code LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			array_push( $params, $like, $like, $like, $like );
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$allowed_orderby = [ 'id', 'first_name', 'last_name', 'email', 'status', 'applied_at', 'approved_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'applied_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$table = self::amb_table();

		// Total count
		$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			: $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Paginated rows
		$limit_params = array_merge( $params, [ (int) $args['per_page'], (int) $args['offset'] ] );
		$rows_sql     = "SELECT * FROM `{$table}` {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items        = (array) $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$limit_params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return [ 'items' => $items, 'total' => $total ];
	}

	public static function insert_ambassador( array $data ): int|false {
		global $wpdb;
		$data['applied_at'] = current_time( 'mysql' );
		$result = $wpdb->insert( self::amb_table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $result ? $wpdb->insert_id : false;
	}

	public static function update_ambassador( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( self::amb_table(), $data, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function delete_ambassador( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::amb_table(), [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// ── Commission CRUD ───────────────────────────────────────────────────────

	public static function get_commission( int $id ): ?object {
		global $wpdb;
		$table = self::com_table();
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: null;
	}

	public static function get_commission_by_order( int $order_id ): ?object {
		global $wpdb;
		$table = self::com_table();
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE order_id = %d", $order_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: null;
	}

	public static function insert_commission( array $data ): int|false {
		global $wpdb;
		$result = $wpdb->insert( self::com_table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $result ? $wpdb->insert_id : false;
	}

	public static function update_commission( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( self::com_table(), $data, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Count commissions for an ambassador in the current calendar month.
	 */
	public static function count_monthly_commissions( int $ambassador_id, string $month_start, string $month_end ): int {
		global $wpdb;
		$table = self::com_table();
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE ambassador_id = %d AND status != 'cancelled' AND order_date BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ambassador_id,
				$month_start,
				$month_end
			)
		);
	}

	/**
	 * List commissions for an ambassador with optional period filter.
	 *
	 * @return array{items:object[], total:int, sum_commission:float, sum_net:float}
	 */
	public static function get_commissions( int $ambassador_id, array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status'     => '',
			'date_from'  => '',
			'date_to'    => '',
			'per_page'   => 25,
			'offset'     => 0,
		];
		$args  = wp_parse_args( $args, $defaults );
		$table = self::com_table();

		$where    = [ 'ambassador_id = %d' ];
		$params   = [ $ambassador_id ];

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'order_date >= %s';
			$params[] = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'order_date <= %s';
			$params[] = $args['date_to'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$sums_sql  = "SELECT SUM(commission) AS sc, SUM(net_amount) AS sn FROM `{$table}` {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sums      = $wpdb->get_row( $wpdb->prepare( $sums_sql, ...$params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$limit_params = array_merge( $params, [ (int) $args['per_page'], (int) $args['offset'] ] );
		$rows_sql     = "SELECT * FROM `{$table}` {$where_sql} ORDER BY order_date DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items        = (array) $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$limit_params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return [
			'items'          => $items,
			'total'          => $total,
			'sum_commission' => (float) ( $sums->sc ?? 0 ),
			'sum_net'        => (float) ( $sums->sn ?? 0 ),
		];
	}

	// ── Payout CRUD ───────────────────────────────────────────────────────────

	public static function insert_payout( array $data ): int|false {
		global $wpdb;
		$data['created_at'] = current_time( 'mysql' );
		$result = $wpdb->insert( self::pay_table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $result ? $wpdb->insert_id : false;
	}

	public static function get_payout( int $id ): ?object {
		global $wpdb;
		$table = self::pay_table();
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: null;
	}

	public static function update_payout( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( self::pay_table(), $data, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get payouts for a given ambassador.
	 */
	public static function get_payouts( int $ambassador_id, array $args = [] ): array {
		global $wpdb;
		$defaults = [ 'per_page' => 25, 'offset' => 0 ];
		$args  = wp_parse_args( $args, $defaults );
		$table = self::pay_table();

		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE ambassador_id = %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $ambassador_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$rows_sql = "SELECT * FROM `{$table}` WHERE ambassador_id = %d ORDER BY period_start DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items    = (array) $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows_sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$ambassador_id,
			(int) $args['per_page'],
			(int) $args['offset']
		) );

		return [ 'items' => $items, 'total' => $total ];
	}

	/**
	 * Mark all commissions in a date range for an ambassador as paid.
	 */
	public static function mark_commissions_paid( int $ambassador_id, string $date_from, string $date_to, int $payout_id ): int {
		global $wpdb;
		$table = self::com_table();
		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare(
				"UPDATE `{$table}` SET status = 'paid', paid_at = %s, payout_id = %d WHERE ambassador_id = %d AND status = 'approved' AND order_date BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' ),
				$payout_id,
				$ambassador_id,
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Aggregate pending/approved commission summary for all ambassadors (admin overview).
	 */
	public static function get_commission_summary(): array {
		global $wpdb;
		$at = self::amb_table();
		$ct = self::com_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$summary_sql = "SELECT a.id, a.first_name, a.last_name, a.email, a.coupon_code, a.status as amb_status,
		        COUNT(c.id) as total_orders, SUM(c.commission) as total_commission,
		        MAX(c.order_date) as last_sale
		 FROM `{$at}` a
		 LEFT JOIN `{$ct}` c ON a.id = c.ambassador_id AND c.status != 'cancelled'
		 WHERE a.status = 'approved'
		 GROUP BY a.id
		 ORDER BY total_commission DESC";
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $summary_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Count all non-rejected ambassadors (approved + pending + suspended).
	 * Used to enforce the free-plan ambassador limit.
	 */
	public static function count_active_ambassadors(): int {
		global $wpdb;
		$table = self::amb_table();
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			"SELECT COUNT(*) FROM `{$table}` WHERE status IN ('approved','pending','suspended')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}

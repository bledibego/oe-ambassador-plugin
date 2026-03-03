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
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::amb_table() . ' WHERE id = %d',
			$id
		) ) ?: null;
	}

	public static function get_ambassador_by_user( int $user_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::amb_table() . ' WHERE user_id = %d',
			$user_id
		) ) ?: null;
	}

	public static function get_ambassador_by_email( string $email ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::amb_table() . ' WHERE email = %s',
			$email
		) ) ?: null;
	}

	public static function get_ambassador_by_coupon( string $code ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::amb_table() . ' WHERE coupon_code = %s OR self_code = %s',
			strtolower( $code ),
			strtolower( $code )
		) ) ?: null;
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
		$count_sql = "SELECT COUNT(*) FROM $table $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		// Paginated rows
		$limit_params   = array_merge( $params, [ (int) $args['per_page'], (int) $args['offset'] ] );
		$rows_sql       = "SELECT * FROM $table $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$items          = (array) $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$limit_params ) );

		return [ 'items' => $items, 'total' => $total ];
	}

	public static function insert_ambassador( array $data ): int|false {
		global $wpdb;
		$data['applied_at'] = current_time( 'mysql' );
		$result = $wpdb->insert( self::amb_table(), $data );
		return $result ? $wpdb->insert_id : false;
	}

	public static function update_ambassador( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( self::amb_table(), $data, [ 'id' => $id ] );
	}

	public static function delete_ambassador( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::amb_table(), [ 'id' => $id ] );
	}

	// ── Commission CRUD ───────────────────────────────────────────────────────

	public static function get_commission( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::com_table() . ' WHERE id = %d',
			$id
		) ) ?: null;
	}

	public static function get_commission_by_order( int $order_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::com_table() . ' WHERE order_id = %d',
			$order_id
		) ) ?: null;
	}

	public static function insert_commission( array $data ): int|false {
		global $wpdb;
		$result = $wpdb->insert( self::com_table(), $data );
		return $result ? $wpdb->insert_id : false;
	}

	public static function update_commission( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( self::com_table(), $data, [ 'id' => $id ] );
	}

	/**
	 * Count commissions for an ambassador in the current calendar month.
	 */
	public static function count_monthly_commissions( int $ambassador_id, string $month_start, string $month_end ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::com_table() .
			" WHERE ambassador_id = %d AND status != 'cancelled'
			  AND order_date BETWEEN %s AND %s",
			$ambassador_id,
			$month_start,
			$month_end
		) );
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

		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where_sql", ...$params ) );
		$sums      = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(commission) AS sc, SUM(net_amount) AS sn FROM $table $where_sql", ...$params ) );

		$limit_params = array_merge( $params, [ (int) $args['per_page'], (int) $args['offset'] ] );
		$items        = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table $where_sql ORDER BY order_date DESC LIMIT %d OFFSET %d",
			...$limit_params
		) );

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
		$result = $wpdb->insert( self::pay_table(), $data );
		return $result ? $wpdb->insert_id : false;
	}

	public static function get_payout( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::pay_table() . ' WHERE id = %d',
			$id
		) ) ?: null;
	}

	public static function update_payout( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( self::pay_table(), $data, [ 'id' => $id ] );
	}

	/**
	 * Get payouts for a given ambassador.
	 */
	public static function get_payouts( int $ambassador_id, array $args = [] ): array {
		global $wpdb;
		$defaults = [ 'per_page' => 25, 'offset' => 0 ];
		$args = wp_parse_args( $args, $defaults );
		$table = self::pay_table();

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE ambassador_id = %d", $ambassador_id ) );
		$items = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE ambassador_id = %d ORDER BY period_start DESC LIMIT %d OFFSET %d",
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
		return (int) $wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::com_table() .
			" SET status = 'paid', paid_at = %s, payout_id = %d
			  WHERE ambassador_id = %d AND status = 'approved'
			  AND order_date BETWEEN %s AND %s",
			current_time( 'mysql' ),
			$payout_id,
			$ambassador_id,
			$date_from,
			$date_to
		) );
	}

	/**
	 * Aggregate pending/approved commission summary for all ambassadors (admin overview).
	 */
	public static function get_commission_summary(): array {
		global $wpdb;
		return (array) $wpdb->get_results(
			'SELECT a.id, a.first_name, a.last_name, a.email, a.coupon_code, a.status as amb_status,
			        COUNT(c.id) as total_orders, SUM(c.commission) as total_commission,
			        MAX(c.order_date) as last_sale
			 FROM ' . self::amb_table() . ' a
			 LEFT JOIN ' . self::com_table() . " c ON a.id = c.ambassador_id AND c.status != 'cancelled'
			 WHERE a.status = 'approved'
			 GROUP BY a.id
			 ORDER BY total_commission DESC"
		);
	}
}

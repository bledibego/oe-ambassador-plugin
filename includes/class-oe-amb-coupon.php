<?php
/**
 * WooCommerce coupon management for ambassadors.
 *
 * Each ambassador gets two coupons:
 *  1. customer_coupon — shared with their audience for a discount.
 *  2. self_coupon     — used by the ambassador themselves for purchases.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_Coupon {

	/** Meta key stored on the WC coupon post. */
	const AMBASSADOR_META = '_oe_amb_ambassador_id';
	const COUPON_TYPE_META = '_oe_amb_coupon_type';  // 'customer' | 'self'

	// ── Create ────────────────────────────────────────────────────────────────

	/**
	 * Create a customer-facing discount coupon for the ambassador.
	 *
	 * @param  int    $ambassador_id  Row ID in oe_ambassadors table.
	 * @param  string $code           Desired coupon code (e.g. "JOHN10").
	 * @param  float  $pct            Discount percentage (e.g. 10).
	 * @return string|WP_Error  The created coupon code, or WP_Error.
	 */
	public static function create_customer_coupon( int $ambassador_id, string $code, float $pct ): string|WP_Error {
		return self::create_coupon( $ambassador_id, $code, $pct, 'customer' );
	}

	/**
	 * Create a self-purchase coupon for the ambassador.
	 */
	public static function create_self_coupon( int $ambassador_id, string $code, float $pct ): string|WP_Error {
		return self::create_coupon( $ambassador_id, $code, $pct, 'self' );
	}

	private static function create_coupon( int $ambassador_id, string $code, float $pct, string $type ): string|WP_Error {
		$code = strtolower( sanitize_text_field( $code ) );

		if ( self::coupon_exists( $code ) ) {
			return new WP_Error( 'coupon_exists', __( 'This coupon code is already in use. Please choose another.', 'oe-ambassador' ) );
		}

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $pct );
		$coupon->set_individual_use( false );
		$coupon->set_usage_limit( 0 );          // unlimited
		$coupon->set_usage_limit_per_user( 0 ); // unlimited
		$coupon->set_free_shipping( false );
		$coupon->set_minimum_amount( '' );
		$coupon->set_maximum_amount( '' );
		$coupon->set_date_expires( null );

		// If this is a self-purchase code, restrict to the ambassador's user
		if ( $type === 'self' ) {
			$amb = OE_Amb_DB::get_ambassador( $ambassador_id );
			if ( $amb && $amb->user_id ) {
				$user = get_user_by( 'id', $amb->user_id );
				if ( $user ) {
					$coupon->set_email_restrictions( [ $user->user_email ] );
					$coupon->set_usage_limit_per_user( 0 ); // still unlimited uses
				}
			}
		}

		$coupon_id = $coupon->save();

		if ( ! $coupon_id || is_wp_error( $coupon_id ) ) {
			return new WP_Error( 'coupon_save_fail', __( 'Failed to save coupon.', 'oe-ambassador' ) );
		}

		update_post_meta( $coupon_id, self::AMBASSADOR_META, $ambassador_id );
		update_post_meta( $coupon_id, self::COUPON_TYPE_META, $type );

		return $code;
	}

	// ── Update ────────────────────────────────────────────────────────────────

	/**
	 * Update a coupon's discount percentage.
	 */
	public static function update_coupon_pct( string $code, float $pct ): bool {
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) {
			return false;
		}
		$coupon->set_amount( $pct );
		return (bool) $coupon->save();
	}

	// ── Delete ────────────────────────────────────────────────────────────────

	/**
	 * Permanently remove a coupon by code.
	 */
	public static function delete_coupon( string $code ): bool {
		$coupon = new WC_Coupon( $code );
		$id     = $coupon->get_id();
		if ( ! $id ) {
			return false;
		}
		return (bool) wp_delete_post( $id, true );
	}

	// ── Query helpers ─────────────────────────────────────────────────────────

	public static function coupon_exists( string $code ): bool {
		$coupon = new WC_Coupon( strtolower( $code ) );
		return (bool) $coupon->get_id();
	}

	/**
	 * Given a WC_Order, return the ambassador ID associated with any coupon
	 * used in that order, or 0 if none.
	 */
	public static function get_ambassador_id_from_order( WC_Order $order ): int {
		foreach ( $order->get_coupon_codes() as $code ) {
			$coupon = new WC_Coupon( $code );
			$amb_id = (int) get_post_meta( $coupon->get_id(), self::AMBASSADOR_META, true );
			$type   = get_post_meta( $coupon->get_id(), self::COUPON_TYPE_META, true );

			// Only customer coupons trigger commissions (not the ambassador's own self-purchase code)
			if ( $amb_id && $type === 'customer' ) {
				return $amb_id;
			}
		}
		return 0;
	}

	/**
	 * Generate a unique coupon code suggestion based on ambassador name + suffix.
	 * e.g. "JohnD10" — keeps trying until it finds an unused code.
	 */
	public static function suggest_code( string $first_name, string $last_name, float $pct ): string {
		$base  = strtolower( substr( $first_name, 0, 4 ) . substr( $last_name, 0, 1 ) . (int) $pct );
		$base  = preg_replace( '/[^a-z0-9]/', '', $base );
		$code  = $base;
		$i     = 2;

		while ( self::coupon_exists( $code ) ) {
			$code = $base . $i;
			$i++;
		}

		return strtoupper( $code );
	}

	/**
	 * Suggest a self-purchase code (e.g. "JohnD-PRO").
	 */
	public static function suggest_self_code( string $first_name, string $last_name ): string {
		$base = strtolower( substr( $first_name, 0, 4 ) . substr( $last_name, 0, 1 ) . '-pro' );
		$base = preg_replace( '/[^a-z0-9-]/', '', $base );
		$code = $base;
		$i    = 2;

		while ( self::coupon_exists( $code ) ) {
			$code = $base . $i;
			$i++;
		}

		return strtoupper( $code );
	}
}

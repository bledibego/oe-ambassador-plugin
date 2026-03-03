<?php
/**
 * Ambassador model — thin wrapper around a DB row.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_Ambassador {

	public int    $id             = 0;
	public int    $user_id        = 0;
	public string $status         = 'pending';
	public string $first_name     = '';
	public string $last_name      = '';
	public string $email          = '';
	public string $phone          = '';
	public string $social_handle  = '';
	public string $social_platform= '';
	public string $website        = '';
	public string $motivation     = '';
	public string $coupon_code    = '';
	public float  $coupon_pct     = 10.0;
	public string $self_code      = '';
	public float  $self_pct       = 20.0;
	public array  $free_products  = [];
	public string $notes          = '';
	public string $applied_at     = '';
	public string $approved_at    = '';

	// ── Factory methods ───────────────────────────────────────────────────────

	public static function find( int $id ): ?self {
		$row = OE_Amb_DB::get_ambassador( $id );
		return $row ? self::from_row( $row ) : null;
	}

	public static function find_by_user( int $user_id ): ?self {
		$row = OE_Amb_DB::get_ambassador_by_user( $user_id );
		return $row ? self::from_row( $row ) : null;
	}

	public static function find_by_coupon( string $code ): ?self {
		$row = OE_Amb_DB::get_ambassador_by_coupon( $code );
		return $row ? self::from_row( $row ) : null;
	}

	private static function from_row( object $row ): self {
		$amb                  = new self();
		$amb->id              = (int)    $row->id;
		$amb->user_id         = (int)    $row->user_id;
		$amb->status          = (string) $row->status;
		$amb->first_name      = (string) $row->first_name;
		$amb->last_name       = (string) $row->last_name;
		$amb->email           = (string) $row->email;
		$amb->phone           = (string) $row->phone;
		$amb->social_handle   = (string) $row->social_handle;
		$amb->social_platform = (string) $row->social_platform;
		$amb->website         = (string) $row->website;
		$amb->motivation      = (string) $row->motivation;
		$amb->coupon_code     = (string) $row->coupon_code;
		$amb->coupon_pct      = (float)  $row->coupon_pct;
		$amb->self_code       = (string) $row->self_code;
		$amb->self_pct        = (float)  $row->self_pct;
		$amb->free_products   = $row->free_products ? (array) json_decode( $row->free_products, true ) : [];
		$amb->notes           = (string) $row->notes;
		$amb->applied_at      = (string) $row->applied_at;
		$amb->approved_at     = (string) ( $row->approved_at ?? '' );
		return $amb;
	}

	// ── Persistence ───────────────────────────────────────────────────────────

	public function save(): bool {
		$data = [
			'user_id'         => $this->user_id,
			'status'          => $this->status,
			'first_name'      => $this->first_name,
			'last_name'       => $this->last_name,
			'email'           => $this->email,
			'phone'           => $this->phone,
			'social_handle'   => $this->social_handle,
			'social_platform' => $this->social_platform,
			'website'         => $this->website,
			'motivation'      => $this->motivation,
			'coupon_code'     => $this->coupon_code,
			'coupon_pct'      => $this->coupon_pct,
			'self_code'       => $this->self_code,
			'self_pct'        => $this->self_pct,
			'free_products'   => wp_json_encode( $this->free_products ),
			'notes'           => $this->notes,
			'approved_at'     => $this->approved_at ?: null,
		];

		if ( $this->id ) {
			return OE_Amb_DB::update_ambassador( $this->id, $data );
		}

		$id = OE_Amb_DB::insert_ambassador( $data );
		if ( $id ) {
			$this->id = $id;
			return true;
		}
		return false;
	}

	// ── Convenience ───────────────────────────────────────────────────────────

	public function full_name(): string {
		return trim( $this->first_name . ' ' . $this->last_name );
	}

	public function is_approved(): bool {
		return $this->status === 'approved';
	}

	public function display_status(): string {
		$map = [
			'pending'   => __( 'Pending',   'oe-ambassador' ),
			'approved'  => __( 'Approved',  'oe-ambassador' ),
			'rejected'  => __( 'Rejected',  'oe-ambassador' ),
			'suspended' => __( 'Suspended', 'oe-ambassador' ),
		];
		return $map[ $this->status ] ?? ucfirst( $this->status );
	}

	/** Get the ambassador's WP user object, or null. */
	public function wp_user(): ?WP_User {
		if ( ! $this->user_id ) {
			return null;
		}
		$user = get_user_by( 'id', $this->user_id );
		return $user ?: null;
	}

	/**
	 * Commission stats for a given month (YYYY-MM format, defaults to current).
	 */
	public function monthly_stats( string $month = '' ): array {
		if ( ! $month ) {
			$month = current_time( 'Y-m' );
		}
		[ $y, $m ] = explode( '-', $month );
		$start = "$y-$m-01 00:00:00";
		$end   = gmdate( 'Y-m-t 23:59:59', mktime( 0, 0, 0, (int) $m, 1, (int) $y ) );

		$result = OE_Amb_DB::get_commissions( $this->id, [
			'date_from' => $start,
			'date_to'   => $end,
			'per_page'  => 9999,
		] );

		return [
			'total_orders'     => $result['total'],
			'total_commission' => $result['sum_commission'],
			'total_net'        => $result['sum_net'],
			'tier_pct'         => OE_Ambassador::tier_pct( $result['total'] ),
		];
	}

	/**
	 * Overall lifetime stats.
	 */
	public function lifetime_stats(): array {
		$result = OE_Amb_DB::get_commissions( $this->id, [ 'per_page' => 9999 ] );
		return [
			'total_orders'     => $result['total'],
			'total_commission' => $result['sum_commission'],
			'total_net'        => $result['sum_net'],
		];
	}

	/**
	 * Social platform share URL (returns array of platform => url).
	 */
	public function social_share_links( string $share_url = '', string $share_text = '' ): array {
		if ( ! $share_url ) {
			$shop_url   = class_exists( 'WooCommerce' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
			$share_url  = add_query_arg( 'ref', $this->coupon_code, $shop_url );
		}
		if ( ! $share_text ) {
			/* translators: 1: discount percentage, 2: site name, 3: coupon code */
			$share_text = sprintf(
				__( 'Get %1$d%% off at %2$s with my code: %3$s', 'oe-ambassador' ),
				(int) $this->coupon_pct,
				get_bloginfo( 'name' ),
				strtoupper( $this->coupon_code )
			);
		}

		$enc_url  = rawurlencode( $share_url );
		$enc_text = rawurlencode( $share_text );

		return [
			'instagram' => 'https://www.instagram.com/',  // Instagram has no direct share URL API
			'facebook'  => "https://www.facebook.com/sharer/sharer.php?u=$enc_url&quote=$enc_text",
			'twitter'   => "https://twitter.com/intent/tweet?url=$enc_url&text=$enc_text",
			'tiktok'    => 'https://www.tiktok.com/',     // TikTok: manual copy
			'linkedin'  => "https://www.linkedin.com/shareArticle?mini=true&url=$enc_url&summary=$enc_text",
			'whatsapp'  => "https://wa.me/?text=$enc_text%20$enc_url",
		];
	}
}

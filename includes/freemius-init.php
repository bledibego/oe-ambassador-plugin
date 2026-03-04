<?php
/**
 * Freemius SDK bootstrap for OE Brand Ambassador Management.
 *
 * ── HOW TO ACTIVATE FREEMIUS ─────────────────────────────────────────────────
 * 1. Download the Freemius SDK from the Freemius dashboard
 * 2. Extract and rename the folder to `freemius`
 * 3. Place it so this path exists:
 *      oe-brand-ambassador-management/vendor/freemius/start.php
 *
 * Until the SDK is installed this file is a no-op: oe_amb_fs() returns null
 * and oe_amb_is_pro() returns false, so the plugin runs in free mode.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'oe_amb_fs' ) ) {
	/**
	 * Returns the Freemius singleton, or null if the SDK is not installed yet.
	 *
	 * @return object|null
	 */
	function oe_amb_fs(): ?object {
		global $oe_amb_fs;

		if ( isset( $oe_amb_fs ) ) {
			return $oe_amb_fs ?: null;
		}

		$sdk_path = OE_AMB_DIR . 'vendor/freemius/start.php';

		if ( ! file_exists( $sdk_path ) ) {
			$oe_amb_fs = false; // SDK not installed — free-mode fallback.
			return null;
		}

		if ( ! function_exists( 'fs_dynamic_init' ) ) {
			require_once $sdk_path;
		}

		$oe_amb_fs = fs_dynamic_init( array(
			'id'                  => '25356',
			'slug'                => 'oe-brand-ambassador-management',
			'premium_slug'        => 'oe-brand-ambassador-management-premium',
			'type'                => 'plugin',
			'public_key'          => 'pk_e7e19aebb9f52daecad37e839244c',
			'is_premium'          => false,
			'premium_suffix'      => 'Pro',
			'has_premium_version' => true,
			'has_addons'          => false,
			'has_paid_plans'      => true,
			'is_org_compliant'    => true,
			'trial'               => array(
				'days'               => 7,
				'is_require_payment' => true,
			),
			'menu'                => array(
				'slug'    => 'oe-amb-dashboard',
				'contact' => true,
				'support' => false,
			),
		) );

		do_action( 'oe_amb_fs_loaded' );

		return $oe_amb_fs;
	}

	oe_amb_fs();
}

<?php
/**
 * Freemius SDK bootstrap for OE Brand Ambassador Management.
 *
 * ── HOW TO ACTIVATE FREEMIUS ─────────────────────────────────────────────────
 * 1. Create a free account at https://freemius.com
 * 2. Add your plugin as a new Product (type: Plugin, billing: Paid + Free)
 * 3. Copy your plugin_id and public_key from the Freemius dashboard
 * 4. Download the Freemius SDK from https://github.com/Freemius/wordpress-sdk/releases
 * 5. Extract it so this path exists:
 *      oe-brand-ambassador-management/vendor/freemius/wordpress-sdk/start.php
 * 6. Replace 'YOUR_PLUGIN_ID' and 'pk_YOUR_PUBLIC_KEY' below with your real values.
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

		$sdk_path = OE_AMB_DIR . 'vendor/freemius/wordpress-sdk/start.php';

		if ( ! file_exists( $sdk_path ) ) {
			$oe_amb_fs = false; // SDK not installed — free-mode fallback
			return null;
		}

		if ( ! function_exists( 'fs_dynamic_init' ) ) {
			require_once $sdk_path;
		}

		$oe_amb_fs = fs_dynamic_init( [
			'id'                  => 'YOUR_PLUGIN_ID',       // ← your Freemius plugin ID
			'slug'                => 'oe-brand-ambassador-management',
			'premium_slug'        => 'oe-brand-ambassador-management-pro',
			'type'                => 'plugin',
			'public_key'          => 'pk_YOUR_PUBLIC_KEY',   // ← your Freemius public key
			'is_premium'          => false,
			'has_premium_version' => true,
			'has_addons'          => false,
			'has_paid_plans'      => true,
			'menu'                => [
				'slug'    => 'oe-amb-dashboard',
				'contact' => false,
				'support' => false,
			],
			'is_live'             => true,
		] );

		do_action( 'oe_amb_fs_loaded' );

		return $oe_amb_fs;
	}

	oe_amb_fs();
}

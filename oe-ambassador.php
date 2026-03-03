<?php
/**
 * Plugin Name:       OE Ambassador – Brand Ambassador Management
 * Plugin URI:        https://github.com/bledibego/oe-ambassador-plugin
 * Description:       Complete brand ambassador management for WooCommerce. Configurable tiers, commission tracking, discount codes, self-purchase codes, free products, social sharing, and email reports.
 * Version:           1.0.0
 * Author:            OptimumEssence
 * Author URI:        https://optimumessence.se
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oe-ambassador
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 8.0
 * WC tested up to:   10.5
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ────────────────────────────────────────────────────────────────
define( 'OE_AMB_VERSION',  '1.0.0' );
define( 'OE_AMB_FILE',     __FILE__ );
define( 'OE_AMB_DIR',      plugin_dir_path( __FILE__ ) );
define( 'OE_AMB_URL',      plugin_dir_url( __FILE__ ) );
define( 'OE_AMB_BASENAME', plugin_basename( __FILE__ ) );

// ── Load all includes immediately (needed before plugins_loaded for activation hooks) ──
require_once plugin_dir_path( __FILE__ ) . 'includes/class-oe-amb-activator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-oe-amb-db.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-oe-amb-ambassador.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-oe-amb-coupon.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-oe-amb-commission.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-oe-amb-email.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-oe-amb-cron.php';
require_once plugin_dir_path( __FILE__ ) . 'public/class-oe-amb-public.php';
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'admin/class-oe-amb-admin.php';
}

// ── Activation / Deactivation ────────────────────────────────────────────────
register_activation_hook( __FILE__,   [ 'OE_Amb_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'OE_Amb_Activator', 'deactivate' ] );

/**
 * Main plugin bootstrap class — singleton.
 */
final class OE_Ambassador {

	private static ?OE_Ambassador $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies(): void {
		// All files already required at plugin load time (top of file)
		// so class definitions are available for activation hooks too.
	}

	private function init_hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'check_woocommerce' ], 20 );

		// Declare WooCommerce HPOS compatibility
		add_action( 'before_woocommerce_init', function() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OE_AMB_FILE, true );
			}
		} );

		OE_Amb_Commission::instance()->init();
		OE_Amb_Cron::instance()->init();

		if ( is_admin() ) {
			OE_Amb_Admin::instance()->init();
		}

		OE_Amb_Public::instance()->init();
	}

	public function check_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p><strong>OE Ambassador</strong>: ' .
				     esc_html__( 'WooCommerce must be installed and active.', 'oe-ambassador' ) .
				     '</p></div>';
			} );
		}
	}

	/** Helper: default tiers returned as array of ['min','max','pct'] */
	public static function default_tiers(): array {
		return [
			[ 'min' => 0,   'max' => 49,  'pct' => 7  ],
			[ 'min' => 50,  'max' => 99,  'pct' => 10 ],
			[ 'min' => 100, 'max' => 149, 'pct' => 15 ],
			[ 'min' => 150, 'max' => -1,  'pct' => 20 ], // -1 = unlimited
		];
	}

	/** Returns configured tiers from options, falling back to defaults. */
	public static function get_tiers(): array {
		$tiers = get_option( 'oe_amb_tiers', [] );
		if ( empty( $tiers ) || ! is_array( $tiers ) ) {
			return self::default_tiers();
		}
		return $tiers;
	}

	/** Find the commission % for a given sales count. */
	public static function tier_pct( int $sales_count ): float {
		$tiers = self::get_tiers();
		$pct   = 0.0;
		foreach ( $tiers as $tier ) {
			$min = (int) $tier['min'];
			$max = (int) $tier['max'];
			if ( $sales_count >= $min && ( $max === -1 || $sales_count <= $max ) ) {
				$pct = (float) $tier['pct'];
				break;
			}
		}
		return $pct;
	}

	/** General plugin setting with default fallback. */
	public static function setting( string $key, mixed $default = '' ): mixed {
		$settings = get_option( 'oe_amb_settings', [] );
		return $settings[ $key ] ?? $default;
	}
}

// ── Boot ─────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function() {
	OE_Ambassador::instance();
}, 5 );

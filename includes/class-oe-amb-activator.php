<?php
/**
 * Plugin activator — creates DB tables and sets defaults.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_Activator {

	public static function activate(): void {
		self::create_tables();
		self::add_roles_caps();
		self::set_defaults();
		self::create_pages();
		OE_Amb_Cron::schedule_events();
		// Flag that setup notice should show
		update_option( 'oe_amb_just_activated', 1 );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		OE_Amb_Cron::unschedule_events();
		flush_rewrite_rules();
	}

	// ── Database tables ───────────────────────────────────────────────────────

	public static function create_tables(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ambassadors
		$sql_amb = "CREATE TABLE {$wpdb->prefix}oe_ambassadors (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id         BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status          VARCHAR(20)  NOT NULL DEFAULT 'pending',
			first_name      VARCHAR(100) NOT NULL DEFAULT '',
			last_name       VARCHAR(100) NOT NULL DEFAULT '',
			email           VARCHAR(200) NOT NULL DEFAULT '',
			phone           VARCHAR(50)  NOT NULL DEFAULT '',
			social_handle   VARCHAR(200) NOT NULL DEFAULT '',
			social_platform VARCHAR(50)  NOT NULL DEFAULT '',
			website         VARCHAR(255) NOT NULL DEFAULT '',
			motivation      TEXT,
			coupon_code     VARCHAR(100) NOT NULL DEFAULT '',
			coupon_pct      DECIMAL(5,2) NOT NULL DEFAULT 10.00,
			self_code       VARCHAR(100) NOT NULL DEFAULT '',
			self_pct        DECIMAL(5,2) NOT NULL DEFAULT 20.00,
			free_products   TEXT,
			notes           TEXT,
			applied_at      DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			approved_at     DATETIME DEFAULT NULL,
			PRIMARY KEY     (id),
			UNIQUE KEY      email (email),
			KEY             user_id (user_id),
			KEY             status (status),
			KEY             coupon_code (coupon_code)
		) $charset_collate;";

		// commissions — one row per order attributed to an ambassador
		$sql_com = "CREATE TABLE {$wpdb->prefix}oe_amb_commissions (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ambassador_id   BIGINT(20) UNSIGNED NOT NULL,
			order_id        BIGINT(20) UNSIGNED NOT NULL,
			order_total     DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			tax_amount      DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			shipping_amount DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			discount_amount DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			net_amount      DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			tier_pct        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
			commission      DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			status          VARCHAR(20)   NOT NULL DEFAULT 'pending',
			currency        VARCHAR(10)   NOT NULL DEFAULT 'SEK',
			order_date      DATETIME      NOT NULL DEFAULT '0000-00-00 00:00:00',
			paid_at         DATETIME      DEFAULT NULL,
			payout_id       BIGINT(20) UNSIGNED DEFAULT NULL,
			PRIMARY KEY     (id),
			UNIQUE KEY      order_id (order_id),
			KEY             ambassador_id (ambassador_id),
			KEY             status (status),
			KEY             order_date (order_date)
		) $charset_collate;";

		// payouts — monthly payment batches
		$sql_pay = "CREATE TABLE {$wpdb->prefix}oe_amb_payouts (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ambassador_id   BIGINT(20) UNSIGNED NOT NULL,
			period_start    DATE        NOT NULL,
			period_end      DATE        NOT NULL,
			total_sales     INT(11)     NOT NULL DEFAULT 0,
			gross_revenue   DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			net_revenue     DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			tier_pct        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
			payout_amount   DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
			currency        VARCHAR(10)   NOT NULL DEFAULT 'SEK',
			status          VARCHAR(20)   NOT NULL DEFAULT 'pending',
			notes           TEXT,
			created_at      DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			paid_at         DATETIME DEFAULT NULL,
			PRIMARY KEY     (id),
			KEY             ambassador_id (ambassador_id),
			KEY             status (status)
		) $charset_collate;";

		dbDelta( $sql_amb );
		dbDelta( $sql_com );
		dbDelta( $sql_pay );

		update_option( 'oe_amb_db_version', OE_AMB_VERSION );
	}

	// ── Auto-create pages ─────────────────────────────────────────────────────

	/**
	 * Creates the Apply and Portal pages with shortcodes on first activation.
	 * Skips if pages already exist. Saves their IDs into plugin settings.
	 */
	public static function create_pages(): void {
		$definitions = [
			'apply_page_id' => [
				'slug'    => 'ambassador-apply',
				'title'   => __( 'Become an Ambassador', 'oe-brand-ambassador-management' ),
				'content' => '<!-- wp:shortcode -->[oe_amb_apply]<!-- /wp:shortcode -->',
			],
			'portal_page_id' => [
				'slug'    => 'ambassador-portal',
				'title'   => __( 'Ambassador Portal', 'oe-brand-ambassador-management' ),
				'content' => '<!-- wp:shortcode -->[oe_amb_portal]<!-- /wp:shortcode -->',
			],
		];

		$settings = get_option( 'oe_amb_settings', [] );
		$changed  = false;

		foreach ( $definitions as $setting_key => $page ) {
			// If already set and the post exists, skip
			if ( ! empty( $settings[ $setting_key ] ) && get_post( (int) $settings[ $setting_key ] ) ) {
				continue;
			}

			// Check by slug
			$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
			if ( $existing ) {
				$settings[ $setting_key ] = $existing->ID;
				$changed = true;
				continue;
			}

			// Create fresh
			$page_id = wp_insert_post( [
				'post_title'   => $page['title'],
				'post_name'    => $page['slug'],
				'post_content' => $page['content'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => 1,
				'comment_status' => 'closed',
			] );

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$settings[ $setting_key ] = $page_id;
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( 'oe_amb_settings', $settings );
		}
	}

	// ── Roles & capabilities ──────────────────────────────────────────────────

	private static function add_roles_caps(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_oe_ambassadors' );
		}
	}

	// ── Default options ───────────────────────────────────────────────────────

	private static function set_defaults(): void {
		if ( ! get_option( 'oe_amb_tiers' ) ) {
			update_option( 'oe_amb_tiers', OE_Ambassador::default_tiers() );
		}

		if ( ! get_option( 'oe_amb_settings' ) ) {
			update_option( 'oe_amb_settings', [
				'customer_coupon_pct'    => 10,    // % discount customers get
				'self_purchase_pct'      => 20,    // % discount ambassador self-purchase
				'commission_trigger'     => 'completed', // order status that triggers commission
				'apply_page_id'          => 0,
				'portal_page_id'         => 0,
				'notify_admin_email'     => get_option( 'admin_email' ),
				'monthly_report_day'     => 1,     // day of month to send monthly reports
				'from_name'              => get_option( 'blogname' ),
				'from_email'             => get_option( 'admin_email' ),
				'currency'               => get_option( 'woocommerce_currency', 'USD' ),
				'terms_page_url'         => '',
			] );
		}
	}
}

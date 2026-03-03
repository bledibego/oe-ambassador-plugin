<?php
/**
 * Admin panel for OE Ambassador.
 *
 * Registers menus, pages, settings, and AJAX handlers.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_Admin {

	private static ?OE_Amb_Admin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action( 'admin_menu',            [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_oe_amb_approve', [ $this, 'handle_approve' ] );
		add_action( 'admin_post_oe_amb_reject',  [ $this, 'handle_reject' ] );
		add_action( 'admin_post_oe_amb_update',  [ $this, 'handle_update' ] );
		add_action( 'admin_post_oe_amb_save_settings', [ $this, 'handle_save_settings' ] );
		add_action( 'admin_notices',         [ $this, 'admin_notices' ] );
		add_action( 'admin_notices',         [ $this, 'setup_notice' ] );

		// AJAX for inline status changes
		add_action( 'wp_ajax_oe_amb_quick_status',      [ $this, 'ajax_quick_status' ] );
		add_action( 'wp_ajax_oe_amb_dismiss_setup',     [ $this, 'ajax_dismiss_setup' ] );

		// Enqueue dismiss script on all admin pages when setup notice is showing
		if ( get_option( 'oe_amb_just_activated' ) ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_setup_dismiss_script' ] );
		}
	}

	/**
	 * Enqueue a small inline script for the setup notice dismiss button.
	 * Uses wp_add_inline_script() — no raw <script> tags in HTML output.
	 */
	public function enqueue_setup_dismiss_script(): void {
		wp_register_script( 'oe-amb-setup-dismiss', false, [ 'jquery' ], null, true ); // phpcs:ignore
		wp_enqueue_script( 'oe-amb-setup-dismiss' );
		wp_add_inline_script(
			'oe-amb-setup-dismiss',
			'function oeDismissSetup(){' .
				'jQuery.post(ajaxurl,{action:"oe_amb_dismiss_setup",nonce:"' . esc_js( wp_create_nonce( 'oe_amb_dismiss' ) ) . '"});' .
				'var el=document.getElementById("oe-amb-setup-notice");if(el){el.style.display="none";}' .
			'}'
		);
	}

	// ── Menus ─────────────────────────────────────────────────────────────────

	public function register_menus(): void {
		$pending = $this->pending_count();
		$badge   = $pending ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';

		add_menu_page(
			__( 'OE Ambassador', 'oe-ambassador' ),
			__( 'Ambassadors', 'oe-ambassador' ) . $badge,
			'manage_oe_ambassadors',
			'oe-ambassador',
			[ $this, 'page_dashboard' ],
			'dashicons-groups',
			56
		);

		add_submenu_page( 'oe-ambassador', __( 'Dashboard', 'oe-ambassador' ),   __( 'Dashboard', 'oe-ambassador' ),   'manage_oe_ambassadors', 'oe-ambassador',              [ $this, 'page_dashboard' ] );
		add_submenu_page( 'oe-ambassador', __( 'Ambassadors', 'oe-ambassador' ), __( 'Ambassadors', 'oe-ambassador' ) . $badge, 'manage_oe_ambassadors', 'oe-ambassador-ambassadors', [ $this, 'page_ambassadors' ] );
		add_submenu_page( 'oe-ambassador', __( 'Reports', 'oe-ambassador' ),     __( 'Reports', 'oe-ambassador' ),     'manage_oe_ambassadors', 'oe-ambassador-reports',     [ $this, 'page_reports' ] );
		add_submenu_page( 'oe-ambassador', __( 'Settings', 'oe-ambassador' ),    __( 'Settings', 'oe-ambassador' ),    'manage_oe_ambassadors', 'oe-ambassador-settings',    [ $this, 'page_settings' ] );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'oe-ambassador' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'oe-amb-admin',
			OE_AMB_URL . 'admin/css/admin.css',
			[],
			OE_AMB_VERSION
		);
		wp_enqueue_script(
			'oe-amb-admin',
			OE_AMB_URL . 'admin/js/admin.js',
			[ 'jquery' ],
			OE_AMB_VERSION,
			true
		);
		wp_localize_script( 'oe-amb-admin', 'oeAmb', [
			'nonce'   => wp_create_nonce( 'oe_amb_admin' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => [
				'confirm_approve' => __( 'Approve this ambassador?', 'oe-ambassador' ),
				'confirm_reject'  => __( 'Reject this application?', 'oe-ambassador' ),
				'confirm_payout'  => __( 'Create payout for selected period?', 'oe-ambassador' ),
			],
		] );
	}

	// ── Pages ─────────────────────────────────────────────────────────────────

	public function page_dashboard(): void {
		require OE_AMB_DIR . 'admin/views/page-dashboard.php';
	}

	public function page_ambassadors(): void {
		$action = sanitize_key( $_GET['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $action === 'view' && isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			require OE_AMB_DIR . 'admin/views/page-ambassador-detail.php';
		} else {
			require OE_AMB_DIR . 'admin/views/page-ambassadors.php';
		}
	}

	public function page_reports(): void {
		require OE_AMB_DIR . 'admin/views/page-reports.php';
	}

	public function page_settings(): void {
		require OE_AMB_DIR . 'admin/views/page-settings.php';
	}

	// ── Action handlers ───────────────────────────────────────────────────────

	public function handle_approve(): void {
		check_admin_referer( 'oe_amb_approve' );
		if ( ! current_user_can( 'manage_oe_ambassadors' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'oe-ambassador' ) );
		}

		$id  = absint( wp_unslash( $_POST['ambassador_id'] ?? 0 ) );
		$amb = OE_Amb_Ambassador::find( $id );
		if ( ! $amb ) {
			wp_die( esc_html__( 'Ambassador not found.', 'oe-ambassador' ) );
		}

		// ── Generate discount codes if not set ────────────────────────────────
		$customer_pct = (float) wp_unslash( $_POST['coupon_pct'] ?? OE_Ambassador::setting( 'customer_coupon_pct', 10 ) );
		$self_pct     = (float) wp_unslash( $_POST['self_pct']   ?? OE_Ambassador::setting( 'self_purchase_pct', 20 ) );

		$coupon_code  = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) );
		$self_code    = sanitize_text_field( wp_unslash( $_POST['self_code']   ?? '' ) );

		if ( ! $coupon_code ) {
			$coupon_code = OE_Amb_Coupon::suggest_code( $amb->first_name, $amb->last_name, $customer_pct );
		}
		if ( ! $self_code ) {
			$self_code = OE_Amb_Coupon::suggest_self_code( $amb->first_name, $amb->last_name );
		}

		// Create WC coupons
		$c1 = OE_Amb_Coupon::create_customer_coupon( $id, $coupon_code, $customer_pct );
		$c2 = OE_Amb_Coupon::create_self_coupon( $id, $self_code, $self_pct );

		if ( is_wp_error( $c1 ) ) {
			set_transient( 'oe_amb_notice_error_' . get_current_user_id(), $c1->get_error_message(), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $id ) );
			exit;
		}

		// Free products
		$free_products = isset( $_POST['free_products'] )
			? array_map( 'intval', (array) wp_unslash( $_POST['free_products'] ) )
			: [];

		// Create WP user if doesn't exist
		$user_id = $amb->user_id;
		if ( ! $user_id ) {
			$user_id = $this->create_ambassador_user( $amb );
		}

		// Update ambassador record
		$amb->status          = 'approved';
		$amb->coupon_code     = is_wp_error( $c1 ) ? $amb->coupon_code : $c1;
		$amb->coupon_pct      = $customer_pct;
		$amb->self_code       = is_wp_error( $c2 ) ? $amb->self_code   : $c2;
		$amb->self_pct        = $self_pct;
		$amb->free_products   = $free_products;
		$amb->user_id         = $user_id;
		$amb->approved_at     = current_time( 'mysql' );
		$amb->save();

		// Send approval email
		OE_Amb_Email::send_approval( $amb );

		set_transient( 'oe_amb_notice_success_' . get_current_user_id(), __( 'Ambassador approved and notified.', 'oe-ambassador' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $id ) );
		exit;
	}

	public function handle_reject(): void {
		check_admin_referer( 'oe_amb_reject' );
		if ( ! current_user_can( 'manage_oe_ambassadors' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'oe-ambassador' ) );
		}

		$id  = absint( wp_unslash( $_POST['ambassador_id'] ?? 0 ) );
		$amb = OE_Amb_Ambassador::find( $id );
		if ( ! $amb ) {
			wp_die( esc_html__( 'Ambassador not found.', 'oe-ambassador' ) );
		}

		$amb->status = 'rejected';
		$amb->notes  = sanitize_textarea_field( wp_unslash( $_POST['rejection_reason'] ?? '' ) );
		$amb->save();

		OE_Amb_Email::send_rejection( $amb );

		set_transient( 'oe_amb_notice_success_' . get_current_user_id(), __( 'Application rejected. Applicant notified.', 'oe-ambassador' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=oe-ambassador-ambassadors' ) );
		exit;
	}

	public function handle_update(): void {
		check_admin_referer( 'oe_amb_update' );
		if ( ! current_user_can( 'manage_oe_ambassadors' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'oe-ambassador' ) );
		}

		$id  = absint( wp_unslash( $_POST['ambassador_id'] ?? 0 ) );
		$amb = OE_Amb_Ambassador::find( $id );
		if ( ! $amb ) {
			wp_die( esc_html__( 'Ambassador not found.', 'oe-ambassador' ) );
		}

		$amb->notes         = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
		$amb->coupon_pct    = (float) wp_unslash( $_POST['coupon_pct'] ?? $amb->coupon_pct );
		$amb->self_pct      = (float) wp_unslash( $_POST['self_pct']   ?? $amb->self_pct );
		$amb->free_products = isset( $_POST['free_products'] )
			? array_map( 'intval', (array) wp_unslash( $_POST['free_products'] ) )
			: [];
		$amb->status        = sanitize_key( wp_unslash( $_POST['status'] ?? $amb->status ) );
		$amb->save();

		// Update coupon amounts if they changed
		if ( $amb->coupon_code ) {
			OE_Amb_Coupon::update_coupon_pct( $amb->coupon_code, $amb->coupon_pct );
		}
		if ( $amb->self_code ) {
			OE_Amb_Coupon::update_coupon_pct( $amb->self_code, $amb->self_pct );
		}

		set_transient( 'oe_amb_notice_success_' . get_current_user_id(), __( 'Ambassador updated.', 'oe-ambassador' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $id ) );
		exit;
	}

	public function handle_save_settings(): void {
		check_admin_referer( 'oe_amb_save_settings' );
		if ( ! current_user_can( 'manage_oe_ambassadors' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'oe-ambassador' ) );
		}

		// General settings
		$settings = [
			'customer_coupon_pct'    => absint( wp_unslash( $_POST['customer_coupon_pct']    ?? 10 ) ),
			'self_purchase_pct'      => absint( wp_unslash( $_POST['self_purchase_pct']      ?? 20 ) ),
			'commission_trigger'     => sanitize_key( wp_unslash( $_POST['commission_trigger']  ?? 'completed' ) ),
			'apply_page_id'          => absint( wp_unslash( $_POST['apply_page_id']          ?? 0 ) ),
			'portal_page_id'         => absint( wp_unslash( $_POST['portal_page_id']         ?? 0 ) ),
			'notify_admin_email'     => sanitize_email( wp_unslash( $_POST['notify_admin_email'] ?? get_option( 'admin_email' ) ) ),
			'from_name'              => sanitize_text_field( wp_unslash( $_POST['from_name']    ?? get_bloginfo( 'name' ) ) ),
			'from_email'             => sanitize_email( wp_unslash( $_POST['from_email']        ?? get_option( 'admin_email' ) ) ),
			'monthly_report_day'     => absint( wp_unslash( $_POST['monthly_report_day']     ?? 1 ) ),
			'auto_approve_days'      => absint( wp_unslash( $_POST['auto_approve_days']      ?? 0 ) ),
			'currency'               => sanitize_text_field( wp_unslash( $_POST['currency']     ?? get_option( 'woocommerce_currency', 'USD' ) ) ),
			'terms_page_url'         => esc_url_raw( wp_unslash( $_POST['terms_page_url']       ?? '' ) ),
		];
		update_option( 'oe_amb_settings', $settings );

		// Tiers
		$tier_mins = array_map( 'intval', (array) wp_unslash( $_POST['tier_min'] ?? [] ) );
		$tier_maxs = array_map( 'intval', (array) wp_unslash( $_POST['tier_max'] ?? [] ) );
		$tier_pcts = array_map( 'floatval', (array) wp_unslash( $_POST['tier_pct'] ?? [] ) );
		$tiers = [];
		foreach ( $tier_mins as $i => $min ) {
			$tiers[] = [
				'min' => $min,
				'max' => isset( $tier_maxs[ $i ] ) ? (int) $tier_maxs[ $i ] : -1,
				'pct' => isset( $tier_pcts[ $i ] ) ? (float) $tier_pcts[ $i ] : 10,
			];
		}
		if ( ! empty( $tiers ) ) {
			update_option( 'oe_amb_tiers', $tiers );
		}

		set_transient( 'oe_amb_notice_success_' . get_current_user_id(), __( 'Settings saved.', 'oe-ambassador' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=oe-ambassador-settings' ) );
		exit;
	}

	// ── AJAX ─────────────────────────────────────────────────────────────────

	public function ajax_quick_status(): void {
		check_ajax_referer( 'oe_amb_admin', 'nonce' );
		if ( ! current_user_can( 'manage_oe_ambassadors' ) ) {
			wp_send_json_error();
		}
		$id     = absint( wp_unslash( $_POST['id'] ?? 0 ) );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		if ( ! $id || ! in_array( $status, [ 'approved', 'suspended', 'rejected' ], true ) ) {
			wp_send_json_error();
		}
		OE_Amb_DB::update_ambassador( $id, [ 'status' => $status ] );
		wp_send_json_success();
	}

	// ── Admin notices ─────────────────────────────────────────────────────────

	public function admin_notices(): void {
		$uid = get_current_user_id();

		if ( $msg = get_transient( 'oe_amb_notice_success_' . $uid ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			delete_transient( 'oe_amb_notice_success_' . $uid );
		}
		if ( $msg = get_transient( 'oe_amb_notice_error_' . $uid ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			delete_transient( 'oe_amb_notice_error_' . $uid );
		}
	}

	// ── Setup notice ─────────────────────────────────────────────────────────

	/**
	 * Shows a friendly checklist after first activation.
	 * Explains what pages were created, what shortcodes are in them,
	 * and what the admin still needs to do.
	 */
	public function setup_notice(): void {
		if ( ! get_option( 'oe_amb_just_activated' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_oe_ambassadors' ) ) {
			return;
		}

		$settings   = get_option( 'oe_amb_settings', [] );
		$apply_id   = (int) ( $settings['apply_page_id']  ?? 0 );
		$portal_id  = (int) ( $settings['portal_page_id'] ?? 0 );
		$apply_url  = $apply_id  ? get_permalink( $apply_id )  : '';
		$portal_url = $portal_id ? get_permalink( $portal_id ) : '';
		$apply_edit = $apply_id  ? admin_url( 'post.php?post=' . $apply_id  . '&action=edit' ) : '';
		$portal_edit = $portal_id ? admin_url( 'post.php?post=' . $portal_id . '&action=edit' ) : '';
		$settings_url = admin_url( 'admin.php?page=oe-ambassador-settings' );
		?>
		<div class="notice notice-info is-dismissible oe-amb-setup-notice" id="oe-amb-setup-notice" style="padding:20px 24px;border-left-color:#c9a96e">
			<h3 style="margin:0 0 12px;font-size:16px">🎉 <?php esc_html_e( 'OE Ambassador is ready! Here\'s your setup checklist:', 'oe-ambassador' ); ?></h3>
			<ol style="margin:0 0 16px;padding-left:20px;line-height:2">
				<li>
					<?php if ( $apply_id ) : ?>
						✅ <strong><?php esc_html_e( 'Application page created:', 'oe-ambassador' ); ?></strong>
						<a href="<?php echo esc_url( $apply_url ); ?>" target="_blank"><?php echo esc_html( get_the_title( $apply_id ) ); ?></a>
						— contains the shortcode <code>[oe_amb_apply]</code>
						(<a href="<?php echo esc_url( $apply_edit ); ?>"><?php esc_html_e( 'edit page', 'oe-ambassador' ); ?></a>)
					<?php else : ?>
						⬜ <strong><?php esc_html_e( 'Create an Apply page', 'oe-ambassador' ); ?></strong>
						— add a new page with the shortcode <code>[oe_amb_apply]</code>, then set it in
						<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Settings', 'oe-ambassador' ); ?></a>
					<?php endif; ?>
				</li>
				<li>
					<?php if ( $portal_id ) : ?>
						✅ <strong><?php esc_html_e( 'Portal page created:', 'oe-ambassador' ); ?></strong>
						<a href="<?php echo esc_url( $portal_url ); ?>" target="_blank"><?php echo esc_html( get_the_title( $portal_id ) ); ?></a>
						— contains the shortcode <code>[oe_amb_portal]</code>
						(<a href="<?php echo esc_url( $portal_edit ); ?>"><?php esc_html_e( 'edit page', 'oe-ambassador' ); ?></a>)
					<?php else : ?>
						⬜ <strong><?php esc_html_e( 'Create a Portal page', 'oe-ambassador' ); ?></strong>
						— add a new page with the shortcode <code>[oe_amb_portal]</code>, then set it in
						<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Settings', 'oe-ambassador' ); ?></a>
					<?php endif; ?>
				</li>
				<li>⬜ <strong><?php esc_html_e( 'Review Commission Tiers', 'oe-ambassador' ); ?></strong> —
					<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'go to Settings →', 'oe-ambassador' ); ?></a>
					<?php esc_html_e( '(default: 7% / 10% / 15% / 20% based on monthly sales)', 'oe-ambassador' ); ?>
				</li>
				<li>⬜ <strong><?php esc_html_e( 'Link to your Apply page from your navigation menu', 'oe-ambassador' ); ?></strong>
					<?php if ( $apply_url ) : ?>
						— <?php
						/* translators: %s is the page URL */
						printf( esc_html__( 'URL: %s', 'oe-ambassador' ), '<code>' . esc_html( $apply_url ) . '</code>' ); ?>
					<?php endif; ?>
				</li>
			</ol>
			<p style="margin:0">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Settings', 'oe-ambassador' ); ?></a>
				&nbsp;
				<button class="button" onclick="oeDismissSetup()" type="button"><?php esc_html_e( 'Dismiss this notice', 'oe-ambassador' ); ?></button>
			</p>
		</div>
		<?php
	}

	public function ajax_dismiss_setup(): void {
		check_ajax_referer( 'oe_amb_dismiss', 'nonce' );
		delete_option( 'oe_amb_just_activated' );
		wp_send_json_success();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function pending_count(): int {
		global $wpdb;
		$table = OE_Amb_DB::amb_table();
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'pending'
			)
		);
	}

	private function create_ambassador_user( OE_Amb_Ambassador $amb ): int {
		$existing = get_user_by( 'email', $amb->email );
		if ( $existing ) {
			$existing->add_role( 'ambassador' );
			return $existing->ID;
		}

		// Create the ambassador role if it doesn't exist
		if ( ! get_role( 'ambassador' ) ) {
			add_role( 'ambassador', __( 'Ambassador', 'oe-ambassador' ), [
				'read'              => true,
				'manage_ambassador' => true,
			] );
		}

		$username  = sanitize_user( $amb->first_name . '.' . $amb->last_name . wp_rand( 10, 99 ) );
		$password  = wp_generate_password( 12 );

		$user_id = wp_create_user( $username, $password, $amb->email );
		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		wp_update_user( [
			'ID'           => $user_id,
			'first_name'   => $amb->first_name,
			'last_name'    => $amb->last_name,
			'display_name' => $amb->full_name(),
			'role'         => 'ambassador',
		] );

		// Send WP new user email with password
		wp_new_user_notification( $user_id, null, 'user' );

		return $user_id;
	}
}

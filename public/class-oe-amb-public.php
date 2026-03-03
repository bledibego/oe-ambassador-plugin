<?php
/**
 * Frontend shortcodes and public hooks for OE Ambassador.
 *
 * Shortcodes:
 *  [oe_amb_apply]   — ambassador application form
 *  [oe_amb_portal]  — ambassador self-service portal (requires login)
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_Public {

	private static ?OE_Amb_Public $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_shortcode( 'oe_amb_apply',  [ $this, 'shortcode_apply' ] );
		add_shortcode( 'oe_amb_portal', [ $this, 'shortcode_portal' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_oe_amb_submit_application',       [ $this, 'handle_application' ] );
		add_action( 'wp_ajax_nopriv_oe_amb_submit_application', [ $this, 'handle_application' ] );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		global $post;
		$apply_id  = (int) OE_Ambassador::setting( 'apply_page_id', 0 );
		$portal_id = (int) OE_Ambassador::setting( 'portal_page_id', 0 );

		if ( ! is_a( $post, 'WP_Post' ) || ! in_array( $post->ID, [ $apply_id, $portal_id ], true ) ) {
			// Also load if shortcode is present
			if ( ! is_a( $post, 'WP_Post' ) ||
			     ( ! has_shortcode( $post->post_content, 'oe_amb_apply' ) &&
			       ! has_shortcode( $post->post_content, 'oe_amb_portal' ) ) ) {
				return;
			}
		}

		wp_enqueue_style( 'oe-amb-public', OE_AMB_URL . 'public/css/public.css', [], OE_AMB_VERSION );
		wp_enqueue_script( 'oe-amb-public', OE_AMB_URL . 'public/js/public.js', [ 'jquery' ], OE_AMB_VERSION, true );
		wp_localize_script( 'oe-amb-public', 'oeAmbPub', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'oe_amb_apply' ),
			'i18n'    => [
				'submitting' => __( 'Sending...', 'oe-ambassador' ),
				'copy_ok'    => __( 'Copied!', 'oe-ambassador' ),
			],
		] );
	}

	// ── [oe_amb_apply] shortcode ──────────────────────────────────────────────

	public function shortcode_apply( array $atts = [] ): string {
		// If already an ambassador, show status
		if ( is_user_logged_in() ) {
			$amb = OE_Amb_Ambassador::find_by_user( get_current_user_id() );
			if ( $amb ) {
				return $this->render_application_status( $amb );
			}
		}

		ob_start();
		include OE_AMB_DIR . 'public/views/application-form.php';
		return ob_get_clean();
	}

	private function render_application_status( OE_Amb_Ambassador $amb ): string {
		$portal_id  = (int) OE_Ambassador::setting( 'portal_page_id', 0 );
		$portal_url = $portal_id ? get_permalink( $portal_id ) : home_url( '/' );

		ob_start();
		$status = $amb->status;
		?>
		<div class="oe-amb-status-card oe-amb-status-<?php echo esc_attr( $status ); ?>">
		<?php if ( $status === 'approved' ) : ?>
			<div class="oe-amb-status-icon">✓</div>
			<h3><?php esc_html_e( 'You are an approved Ambassador!', 'oe-ambassador' ); ?></h3>
			<p><?php esc_html_e( 'Head to your portal to see your stats and discount codes.', 'oe-ambassador' ); ?></p>
			<a href="<?php echo esc_url( $portal_url ); ?>" class="oe-amb-btn"><?php esc_html_e( 'Go to My Portal →', 'oe-ambassador' ); ?></a>
		<?php elseif ( $status === 'pending' ) : ?>
			<div class="oe-amb-status-icon">⏳</div>
			<h3><?php esc_html_e( 'Application Under Review', 'oe-ambassador' ); ?></h3>
			<p><?php esc_html_e( 'Your application has been received. We usually review applications within 2–3 business days.', 'oe-ambassador' ); ?></p>
		<?php elseif ( $status === 'rejected' ) : ?>
			<div class="oe-amb-status-icon">✗</div>
			<h3><?php esc_html_e( 'Application Not Approved', 'oe-ambassador' ); ?></h3>
			<p><?php esc_html_e( 'Unfortunately we were unable to approve your application at this time.', 'oe-ambassador' ); ?></p>
		<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── [oe_amb_portal] shortcode ─────────────────────────────────────────────

	public function shortcode_portal( array $atts = [] ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}

		$amb = OE_Amb_Ambassador::find_by_user( get_current_user_id() );

		if ( ! $amb ) {
			$apply_id  = (int) OE_Ambassador::setting( 'apply_page_id', 0 );
			$apply_url = $apply_id ? get_permalink( $apply_id ) : home_url( '/' );
			return '<div class="oe-amb-status-card oe-amb-status-pending">
				<div class="oe-amb-status-icon">👋</div>
				<h3>' . esc_html__( 'Not an Ambassador Yet?', 'oe-ambassador' ) . '</h3>
				<p>' . esc_html__( 'Apply to become a brand ambassador and start earning commissions.', 'oe-ambassador' ) . '</p>
				<a href="' . esc_url( $apply_url ) . '" class="oe-amb-btn">' . esc_html__( 'Apply Now →', 'oe-ambassador' ) . '</a>
			</div>';
		}

		if ( ! $amb->is_approved() ) {
			return $this->render_application_status( $amb );
		}

		ob_start();
		include OE_AMB_DIR . 'public/views/portal-dashboard.php';
		return ob_get_clean();
	}

	private function render_login_prompt(): string {
		$login_url = wp_login_url( get_permalink() );
		return '<div class="oe-amb-status-card oe-amb-status-pending">
			<div class="oe-amb-status-icon">🔐</div>
			<h3>' . esc_html__( 'Login Required', 'oe-ambassador' ) . '</h3>
			<p>' . esc_html__( 'Please log in to access your ambassador portal.', 'oe-ambassador' ) . '</p>
			<a href="' . esc_url( $login_url ) . '" class="oe-amb-btn">' . esc_html__( 'Log In →', 'oe-ambassador' ) . '</a>
		</div>';
	}

	// ── Application AJAX handler ───────────────────────────────────────────────

	public function handle_application(): void {
		check_ajax_referer( 'oe_amb_apply', 'nonce' );

		$first_name      = sanitize_text_field( $_POST['first_name'] ?? '' );
		$last_name       = sanitize_text_field( $_POST['last_name']  ?? '' );
		$email           = sanitize_email( $_POST['email'] ?? '' );
		$phone           = sanitize_text_field( $_POST['phone'] ?? '' );
		$social_platform = sanitize_key( $_POST['social_platform'] ?? '' );
		$social_handle   = sanitize_text_field( $_POST['social_handle'] ?? '' );
		$website         = esc_url_raw( $_POST['website'] ?? '' );
		$motivation      = sanitize_textarea_field( $_POST['motivation'] ?? '' );

		// Validation
		if ( ! $first_name || ! $last_name ) {
			wp_send_json_error( __( 'Please enter your full name.', 'oe-ambassador' ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( __( 'Please enter a valid email address.', 'oe-ambassador' ) );
		}
		if ( ! $motivation || strlen( $motivation ) < 30 ) {
			wp_send_json_error( __( 'Please tell us more about yourself (at least 30 characters).', 'oe-ambassador' ) );
		}

		// Check existing
		$existing = OE_Amb_DB::get_ambassador_by_email( $email );
		if ( $existing ) {
			wp_send_json_error( __( 'An application with this email already exists.', 'oe-ambassador' ) );
		}

		// Linked WP user
		$user_id = 0;
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
		} else {
			$user = get_user_by( 'email', $email );
			$user_id = $user ? $user->ID : 0;
		}

		// Insert application
		$amb             = new OE_Amb_Ambassador();
		$amb->first_name      = $first_name;
		$amb->last_name       = $last_name;
		$amb->email           = $email;
		$amb->phone           = $phone;
		$amb->social_platform = $social_platform;
		$amb->social_handle   = ltrim( $social_handle, '@' );
		$amb->website         = $website;
		$amb->motivation      = $motivation;
		$amb->status          = 'pending';
		$amb->user_id         = $user_id;

		if ( ! $amb->save() ) {
			wp_send_json_error( __( 'Failed to submit application. Please try again.', 'oe-ambassador' ) );
		}

		// Notify admin
		OE_Amb_Email::send_new_application_admin( $amb );

		wp_send_json_success( __( 'Application submitted successfully! We\'ll review it within 2–3 business days and notify you by email.', 'oe-ambassador' ) );
	}
}

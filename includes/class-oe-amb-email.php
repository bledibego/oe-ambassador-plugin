<?php
/**
 * Email notifications for OE Ambassador.
 *
 * Uses wp_mail() with HTML content. All emails respect the
 * 'from_name' and 'from_email' plugin settings.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OE_Amb_Email {

	// ── Core send helper ──────────────────────────────────────────────────────

	private static function send( string $to, string $subject, string $body ): bool {
		$from_name  = OE_Ambassador::setting( 'from_name',  get_bloginfo( 'name' ) );
		$from_email = OE_Ambassador::setting( 'from_email', get_option( 'admin_email' ) );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			"From: $from_name <$from_email>",
		];

		return wp_mail( $to, $subject, self::wrap( $subject, $body ), $headers );
	}

	/** Wraps the body in a clean HTML email shell. */
	private static function wrap( string $title, string $body ): string {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$logo_url  = get_site_icon_url( 64 );
		$year      = date( 'Y' );

		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:30px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08)">
      <!-- Header -->
      <tr><td style="background:#1a1a2e;padding:32px 40px;text-align:center">
        <?php if ( $logo_url ) : ?>
          <img src="<?php echo esc_url( $logo_url ); ?>" width="48" height="48" alt="" style="border-radius:8px;margin-bottom:12px;display:block;margin:0 auto 12px">
        <?php endif; ?>
        <span style="color:#c9a96e;font-size:22px;font-weight:700;letter-spacing:1px"><?php echo esc_html( $site_name ); ?></span>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:36px 40px;color:#333;font-size:15px;line-height:1.7">
        <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput ?>
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#f8f8f8;padding:20px 40px;text-align:center;font-size:12px;color:#999;border-top:1px solid #eee">
        © <?php echo esc_html( $year ); ?> <a href="<?php echo esc_url( $site_url ); ?>" style="color:#c9a96e;text-decoration:none"><?php echo esc_html( $site_name ); ?></a> · Ambassador Program
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	// ── Application notifications ─────────────────────────────────────────────

	/**
	 * Notify admin that a new application was received.
	 */
	public static function send_new_application_admin( OE_Amb_Ambassador $amb ): void {
		$admin_email  = OE_Ambassador::setting( 'notify_admin_email', get_option( 'admin_email' ) );
		$subject      = sprintf( __( 'New Ambassador Application: %s', 'oe-ambassador' ), $amb->full_name() );
		$review_url   = admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $amb->id );

		ob_start();
		?>
<h2 style="color:#1a1a2e;margin:0 0 16px">New Ambassador Application</h2>
<p>A new ambassador application has been submitted and is waiting for your review.</p>
<table style="width:100%;border-collapse:collapse;margin:20px 0">
  <tr><td style="padding:8px 12px;background:#f8f8f8;font-weight:600;width:40%">Name</td><td style="padding:8px 12px"><?php echo esc_html( $amb->full_name() ); ?></td></tr>
  <tr><td style="padding:8px 12px;font-weight:600">Email</td><td style="padding:8px 12px"><?php echo esc_html( $amb->email ); ?></td></tr>
  <tr><td style="padding:8px 12px;background:#f8f8f8;font-weight:600">Phone</td><td style="padding:8px 12px;background:#f8f8f8"><?php echo esc_html( $amb->phone ); ?></td></tr>
  <tr><td style="padding:8px 12px;font-weight:600">Platform</td><td style="padding:8px 12px"><?php echo esc_html( ucfirst( $amb->social_platform ) . ( $amb->social_handle ? ' · @' . $amb->social_handle : '' ) ); ?></td></tr>
  <?php if ( $amb->website ) : ?>
  <tr><td style="padding:8px 12px;background:#f8f8f8;font-weight:600">Website</td><td style="padding:8px 12px;background:#f8f8f8"><?php echo esc_html( $amb->website ); ?></td></tr>
  <?php endif; ?>
  <tr><td style="padding:8px 12px;font-weight:600;vertical-align:top">Motivation</td><td style="padding:8px 12px"><?php echo nl2br( esc_html( $amb->motivation ) ); ?></td></tr>
</table>
<p style="text-align:center;margin-top:28px">
  <a href="<?php echo esc_url( $review_url ); ?>" style="display:inline-block;background:#c9a96e;color:#1a1a2e;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:8px">Review Application →</a>
</p>
		<?php
		self::send( $admin_email, $subject, ob_get_clean() );
	}

	/**
	 * Send approval email to ambassador with their codes.
	 */
	public static function send_approval( OE_Amb_Ambassador $amb ): void {
		$portal_page_id = (int) OE_Ambassador::setting( 'portal_page_id', 0 );
		$portal_url     = $portal_page_id ? get_permalink( $portal_page_id ) : home_url( '/ambassador' );
		$subject        = __( 'Welcome to the Ambassador Program! 🎉', 'oe-ambassador' );

		ob_start();
		?>
<h2 style="color:#1a1a2e;margin:0 0 8px">Congratulations, <?php echo esc_html( $amb->first_name ); ?>! 🎉</h2>
<p style="color:#666;margin:0 0 24px">Your application has been approved. Welcome to the <strong><?php echo esc_html( get_bloginfo('name') ); ?> Ambassador Program!</strong></p>

<div style="background:#f8f4ed;border-left:4px solid #c9a96e;padding:20px 24px;border-radius:0 8px 8px 0;margin-bottom:24px">
  <h3 style="margin:0 0 16px;color:#1a1a2e">Your Discount Codes</h3>

  <table style="width:100%">
    <tr>
      <td style="padding:6px 0">
        <strong>Customer Code</strong><br>
        <small style="color:#666">Share this with your followers — they get <?php echo (int) $amb->coupon_pct; ?>% off their order</small>
      </td>
      <td style="text-align:right">
        <span style="font-size:20px;font-weight:700;color:#c9a96e;letter-spacing:2px;font-family:monospace"><?php echo esc_html( strtoupper( $amb->coupon_code ) ); ?></span>
      </td>
    </tr>
    <?php if ( $amb->self_code ) : ?>
    <tr style="border-top:1px solid #e8d9bb">
      <td style="padding:12px 0 6px">
        <strong>Your Personal Code</strong><br>
        <small style="color:#666">Use this for your own purchases — <?php echo (int) $amb->self_pct; ?>% off</small>
      </td>
      <td style="text-align:right;padding-top:12px">
        <span style="font-size:20px;font-weight:700;color:#1a1a2e;letter-spacing:2px;font-family:monospace"><?php echo esc_html( strtoupper( $amb->self_code ) ); ?></span>
      </td>
    </tr>
    <?php endif; ?>
  </table>
</div>

<?php if ( ! empty( $amb->free_products ) ) :
  $product_names = array_map( fn($pid) => get_the_title($pid), $amb->free_products );
  $product_names = array_filter($product_names);
?>
<div style="background:#eef8f0;border-left:4px solid #4caf50;padding:20px 24px;border-radius:0 8px 8px 0;margin-bottom:24px">
  <strong>🎁 Free Products Allocated</strong><br>
  <span style="color:#666"><?php echo esc_html( implode( ', ', $product_names ) ); ?></span>
</div>
<?php endif; ?>

<h3 style="color:#1a1a2e">How Commissions Work</h3>
<p>Every time someone places an order using your code, you earn a commission based on your monthly sales tier:</p>
<?php
$tiers = OE_Ambassador::get_tiers();
echo '<ul style="padding-left:20px;color:#555">';
foreach ( $tiers as $tier ) {
	$max_label = ( (int) $tier['max'] === -1 ) ? '+' : '–' . $tier['max'];
	echo '<li style="margin-bottom:6px">' . esc_html( $tier['min'] . $max_label . ' sales/month → ' . $tier['pct'] . '% commission' ) . '</li>';
}
echo '</ul>';
?>
<p style="color:#666;font-size:13px">Commission is calculated on the net order total (excluding VAT and shipping).</p>

<p style="text-align:center;margin-top:32px">
  <a href="<?php echo esc_url( $portal_url ); ?>" style="display:inline-block;background:#1a1a2e;color:#c9a96e;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:8px">View Your Ambassador Portal →</a>
</p>
		<?php
		self::send( $amb->email, $subject, ob_get_clean() );
	}

	/**
	 * Send rejection email.
	 */
	public static function send_rejection( OE_Amb_Ambassador $amb ): void {
		$subject = __( 'Your Ambassador Application', 'oe-ambassador' );

		ob_start();
		?>
<h2 style="color:#1a1a2e;margin:0 0 16px">Hi <?php echo esc_html( $amb->first_name ); ?>,</h2>
<p>Thank you for your interest in the <strong><?php echo esc_html( get_bloginfo('name') ); ?> Ambassador Program</strong>.</p>
<p>After reviewing your application, we're unable to move forward at this time. We appreciate you taking the time to apply and encourage you to stay connected with us.</p>
<p>If you have any questions, please don't hesitate to reply to this email.</p>
<p>Best regards,<br><strong><?php echo esc_html( get_bloginfo('name') ); ?> Team</strong></p>
		<?php
		self::send( $amb->email, $subject, ob_get_clean() );
	}

	// ── Monthly reports ───────────────────────────────────────────────────────

	/**
	 * Send monthly performance report to an ambassador.
	 *
	 * @param OE_Amb_Ambassador $amb
	 * @param string            $month      Format: "YYYY-MM"
	 * @param array             $commissions Result from OE_Amb_DB::get_commissions()
	 */
	public static function send_monthly_report( OE_Amb_Ambassador $amb, string $month, array $commissions ): void {
		$month_label = date( 'F Y', strtotime( $month . '-01' ) );
		$subject     = sprintf( __( 'Your Ambassador Report — %s', 'oe-ambassador' ), $month_label );
		$portal_url  = get_permalink( (int) OE_Ambassador::setting( 'portal_page_id', 0 ) ) ?: home_url( '/ambassador' );
		$currency    = OE_Ambassador::setting( 'currency', 'SEK' );
		$total_pct   = OE_Ambassador::tier_pct( $commissions['total'] );

		ob_start();
		?>
<h2 style="color:#1a1a2e;margin:0 0 8px">Your Report for <?php echo esc_html( $month_label ); ?></h2>
<p style="color:#666;margin:0 0 24px">Hi <?php echo esc_html( $amb->first_name ); ?>, here's your performance summary.</p>

<div style="display:flex;gap:16px;margin-bottom:24px">
  <div style="flex:1;background:#1a1a2e;color:#c9a96e;padding:20px;border-radius:10px;text-align:center">
    <div style="font-size:32px;font-weight:700"><?php echo (int) $commissions['total']; ?></div>
    <div style="font-size:13px;opacity:.8">Sales</div>
  </div>
  <div style="flex:1;background:#f8f4ed;padding:20px;border-radius:10px;text-align:center">
    <div style="font-size:32px;font-weight:700;color:#1a1a2e"><?php echo number_format( $commissions['sum_commission'], 2 ); ?></div>
    <div style="font-size:13px;color:#888"><?php echo esc_html( $currency ); ?> Commission</div>
  </div>
  <div style="flex:1;background:#f8f4ed;padding:20px;border-radius:10px;text-align:center">
    <div style="font-size:32px;font-weight:700;color:#1a1a2e"><?php echo (int) $total_pct; ?>%</div>
    <div style="font-size:13px;color:#888">Your Tier</div>
  </div>
</div>

<h3 style="color:#1a1a2e">Order Breakdown</h3>
<table style="width:100%;border-collapse:collapse;font-size:13px">
  <thead>
    <tr style="background:#1a1a2e;color:#fff">
      <th style="padding:10px;text-align:left">Order</th>
      <th style="padding:10px;text-align:left">Date</th>
      <th style="padding:10px;text-align:right">Net Amount</th>
      <th style="padding:10px;text-align:right">Tier</th>
      <th style="padding:10px;text-align:right">Commission</th>
      <th style="padding:10px;text-align:center">Status</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $i = 0;
  foreach ( $commissions['items'] as $com ) :
    $bg = ( $i++ % 2 ) ? '#f8f8f8' : '#fff';
  ?>
    <tr style="background:<?php echo $bg; ?>">
      <td style="padding:9px 10px">#<?php echo (int) $com->order_id; ?></td>
      <td style="padding:9px 10px"><?php echo esc_html( date( 'd M', strtotime( $com->order_date ) ) ); ?></td>
      <td style="padding:9px 10px;text-align:right"><?php echo number_format( (float) $com->net_amount, 2 ); ?></td>
      <td style="padding:9px 10px;text-align:right"><?php echo number_format( (float) $com->tier_pct, 1 ); ?>%</td>
      <td style="padding:9px 10px;text-align:right;font-weight:600"><?php echo number_format( (float) $com->commission, 2 ); ?></td>
      <td style="padding:9px 10px;text-align:center">
        <span style="font-size:11px;padding:3px 8px;border-radius:99px;background:<?php echo $com->status === 'paid' ? '#e8f5e9' : '#fff3e0'; ?>;color:<?php echo $com->status === 'paid' ? '#2e7d32' : '#e65100'; ?>">
          <?php echo esc_html( ucfirst( $com->status ) ); ?>
        </span>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr style="background:#1a1a2e;color:#c9a96e;font-weight:700">
      <td colspan="4" style="padding:10px">Total</td>
      <td style="padding:10px;text-align:right"><?php echo number_format( $commissions['sum_commission'], 2 ); ?> <?php echo esc_html( $currency ); ?></td>
      <td></td>
    </tr>
  </tfoot>
</table>

<p style="margin-top:16px;font-size:13px;color:#888">Net amount = order total − tax − shipping. Commission is calculated on the net amount using your active tier.</p>

<p style="text-align:center;margin-top:28px">
  <a href="<?php echo esc_url( $portal_url ); ?>" style="display:inline-block;background:#c9a96e;color:#1a1a2e;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:8px">View Full Portal →</a>
</p>
		<?php
		self::send( $amb->email, $subject, ob_get_clean() );
	}

	/**
	 * Admin monthly summary email.
	 */
	public static function send_admin_monthly_summary( array $summary, string $month ): void {
		$admin_email = OE_Ambassador::setting( 'notify_admin_email', get_option( 'admin_email' ) );
		$month_label = date( 'F Y', strtotime( $month . '-01' ) );
		$subject     = sprintf( __( 'Ambassador Program Summary — %s', 'oe-ambassador' ), $month_label );
		$admin_url   = admin_url( 'admin.php?page=oe-ambassador-reports' );

		ob_start();
		?>
<h2 style="color:#1a1a2e;margin:0 0 16px">Ambassador Program Summary — <?php echo esc_html( $month_label ); ?></h2>
<table style="width:100%;border-collapse:collapse;font-size:13px">
  <thead>
    <tr style="background:#1a1a2e;color:#fff">
      <th style="padding:10px;text-align:left">Ambassador</th>
      <th style="padding:10px;text-align:right">Sales</th>
      <th style="padding:10px;text-align:right">Total Commission</th>
      <th style="padding:10px;text-align:left">Last Sale</th>
    </tr>
  </thead>
  <tbody>
  <?php $i = 0; foreach ( $summary as $row ) : $bg = ( $i++ % 2 ) ? '#f8f8f8' : '#fff'; ?>
    <tr style="background:<?php echo $bg; ?>">
      <td style="padding:9px 10px"><?php echo esc_html( $row->first_name . ' ' . $row->last_name ); ?></td>
      <td style="padding:9px 10px;text-align:right"><?php echo (int) $row->total_orders; ?></td>
      <td style="padding:9px 10px;text-align:right;font-weight:600"><?php echo number_format( (float) $row->total_commission, 2 ); ?></td>
      <td style="padding:9px 10px"><?php echo $row->last_sale ? esc_html( date( 'd M Y', strtotime( $row->last_sale ) ) ) : '—'; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p style="text-align:center;margin-top:28px">
  <a href="<?php echo esc_url( $admin_url ); ?>" style="display:inline-block;background:#1a1a2e;color:#c9a96e;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:8px">View Reports in Admin →</a>
</p>
		<?php
		self::send( $admin_email, $subject, ob_get_clean() );
	}

	/**
	 * Payout notification to ambassador.
	 */
	public static function send_payout_notification( OE_Amb_Ambassador $amb, object $payout ): void {
		$subject     = __( 'Commission Payment Processed! 💸', 'oe-ambassador' );
		$currency    = $payout->currency;
		$portal_url  = get_permalink( (int) OE_Ambassador::setting( 'portal_page_id', 0 ) ) ?: home_url( '/ambassador' );

		ob_start();
		?>
<h2 style="color:#1a1a2e;margin:0 0 8px">Payment Processed! 💸</h2>
<p>Hi <?php echo esc_html( $amb->first_name ); ?>, your commission for the period <strong><?php echo esc_html( date( 'd M', strtotime( $payout->period_start ) ) ); ?> – <?php echo esc_html( date( 'd M Y', strtotime( $payout->period_end ) ) ); ?></strong> has been processed.</p>
<div style="background:#e8f5e9;border-radius:10px;padding:24px;text-align:center;margin:24px 0">
  <div style="font-size:42px;font-weight:700;color:#2e7d32"><?php echo number_format( (float) $payout->payout_amount, 2 ); ?> <small style="font-size:18px"><?php echo esc_html( $currency ); ?></small></div>
  <div style="color:#555;margin-top:6px">Based on <?php echo (int) $payout->total_sales; ?> sales · <?php echo number_format( (float) $payout->tier_pct, 1 ); ?>% tier</div>
</div>
<?php if ( $payout->notes ) : ?>
<p style="color:#555"><strong>Note from team:</strong> <?php echo nl2br( esc_html( $payout->notes ) ); ?></p>
<?php endif; ?>
<p style="text-align:center;margin-top:24px">
  <a href="<?php echo esc_url( $portal_url ); ?>" style="display:inline-block;background:#1a1a2e;color:#c9a96e;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:8px">View in Portal →</a>
</p>
		<?php
		self::send( $amb->email, $subject, ob_get_clean() );
	}
}

// Hook into action fired by commission class
add_action( 'oe_amb_payout_created', function( int $payout_id, int $ambassador_id ) {
	$payout = OE_Amb_DB::get_payout( $payout_id );
	$amb    = OE_Amb_Ambassador::find( $ambassador_id );
	if ( $payout && $amb ) {
		OE_Amb_Email::send_payout_notification( $amb, $payout );
	}
}, 10, 2 );

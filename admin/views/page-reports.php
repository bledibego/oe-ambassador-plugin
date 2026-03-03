<?php
/**
 * Reports page view.
 *
 * @package OE_Ambassador
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$month    = sanitize_text_field( wp_unslash( $_GET['month'] ?? gmdate( 'Y-m' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
[ $y, $m ] = explode( '-', $month );
$date_from = "$y-$m-01 00:00:00";
$date_to   = gmdate( 'Y-m-t 23:59:59', mktime( 0, 0, 0, (int)$m, 1, (int)$y ) );
$prev_month = gmdate( 'Y-m', mktime( 0, 0, 0, (int)$m - 1, 1, (int)$y ) );
$next_month = gmdate( 'Y-m', mktime( 0, 0, 0, (int)$m + 1, 1, (int)$y ) );
$currency   = OE_Ambassador::setting( 'currency', 'SEK' );

$summary = OE_Amb_DB::get_commission_summary();
$com_badge = [
    'pending'   => '#fff3e0;color:#e65100',
    'approved'  => '#e8f5e9;color:#2e7d32',
    'paid'      => '#e3f2fd;color:#1565c0',
    'cancelled' => '#fce4ec;color:#c62828',
];

global $wpdb;
$ct = OE_Amb_DB::com_table();
$at = OE_Amb_DB::amb_table();

// All commissions for this month
$month_commissions = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$wpdb->prepare(
		"SELECT c.*, a.first_name, a.last_name, a.coupon_code FROM `{$ct}` c JOIN `{$at}` a ON c.ambassador_id = a.id WHERE c.order_date BETWEEN %s AND %s AND c.status != 'cancelled' ORDER BY c.order_date DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$date_from,
		$date_to
	)
);

$month_total_sales = count( $month_commissions );
$month_total_comm  = array_sum( array_column( $month_commissions, 'commission' ) );

// Pending commissions (all time)
$pending_all = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	"SELECT c.*, a.first_name, a.last_name, a.coupon_code FROM `{$ct}` c JOIN `{$at}` a ON c.ambassador_id = a.id WHERE c.status = 'pending' ORDER BY c.order_date DESC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);
?>
<div class="wrap oe-amb-wrap">
<h1><?php esc_html_e( 'Commission Reports', 'oe-ambassador' ); ?></h1>

<!-- Month navigator -->
<div class="oe-amb-card" style="margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:16px">
        <a href="<?php echo esc_url( add_query_arg( 'month', $prev_month ) ); ?>" class="button">&laquo; <?php echo esc_html( gmdate( 'M Y', strtotime( $prev_month . '-01' ) ) ); ?></a>
        <h2 style="margin:0;flex:1;text-align:center"><?php echo esc_html( gmdate( 'F Y', strtotime( $month . '-01' ) ) ); ?></h2>
        <a href="<?php echo esc_url( add_query_arg( 'month', $next_month ) ); ?>" class="button"><?php echo esc_html( gmdate( 'M Y', strtotime( $next_month . '-01' ) ) ); ?> &raquo;</a>
    </div>
</div>

<!-- Month summary -->
<div class="oe-amb-stat-grid" style="margin-bottom:16px">
    <div class="oe-amb-stat-card">
        <div class="oe-amb-stat-icon" style="background:#e3f2fd;color:#1565c0">🛍</div>
        <div>
            <div class="oe-amb-stat-value"><?php echo (int) $month_total_sales; ?></div>
            <div class="oe-amb-stat-label"><?php esc_html_e( 'Ambassador Sales', 'oe-ambassador' ); ?></div>
        </div>
    </div>
    <div class="oe-amb-stat-card">
        <div class="oe-amb-stat-icon" style="background:#f3e5f5;color:#6a1b9a">💰</div>
        <div>
            <div class="oe-amb-stat-value"><?php echo number_format( $month_total_comm, 0 ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
            <div class="oe-amb-stat-label"><?php esc_html_e( 'Total Commission', 'oe-ambassador' ); ?></div>
        </div>
    </div>
    <div class="oe-amb-stat-card">
        <div class="oe-amb-stat-icon" style="background:#fff3e0;color:#e65100">⏳</div>
        <div>
            <div class="oe-amb-stat-value"><?php echo count( $pending_all ); ?></div>
            <div class="oe-amb-stat-label"><?php esc_html_e( 'Pending Approval (All)', 'oe-ambassador' ); ?></div>
        </div>
    </div>
</div>

<!-- Pending commissions (all time) — need action -->
<?php if ( ! empty( $pending_all ) ) : ?>
<div class="oe-amb-card" style="margin-bottom:16px;border:2px solid #c9a96e">
    <div class="oe-amb-card-header"><h2>⚡ <?php esc_html_e( 'Pending Commissions — Needs Approval', 'oe-ambassador' ); ?></h2></div>
    <table class="widefat striped" style="font-size:13px">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Ambassador', 'oe-ambassador' ); ?></th>
                <th><?php esc_html_e( 'Order', 'oe-ambassador' ); ?></th>
                <th><?php esc_html_e( 'Date', 'oe-ambassador' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'NET', 'oe-ambassador' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'Tier', 'oe-ambassador' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'Commission', 'oe-ambassador' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $pending_all as $com ) : ?>
            <tr>
                <td>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $com->ambassador_id ) ); ?>"><?php echo esc_html( $com->first_name . ' ' . $com->last_name ); ?></a><br>
                    <small style="color:#888;font-family:monospace"><?php echo esc_html( strtoupper( $com->coupon_code ) ); ?></small>
                </td>
                <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $com->order_id . '&action=edit' ) ); ?>">#<?php echo (int) $com->order_id; ?></a></td>
                <td><?php echo esc_html( gmdate( 'd M Y', strtotime( $com->order_date ) ) ); ?></td>
                <td style="text-align:right"><?php echo number_format( (float) $com->net_amount, 2 ); ?></td>
                <td style="text-align:right"><?php echo number_format( (float) $com->tier_pct, 1 ); ?>%</td>
                <td style="text-align:right;font-weight:700"><?php echo number_format( (float) $com->commission, 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                <td>
                    <button class="button button-primary button-small oe-approve-commission" data-id="<?php echo (int) $com->id; ?>"><?php esc_html_e( 'Approve', 'oe-ambassador' ); ?></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Monthly commission table by ambassador -->
<div class="oe-amb-card">
    <div class="oe-amb-card-header">
        <h2><?php
        /* translators: %s is the month and year, e.g. "January 2025" */
        printf( esc_html__( 'By Ambassador — %s', 'oe-ambassador' ), esc_html( gmdate( 'F Y', strtotime( $month . '-01' ) ) ) ); ?></h2>
    </div>
    <table class="widefat striped" style="font-size:13px">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Ambassador', 'oe-ambassador' ); ?></th>
                <th><?php esc_html_e( 'Code', 'oe-ambassador' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'Orders', 'oe-ambassador' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'Tier', 'oe-ambassador' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'NET Revenue', 'oe-ambassador' ); ?></th>
                <th style="text-align:right"><?php esc_html_e( 'Commission', 'oe-ambassador' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php
        // Group by ambassador
        $by_amb = [];
        foreach ( $month_commissions as $com ) {
            $aid = $com->ambassador_id;
            if ( ! isset( $by_amb[ $aid ] ) ) {
                $by_amb[ $aid ] = [
                    'name'       => $com->first_name . ' ' . $com->last_name,
                    'code'       => $com->coupon_code,
                    'orders'     => 0,
                    'net'        => 0,
                    'commission' => 0,
                    'tier_pct'   => $com->tier_pct,
                ];
            }
            $by_amb[ $aid ]['orders']++;
            $by_amb[ $aid ]['net']        += $com->net_amount;
            $by_amb[ $aid ]['commission'] += $com->commission;
        }
        if ( empty( $by_amb ) ) :
        ?>
            <tr><td colspan="7" style="text-align:center;padding:24px;color:#888"><?php esc_html_e( 'No sales this month.', 'oe-ambassador' ); ?></td></tr>
        <?php else : foreach ( $by_amb as $aid => $data ) : ?>
            <tr>
                <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $aid ) ); ?>"><?php echo esc_html( $data['name'] ); ?></a></td>
                <td style="font-family:monospace;font-weight:600"><?php echo esc_html( strtoupper( $data['code'] ) ); ?></td>
                <td style="text-align:right"><?php echo (int) $data['orders']; ?></td>
                <td style="text-align:right"><?php echo number_format( (float) $data['tier_pct'], 1 ); ?>%</td>
                <td style="text-align:right"><?php echo number_format( (float) $data['net'], 2 ); ?></td>
                <td style="text-align:right;font-weight:700"><?php echo number_format( (float) $data['commission'], 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                <td>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $aid ) ); ?>" class="button button-small"><?php esc_html_e( 'Payout', 'oe-ambassador' ); ?></a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</div>

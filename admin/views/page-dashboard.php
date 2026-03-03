<?php
/**
 * Admin Dashboard view.
 *
 * @package OE_Ambassador
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$stats_pending  = OE_Amb_DB::get_ambassadors( [ 'status' => 'pending',  'per_page' => 9999 ] )['total'];
$stats_approved = OE_Amb_DB::get_ambassadors( [ 'status' => 'approved', 'per_page' => 9999 ] )['total'];
$commission_sum = OE_Amb_DB::get_commission_summary();

$month_start = date( 'Y-m-01 00:00:00' );
$month_end   = date( 'Y-m-t 23:59:59' );

global $wpdb;
$this_month_sales = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . OE_Amb_DB::com_table() . " WHERE order_date BETWEEN '$month_start' AND '$month_end' AND status != 'cancelled'"
);
$this_month_comm = (float) $wpdb->get_var(
	"SELECT SUM(commission) FROM " . OE_Amb_DB::com_table() . " WHERE order_date BETWEEN '$month_start' AND '$month_end' AND status != 'cancelled'"
);
$pending_comm    = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . OE_Amb_DB::com_table() . " WHERE status = 'pending'"
);

$currency = OE_Ambassador::setting( 'currency', 'SEK' );
$recent   = OE_Amb_DB::get_ambassadors( [ 'per_page' => 5 ] );
?>
<div class="wrap oe-amb-wrap">
<h1 class="wp-heading-inline"><?php esc_html_e( 'Ambassador Dashboard', 'oe-ambassador' ); ?></h1>
<hr class="wp-header-end">

<!-- Stat cards -->
<div class="oe-amb-stat-grid">
    <div class="oe-amb-stat-card">
        <div class="oe-amb-stat-icon" style="background:#fff3e0;color:#e65100">⏳</div>
        <div>
            <div class="oe-amb-stat-value"><?php echo (int) $stats_pending; ?></div>
            <div class="oe-amb-stat-label"><?php esc_html_e( 'Pending Applications', 'oe-ambassador' ); ?></div>
        </div>
    </div>
    <div class="oe-amb-stat-card">
        <div class="oe-amb-stat-icon" style="background:#e8f5e9;color:#2e7d32">✓</div>
        <div>
            <div class="oe-amb-stat-value"><?php echo (int) $stats_approved; ?></div>
            <div class="oe-amb-stat-label"><?php esc_html_e( 'Active Ambassadors', 'oe-ambassador' ); ?></div>
        </div>
    </div>
    <div class="oe-amb-stat-card">
        <div class="oe-amb-stat-icon" style="background:#e3f2fd;color:#1565c0">🛍</div>
        <div>
            <div class="oe-amb-stat-value"><?php echo (int) $this_month_sales; ?></div>
            <div class="oe-amb-stat-label"><?php esc_html_e( 'Sales This Month', 'oe-ambassador' ); ?></div>
        </div>
    </div>
    <div class="oe-amb-stat-card">
        <div class="oe-amb-stat-icon" style="background:#f3e5f5;color:#6a1b9a">💰</div>
        <div>
            <div class="oe-amb-stat-value"><?php echo number_format( $this_month_comm, 0 ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
            <div class="oe-amb-stat-label"><?php esc_html_e( 'Commissions This Month', 'oe-ambassador' ); ?></div>
        </div>
    </div>
</div>

<div class="oe-amb-row">
    <!-- Recent applications -->
    <div class="oe-amb-card" style="flex:2">
        <div class="oe-amb-card-header">
            <h2><?php esc_html_e( 'Recent Applications', 'oe-ambassador' ); ?></h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=oe-ambassador-ambassadors' ) ); ?>" class="button"><?php esc_html_e( 'View All', 'oe-ambassador' ); ?></a>
        </div>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'oe-ambassador' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'oe-ambassador' ); ?></th>
                    <th><?php esc_html_e( 'Platform', 'oe-ambassador' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'oe-ambassador' ); ?></th>
                    <th><?php esc_html_e( 'Applied', 'oe-ambassador' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $recent['items'] as $row ) :
                $status_class = [
                    'pending'   => 'oe-amb-badge-warning',
                    'approved'  => 'oe-amb-badge-success',
                    'rejected'  => 'oe-amb-badge-danger',
                    'suspended' => 'oe-amb-badge-muted',
                ][ $row->status ] ?? 'oe-amb-badge-muted';
            ?>
                <tr>
                    <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $row->id ) ); ?>"><?php echo esc_html( $row->first_name . ' ' . $row->last_name ); ?></a></td>
                    <td><?php echo esc_html( $row->email ); ?></td>
                    <td><?php echo esc_html( ucfirst( $row->social_platform ) ); ?></td>
                    <td><span class="oe-amb-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
                    <td><?php echo esc_html( date( 'd M Y', strtotime( $row->applied_at ) ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty( $recent['items'] ) ) : ?>
                <tr><td colspan="5" style="text-align:center;padding:24px;color:#888"><?php esc_html_e( 'No applications yet.', 'oe-ambassador' ); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top ambassadors -->
    <div class="oe-amb-card" style="flex:1">
        <div class="oe-amb-card-header">
            <h2><?php esc_html_e( 'Top Ambassadors', 'oe-ambassador' ); ?></h2>
        </div>
        <?php if ( empty( $commission_sum ) ) : ?>
            <p style="text-align:center;color:#888;padding:24px"><?php esc_html_e( 'No commission data yet.', 'oe-ambassador' ); ?></p>
        <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Ambassador', 'oe-ambassador' ); ?></th>
                    <th style="text-align:right"><?php esc_html_e( 'Sales', 'oe-ambassador' ); ?></th>
                    <th style="text-align:right"><?php esc_html_e( 'Commission', 'oe-ambassador' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( array_slice( $commission_sum, 0, 8 ) as $row ) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $row->id ) ); ?>">
                            <?php echo esc_html( $row->first_name . ' ' . $row->last_name ); ?>
                        </a><br>
                        <small style="color:#888"><?php echo esc_html( strtoupper( $row->coupon_code ) ); ?></small>
                    </td>
                    <td style="text-align:right"><?php echo (int) $row->total_orders; ?></td>
                    <td style="text-align:right;font-weight:600"><?php echo number_format( (float) $row->total_commission, 0 ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ( $pending_comm > 0 ) : ?>
        <div class="notice notice-warning inline" style="margin:16px;border-radius:6px">
            <p><?php printf( esc_html__( '%d commission(s) awaiting approval.', 'oe-ambassador' ), $pending_comm ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=oe-ambassador-reports' ) ); ?>"><?php esc_html_e( 'Review', 'oe-ambassador' ); ?></a></p>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

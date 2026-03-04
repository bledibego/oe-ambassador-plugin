<?php
/**
 * Single ambassador detail/edit view.
 *
 * @package OE_Ambassador
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$id  = absint( wp_unslash( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$amb = OE_Amb_Ambassador::find( $id );

if ( ! $amb ) {
    echo '<div class="wrap"><p>' . esc_html__( 'Ambassador not found.', 'oe-brand-ambassador-management' ) . '</p></div>';
    return;
}

$stats    = $amb->monthly_stats();
$lifetime = $amb->lifetime_stats();
$currency = OE_Ambassador::setting( 'currency', 'SEK' );
$tiers    = OE_Ambassador::get_tiers();

// Get recent commissions
$commissions = OE_Amb_DB::get_commissions( $amb->id, [ 'per_page' => 10 ] );

$badge_map = [
    'pending'   => 'oe-amb-badge-warning',
    'approved'  => 'oe-amb-badge-success',
    'rejected'  => 'oe-amb-badge-danger',
    'suspended' => 'oe-amb-badge-muted',
];
$com_badge = [
    'pending'  => '#fff3e0;color:#e65100',
    'approved' => '#e8f5e9;color:#2e7d32',
    'paid'     => '#e3f2fd;color:#1565c0',
    'cancelled'=> '#fce4ec;color:#c62828',
];

$all_products = wc_get_products( [ 'limit' => -1, 'status' => 'publish', 'return' => 'ids' ] );
?>
<div class="wrap oe-amb-wrap">
<h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=oe-ambassador-ambassadors' ) ); ?>" style="font-size:14px;font-weight:400;vertical-align:middle">← <?php esc_html_e( 'All Ambassadors', 'oe-brand-ambassador-management' ); ?></a><br>
    <?php echo esc_html( $amb->full_name() ); ?>
    <span class="oe-amb-badge <?php echo esc_attr( $badge_map[ $amb->status ] ?? '' ); ?>" style="font-size:13px;vertical-align:middle"><?php echo esc_html( ucfirst( $amb->status ) ); ?></span>
</h1>

<div class="oe-amb-row">
    <!-- Left column: Profile + actions -->
    <div style="flex:1">

        <!-- Profile card -->
        <div class="oe-amb-card">
            <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Profile', 'oe-brand-ambassador-management' ); ?></h2></div>
            <table class="form-table" style="margin:0">
                <tr><th><?php esc_html_e( 'Name', 'oe-brand-ambassador-management' ); ?></th><td><?php echo esc_html( $amb->full_name() ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Email', 'oe-brand-ambassador-management' ); ?></th><td><a href="mailto:<?php echo esc_attr( $amb->email ); ?>"><?php echo esc_html( $amb->email ); ?></a></td></tr>
                <tr><th><?php esc_html_e( 'Phone', 'oe-brand-ambassador-management' ); ?></th><td><?php echo esc_html( $amb->phone ) ?: '—'; ?></td></tr>
                <tr><th><?php esc_html_e( 'Platform', 'oe-brand-ambassador-management' ); ?></th><td><?php echo $amb->social_platform ? esc_html( ucfirst( $amb->social_platform ) . ( $amb->social_handle ? ' · @' . $amb->social_handle : '' ) ) : '—'; ?></td></tr>
                <?php if ( $amb->website ) : ?><tr><th><?php esc_html_e( 'Website', 'oe-brand-ambassador-management' ); ?></th><td><a href="<?php echo esc_url( $amb->website ); ?>" target="_blank"><?php echo esc_html( $amb->website ); ?></a></td></tr><?php endif; ?>
                <tr><th><?php esc_html_e( 'Applied', 'oe-brand-ambassador-management' ); ?></th><td><?php echo esc_html( gmdate( 'd M Y H:i', strtotime( $amb->applied_at ) ) ); ?></td></tr>
                <?php if ( $amb->approved_at ) : ?><tr><th><?php esc_html_e( 'Approved', 'oe-brand-ambassador-management' ); ?></th><td><?php echo esc_html( gmdate( 'd M Y', strtotime( $amb->approved_at ) ) ); ?></td></tr><?php endif; ?>
                <tr><th style="vertical-align:top"><?php esc_html_e( 'Motivation', 'oe-brand-ambassador-management' ); ?></th><td><?php echo nl2br( esc_html( $amb->motivation ) ); ?></td></tr>
                <?php if ( $amb->user_id ) : ?>
                <tr><th><?php esc_html_e( 'WP User', 'oe-brand-ambassador-management' ); ?></th>
                    <td><a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $amb->user_id ) ); ?>"><?php echo esc_html( get_userdata( $amb->user_id )->user_login ); ?></a></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Stats card -->
        <div class="oe-amb-card" style="margin-top:16px">
            <div class="oe-amb-card-header"><h2><?php esc_html_e( 'This Month', 'oe-brand-ambassador-management' ); ?></h2></div>
            <div class="oe-amb-stat-grid" style="grid-template-columns:repeat(2,1fr)">
                <div class="oe-amb-stat-card" style="flex-direction:column;text-align:center">
                    <div class="oe-amb-stat-value"><?php echo (int) $stats['total_orders']; ?></div>
                    <div class="oe-amb-stat-label"><?php esc_html_e( 'Sales', 'oe-brand-ambassador-management' ); ?></div>
                </div>
                <div class="oe-amb-stat-card" style="flex-direction:column;text-align:center">
                    <div class="oe-amb-stat-value"><?php echo (int) $stats['tier_pct']; ?>%</div>
                    <div class="oe-amb-stat-label"><?php esc_html_e( 'Tier', 'oe-brand-ambassador-management' ); ?></div>
                </div>
                <div class="oe-amb-stat-card" style="flex-direction:column;text-align:center">
                    <div class="oe-amb-stat-value"><?php echo number_format( $stats['total_commission'], 0 ); ?></div>
                    <div class="oe-amb-stat-label"><?php esc_html_e( 'Commission', 'oe-brand-ambassador-management' ); ?></div>
                </div>
                <div class="oe-amb-stat-card" style="flex-direction:column;text-align:center">
                    <div class="oe-amb-stat-value"><?php echo (int) $lifetime['total_orders']; ?></div>
                    <div class="oe-amb-stat-label"><?php esc_html_e( 'Lifetime Sales', 'oe-brand-ambassador-management' ); ?></div>
                </div>
            </div>
        </div>

        <!-- Approve actions (pending only) -->
        <?php if ( $amb->status === 'pending' ) : ?>
        <div class="oe-amb-card" style="margin-top:16px;border:2px solid #c9a96e">
            <div class="oe-amb-card-header"><h2 style="color:#c9a96e">⚡ <?php esc_html_e( 'Approve Application', 'oe-brand-ambassador-management' ); ?></h2></div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'oe_amb_approve' ); ?>
                <input type="hidden" name="action" value="oe_amb_approve">
                <input type="hidden" name="ambassador_id" value="<?php echo (int) $amb->id; ?>">
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Customer Discount Code', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <input type="text" name="coupon_code" value="<?php echo esc_attr( OE_Amb_Coupon::suggest_code( $amb->first_name, $amb->last_name, 10 ) ); ?>" style="text-transform:uppercase;font-family:monospace;width:180px">
                            <input type="number" name="coupon_pct" value="<?php echo (int) OE_Ambassador::setting('customer_coupon_pct', 10); ?>" min="1" max="100" style="width:60px"> %
                            <p class="description"><?php esc_html_e( 'Code ambassador shares with their audience.', 'oe-brand-ambassador-management' ); ?></p>
                        </td>
                    </tr>
                    <?php if ( oe_amb_is_pro() ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Self-Purchase Code', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <input type="text" name="self_code" value="<?php echo esc_attr( OE_Amb_Coupon::suggest_self_code( $amb->first_name, $amb->last_name ) ); ?>" style="text-transform:uppercase;font-family:monospace;width:180px">
                            <input type="number" name="self_pct" value="<?php echo (int) OE_Ambassador::setting('self_purchase_pct', 20); ?>" min="1" max="100" style="width:60px"> %
                            <p class="description"><?php esc_html_e( "Ambassador's personal discount code.", 'oe-brand-ambassador-management' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Free Products', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <select name="free_products[]" multiple style="height:100px;width:100%;max-width:300px">
                                <?php foreach ( $all_products as $pid ) :
                                    $p = wc_get_product( $pid );
                                    if ( $p ) : ?>
                                    <option value="<?php echo (int) $pid; ?>"><?php echo esc_html( $p->get_name() ); ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple.', 'oe-brand-ambassador-management' ); ?></p>
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr>
                        <th><?php esc_html_e( 'Self-Purchase Code', 'oe-brand-ambassador-management' ); ?></th>
                        <td><span class="oe-amb-pro-badge">🔒 Pro</span> <a href="<?php echo esc_url( oe_amb_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to unlock', 'oe-brand-ambassador-management' ); ?></a></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Free Products', 'oe-brand-ambassador-management' ); ?></th>
                        <td><span class="oe-amb-pro-badge">🔒 Pro</span> <a href="<?php echo esc_url( oe_amb_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to unlock', 'oe-brand-ambassador-management' ); ?></a></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php submit_button( __( 'Approve Ambassador ✓', 'oe-brand-ambassador-management' ), 'primary large' ); ?>
            </form>

            <hr>
            <h3><?php esc_html_e( 'Reject Application', 'oe-brand-ambassador-management' ); ?></h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'oe_amb_reject' ); ?>
                <input type="hidden" name="action" value="oe_amb_reject">
                <input type="hidden" name="ambassador_id" value="<?php echo (int) $amb->id; ?>">
                <p><textarea name="rejection_reason" rows="3" style="width:100%" placeholder="<?php esc_attr_e( 'Optional rejection reason (sent to applicant)...', 'oe-brand-ambassador-management' ); ?>"></textarea></p>
                <?php submit_button( __( 'Reject Application', 'oe-brand-ambassador-management' ), 'delete', 'submit', false ); ?>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right column: Codes + Commissions -->
    <div style="flex:2">

        <!-- Codes & settings -->
        <?php if ( $amb->status === 'approved' ) : ?>
        <div class="oe-amb-card">
            <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Codes & Settings', 'oe-brand-ambassador-management' ); ?></h2></div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'oe_amb_update' ); ?>
                <input type="hidden" name="action" value="oe_amb_update">
                <input type="hidden" name="ambassador_id" value="<?php echo (int) $amb->id; ?>">
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Customer Code', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <code style="font-size:18px;font-weight:700;color:#c9a96e"><?php echo esc_html( strtoupper( $amb->coupon_code ) ); ?></code>
                            — <input type="number" name="coupon_pct" value="<?php echo esc_attr( $amb->coupon_pct ); ?>" min="1" max="100" style="width:60px"> %
                        </td>
                    </tr>
                    <?php if ( oe_amb_is_pro() ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Self-Purchase Code', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <code style="font-size:18px;font-weight:700"><?php echo esc_html( strtoupper( $amb->self_code ) ); ?></code>
                            — <input type="number" name="self_pct" value="<?php echo esc_attr( $amb->self_pct ); ?>" min="1" max="100" style="width:60px"> %
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr>
                        <th><?php esc_html_e( 'Self-Purchase Code', 'oe-brand-ambassador-management' ); ?></th>
                        <td><span class="oe-amb-pro-badge">🔒 Pro</span> <a href="<?php echo esc_url( oe_amb_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to unlock', 'oe-brand-ambassador-management' ); ?></a></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e( 'Free Products', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <select name="free_products[]" multiple style="height:100px;width:100%;max-width:300px">
                                <?php foreach ( $all_products as $pid ) :
                                    $p = wc_get_product( $pid );
                                    if ( $p ) : ?>
                                    <option value="<?php echo (int) $pid; ?>" <?php selected( in_array( $pid, $amb->free_products, true ) ); ?>><?php echo esc_html( $p->get_name() ); ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Status', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <select name="status">
                                <option value="approved"  <?php selected( $amb->status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'oe-brand-ambassador-management' ); ?></option>
                                <option value="suspended" <?php selected( $amb->status, 'suspended' ); ?>><?php esc_html_e( 'Suspended', 'oe-brand-ambassador-management' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Admin Notes', 'oe-brand-ambassador-management' ); ?></th>
                        <td><textarea name="notes" rows="3" style="width:100%"><?php echo esc_textarea( $amb->notes ); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Changes', 'oe-brand-ambassador-management' ) ); ?>
            </form>
        </div>
        <?php endif; ?>

        <!-- Commission payout creator -->
        <?php if ( $amb->status === 'approved' ) : ?>
        <div class="oe-amb-card" style="margin-top:16px">
            <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Create Payout', 'oe-brand-ambassador-management' ); ?></h2></div>
            <p style="color:#666"><?php esc_html_e( 'Mark all approved commissions in a period as paid and notify the ambassador.', 'oe-brand-ambassador-management' ); ?></p>
            <div style="display:flex;gap:12px;align-items:flex-end">
                <label><?php esc_html_e( 'From', 'oe-brand-ambassador-management' ); ?><br><input type="date" id="oe-payout-from" value="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>"></label>
                <label><?php esc_html_e( 'To', 'oe-brand-ambassador-management' ); ?><br><input type="date" id="oe-payout-to" value="<?php echo esc_attr( gmdate( 'Y-m-t' ) ); ?>"></label>
                <label style="flex:1"><?php esc_html_e( 'Notes', 'oe-brand-ambassador-management' ); ?><br><input type="text" id="oe-payout-notes" style="width:100%" placeholder="<?php esc_attr_e( 'Transfer reference, PayPal ID...', 'oe-brand-ambassador-management' ); ?>"></label>
                <button class="button button-primary" id="oe-payout-btn" data-amb="<?php echo (int) $amb->id; ?>"><?php esc_html_e( 'Create Payout', 'oe-brand-ambassador-management' ); ?></button>
            </div>
            <div id="oe-payout-result" style="margin-top:12px"></div>
        </div>
        <?php endif; ?>

        <!-- Recent commissions -->
        <div class="oe-amb-card" style="margin-top:16px">
            <div class="oe-amb-card-header">
                <h2><?php esc_html_e( 'Recent Commissions', 'oe-brand-ambassador-management' ); ?></h2>
                <span style="color:#888"><?php
                /* translators: 1: number of lifetime sales, 2: commission amount, 3: currency code */
                printf( esc_html__( 'Lifetime: %1$d sales · %2$s %3$s commission', 'oe-brand-ambassador-management' ), absint( $lifetime['total_orders'] ), esc_html( number_format( $lifetime['total_commission'], 0 ) ), esc_html( $currency ) ); ?></span>
            </div>
            <table class="widefat striped" style="font-size:13px">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Order', 'oe-brand-ambassador-management' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'oe-brand-ambassador-management' ); ?></th>
                        <th style="text-align:right"><?php esc_html_e( 'Total', 'oe-brand-ambassador-management' ); ?></th>
                        <th style="text-align:right"><?php esc_html_e( 'NET', 'oe-brand-ambassador-management' ); ?></th>
                        <th style="text-align:right"><?php esc_html_e( 'Tier', 'oe-brand-ambassador-management' ); ?></th>
                        <th style="text-align:right"><?php esc_html_e( 'Commission', 'oe-brand-ambassador-management' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'oe-brand-ambassador-management' ); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $commissions['items'] ) ) : ?>
                    <tr><td colspan="8" style="text-align:center;padding:20px;color:#888"><?php esc_html_e( 'No commissions yet.', 'oe-brand-ambassador-management' ); ?></td></tr>
                <?php else : foreach ( $commissions['items'] as $com ) :
                    $bg = $com_badge[ $com->status ] ?? '#f5f5f5;color:#333';
                ?>
                    <tr>
                        <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $com->order_id . '&action=edit' ) ); ?>">#<?php echo (int) $com->order_id; ?></a></td>
                        <td><?php echo esc_html( gmdate( 'd M Y', strtotime( $com->order_date ) ) ); ?></td>
                        <td style="text-align:right"><?php echo number_format( (float) $com->order_total, 0 ); ?></td>
                        <td style="text-align:right"><?php echo number_format( (float) $com->net_amount, 0 ); ?></td>
                        <td style="text-align:right"><?php echo number_format( (float) $com->tier_pct, 1 ); ?>%</td>
                        <td style="text-align:right;font-weight:700"><?php echo number_format( (float) $com->commission, 2 ); ?></td>
                        <td><span style="font-size:11px;padding:3px 8px;border-radius:99px;background:<?php echo esc_attr( $bg ); ?>"><?php echo esc_html( ucfirst( $com->status ) ); ?></span></td>
                        <td>
                        <?php if ( $com->status === 'pending' ) : ?>
                            <button class="button button-small oe-approve-commission" data-id="<?php echo (int) $com->id; ?>"><?php esc_html_e( 'Approve', 'oe-brand-ambassador-management' ); ?></button>
                        <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if ( $commissions['total'] > 0 ) : ?>
                <tfoot>
                    <tr style="font-weight:700;background:#1a1a2e;color:#fff">
                        <td colspan="5"><?php echo (int) $commissions['total']; ?> <?php esc_html_e( 'commissions', 'oe-brand-ambassador-management' ); ?></td>
                        <td style="text-align:right"><?php echo number_format( $commissions['sum_commission'], 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</div>

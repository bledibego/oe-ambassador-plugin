<?php
/**
 * Ambassador portal dashboard view.
 * $amb is available from the calling shortcode.
 *
 * @package OE_Ambassador
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$currency     = OE_Ambassador::setting( 'currency', 'SEK' );
$month        = sanitize_text_field( wp_unslash( $_GET['amb_month'] ?? gmdate( 'Y-m' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
[ $y, $m ]    = explode( '-', $month );
$date_from    = "$y-$m-01 00:00:00";
$date_to      = gmdate( 'Y-m-t 23:59:59', mktime( 0, 0, 0, (int)$m, 1, (int)$y ) );
$prev_month   = gmdate( 'Y-m', mktime( 0, 0, 0, (int)$m - 1, 1, (int)$y ) );
$next_month   = gmdate( 'Y-m', mktime( 0, 0, 0, (int)$m + 1, 1, (int)$y ) );
$month_label  = gmdate( 'F Y', mktime( 0, 0, 0, (int)$m, 1, (int)$y ) );

$monthly  = $amb->monthly_stats( $month );
$lifetime = $amb->lifetime_stats();
$tiers    = OE_Ambassador::get_tiers();

$commissions = OE_Amb_DB::get_commissions( $amb->id, [
    'date_from' => $date_from,
    'date_to'   => $date_to,
    'per_page'  => 9999,
] );

$payouts = OE_Amb_DB::get_payouts( $amb->id, [ 'per_page' => 5 ] );

$share_links = $amb->social_share_links();

$site_name = get_bloginfo( 'name' );
$shop_url  = class_exists('WooCommerce') ? get_permalink( wc_get_page_id('shop') ) : home_url('/');
$ref_url   = add_query_arg( 'ref', $amb->coupon_code, $shop_url );

$com_status_class = [
    'pending'   => 'status-pending',
    'approved'  => 'status-approved',
    'paid'      => 'status-paid',
    'cancelled' => 'status-cancelled',
];
?>
<div class="oe-amb-portal">

    <!-- Header -->
    <div class="oe-amb-portal-header">
        <div>
            <h1><?php
            /* translators: %s is the ambassador's first name */
            printf( esc_html__( 'Welcome back, %s!', 'oe-ambassador' ), esc_html( $amb->first_name ) ); ?></h1>
            <p><?php
            /* translators: %s is the site name */
            printf( esc_html__( 'Your %s Ambassador Dashboard', 'oe-ambassador' ), esc_html( $site_name ) ); ?></p>
        </div>
        <div class="oe-amb-portal-tier-badge">
            <?php
            $current_tier = null;
            foreach ( $tiers as $i => $tier ) {
                $min = (int) $tier['min'];
                $max = (int) $tier['max'];
                if ( $monthly['total_orders'] >= $min && ( $max === -1 || $monthly['total_orders'] <= $max ) ) {
                    $current_tier = $tier;
                    break;
                }
            }
            ?>
            <div class="oe-amb-tier-current"><?php echo $current_tier ? (int) $current_tier['pct'] : 0; ?>%</div>
            <div class="oe-amb-tier-label"><?php esc_html_e( 'Commission Rate', 'oe-ambassador' ); ?></div>
        </div>
    </div>

    <!-- Month navigator -->
    <div class="oe-amb-month-nav">
        <a href="<?php echo esc_url( add_query_arg( 'amb_month', $prev_month ) ); ?>" class="oe-amb-month-btn">&laquo; <?php echo esc_html( gmdate( 'M Y', strtotime( $prev_month . '-01' ) ) ); ?></a>
        <strong><?php echo esc_html( $month_label ); ?></strong>
        <?php if ( $next_month <= gmdate( 'Y-m' ) ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'amb_month', $next_month ) ); ?>" class="oe-amb-month-btn"><?php echo esc_html( gmdate( 'M Y', strtotime( $next_month . '-01' ) ) ); ?> &raquo;</a>
        <?php else : ?>
        <span class="oe-amb-month-btn disabled"></span>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="oe-amb-portal-stats">
        <div class="oe-amb-portal-stat">
            <div class="oe-amb-portal-stat-value"><?php echo (int) $monthly['total_orders']; ?></div>
            <div class="oe-amb-portal-stat-label"><?php esc_html_e( 'Sales This Month', 'oe-ambassador' ); ?></div>
        </div>
        <div class="oe-amb-portal-stat">
            <div class="oe-amb-portal-stat-value"><?php echo number_format( $monthly['total_net'], 0 ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
            <div class="oe-amb-portal-stat-label"><?php esc_html_e( 'NET Revenue', 'oe-ambassador' ); ?></div>
        </div>
        <div class="oe-amb-portal-stat highlighted">
            <div class="oe-amb-portal-stat-value"><?php echo number_format( $monthly['total_commission'], 2 ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
            <div class="oe-amb-portal-stat-label"><?php esc_html_e( 'Your Commission', 'oe-ambassador' ); ?></div>
        </div>
        <div class="oe-amb-portal-stat">
            <div class="oe-amb-portal-stat-value"><?php echo (int) $lifetime['total_orders']; ?></div>
            <div class="oe-amb-portal-stat-label"><?php esc_html_e( 'Lifetime Sales', 'oe-ambassador' ); ?></div>
        </div>
    </div>

    <!-- Two column layout -->
    <div class="oe-amb-portal-grid">

        <!-- Codes & Share -->
        <div class="oe-amb-portal-card">
            <h3><?php esc_html_e( 'Your Discount Codes', 'oe-ambassador' ); ?></h3>

            <div class="oe-amb-code-block">
                <div class="oe-amb-code-label"><?php
                /* translators: %d is the customer discount percentage */
                printf( esc_html__( 'Customer Code (%d%% off)', 'oe-ambassador' ), (int) $amb->coupon_pct ); ?></div>
                <div class="oe-amb-code-display">
                    <span class="oe-amb-code" id="oe-customer-code"><?php echo esc_html( strtoupper( $amb->coupon_code ) ); ?></span>
                    <button class="oe-amb-copy-btn" data-target="oe-customer-code"><?php esc_html_e( 'Copy', 'oe-ambassador' ); ?></button>
                </div>
            </div>

            <?php if ( $amb->self_code ) : ?>
            <div class="oe-amb-code-block" style="margin-top:12px">
                <div class="oe-amb-code-label"><?php
                /* translators: %d is the ambassador's self-purchase discount percentage */
                printf( esc_html__( 'Your Personal Code (%d%% off your orders)', 'oe-ambassador' ), (int) $amb->self_pct ); ?></div>
                <div class="oe-amb-code-display">
                    <span class="oe-amb-code oe-amb-code-self" id="oe-self-code"><?php echo esc_html( strtoupper( $amb->self_code ) ); ?></span>
                    <button class="oe-amb-copy-btn" data-target="oe-self-code"><?php esc_html_e( 'Copy', 'oe-ambassador' ); ?></button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $amb->free_products ) ) : ?>
            <div class="oe-amb-free-products">
                <h4>🎁 <?php esc_html_e( 'Your Free Products', 'oe-ambassador' ); ?></h4>
                <ul>
                <?php foreach ( $amb->free_products as $pid ) :
                    $product = wc_get_product( $pid );
                    if ( $product ) : ?>
                    <li><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></li>
                <?php endif; endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Share links -->
        <div class="oe-amb-portal-card">
            <h3><?php esc_html_e( 'Share & Earn', 'oe-ambassador' ); ?></h3>
            <p class="oe-amb-share-intro"><?php esc_html_e( 'Share your personal link or code to earn commissions.', 'oe-ambassador' ); ?></p>

            <div class="oe-amb-code-block">
                <div class="oe-amb-code-label"><?php esc_html_e( 'Your referral link', 'oe-ambassador' ); ?></div>
                <div class="oe-amb-code-display">
                    <span class="oe-amb-code" id="oe-ref-url" style="font-size:11px;font-family:monospace"><?php echo esc_html( $ref_url ); ?></span>
                    <button class="oe-amb-copy-btn" data-target="oe-ref-url"><?php esc_html_e( 'Copy', 'oe-ambassador' ); ?></button>
                </div>
            </div>

            <div class="oe-amb-social-buttons">
                <a href="<?php echo esc_url( $share_links['facebook'] ); ?>" target="_blank" class="oe-amb-social-btn oe-social-fb">📘 Facebook</a>
                <a href="<?php echo esc_url( $share_links['twitter'] ); ?>"  target="_blank" class="oe-amb-social-btn oe-social-tw">🐦 Twitter</a>
                <a href="<?php echo esc_url( $share_links['whatsapp'] ); ?>" target="_blank" class="oe-amb-social-btn oe-social-wa">💬 WhatsApp</a>
                <a href="<?php echo esc_url( $share_links['linkedin'] ); ?>" target="_blank" class="oe-amb-social-btn oe-social-li">💼 LinkedIn</a>
            </div>

            <p class="oe-amb-share-tip">
                <?php printf(
                    esc_html__( 'On Instagram or TikTok? Mention code %s in your post/story.', 'oe-ambassador' ),
                    '<strong>' . esc_html( strtoupper( $amb->coupon_code ) ) . '</strong>'
                ); ?>
            </p>
        </div>
    </div>

    <!-- Tier progress -->
    <div class="oe-amb-portal-card oe-amb-tiers-card">
        <h3><?php esc_html_e( 'Commission Tiers', 'oe-ambassador' ); ?></h3>
        <div class="oe-amb-tiers">
        <?php foreach ( $tiers as $i => $tier ) :
            $min = (int) $tier['min'];
            $max = (int) $tier['max'];
            $is_active = $monthly['total_orders'] >= $min && ( $max === -1 || $monthly['total_orders'] <= $max );
            $max_label = ( $max === -1 ) ? '+' : '–' . $max;
        ?>
            <div class="oe-amb-tier-card <?php echo $is_active ? 'active' : ''; ?>">
                <?php if ( $is_active ) : ?><div class="oe-amb-tier-active-badge">✓ <?php esc_html_e( 'Current', 'oe-ambassador' ); ?></div><?php endif; ?>
                <div class="oe-amb-tier-num">Tier <?php echo absint( $i + 1 ); ?></div>
                <div class="oe-amb-tier-pct"><?php echo (int) $tier['pct']; ?>%</div>
                <div class="oe-amb-tier-range"><?php echo esc_html( $min . $max_label . ' sales' ); ?></div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php
        // Progress to next tier
        $next_tier = null;
        foreach ( $tiers as $tier ) {
            if ( (int)$tier['min'] > $monthly['total_orders'] ) {
                $next_tier = $tier;
                break;
            }
        }
        if ( $next_tier ) :
            $gap = (int)$next_tier['min'] - $monthly['total_orders'];
        ?>
        <p class="oe-amb-tier-progress"><?php
        /* translators: 1: number of sales needed, 2: commission percentage for next tier */
        printf(
            esc_html__( 'You need %1$d more sale(s) this month to reach %2$d%% commission!', 'oe-ambassador' ),
            absint( $gap ),
            (int) $next_tier['pct']
        ); ?></p>
        <?php endif; ?>
    </div>

    <!-- Order history -->
    <div class="oe-amb-portal-card">
        <h3><?php
        /* translators: %s is the month and year label */
        printf( esc_html__( 'Orders — %s', 'oe-ambassador' ), esc_html( $month_label ) ); ?></h3>

        <?php if ( empty( $commissions['items'] ) ) : ?>
        <p class="oe-amb-empty"><?php esc_html_e( 'No sales this month. Share your code to start earning!', 'oe-ambassador' ); ?></p>
        <?php else : ?>
        <div class="oe-amb-table-wrap">
            <table class="oe-amb-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Order', 'oe-ambassador' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'oe-ambassador' ); ?></th>
                        <th class="num"><?php esc_html_e( 'Order Total', 'oe-ambassador' ); ?></th>
                        <th class="num"><?php esc_html_e( 'NET', 'oe-ambassador' ); ?></th>
                        <th class="num"><?php esc_html_e( 'Tier', 'oe-ambassador' ); ?></th>
                        <th class="num"><?php esc_html_e( 'Commission', 'oe-ambassador' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'oe-ambassador' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $commissions['items'] as $com ) : ?>
                    <tr>
                        <td>#<?php echo (int) $com->order_id; ?></td>
                        <td><?php echo esc_html( gmdate( 'd M', strtotime( $com->order_date ) ) ); ?></td>
                        <td class="num"><?php echo number_format( (float) $com->order_total, 0 ); ?></td>
                        <td class="num"><?php echo number_format( (float) $com->net_amount, 0 ); ?></td>
                        <td class="num"><?php echo number_format( (float) $com->tier_pct, 1 ); ?>%</td>
                        <td class="num commission"><?php echo number_format( (float) $com->commission, 2 ); ?></td>
                        <td><span class="oe-amb-com-status <?php echo esc_attr( $com_status_class[ $com->status ] ?? '' ); ?>"><?php echo esc_html( ucfirst( $com->status ) ); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="oe-amb-table-total">
                        <td colspan="5"><?php esc_html_e( 'Total', 'oe-ambassador' ); ?></td>
                        <td class="num"><?php echo number_format( $commissions['sum_commission'], 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="oe-amb-com-note">
            <?php esc_html_e( 'NET = order total − tax − shipping. Commission = NET × your tier %.', 'oe-ambassador' ); ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Payment history -->
    <?php if ( ! empty( $payouts['items'] ) ) : ?>
    <div class="oe-amb-portal-card">
        <h3><?php esc_html_e( 'Payment History', 'oe-ambassador' ); ?></h3>
        <div class="oe-amb-table-wrap">
            <table class="oe-amb-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Period', 'oe-ambassador' ); ?></th>
                        <th class="num"><?php esc_html_e( 'Sales', 'oe-ambassador' ); ?></th>
                        <th class="num"><?php esc_html_e( 'Tier', 'oe-ambassador' ); ?></th>
                        <th class="num"><?php esc_html_e( 'Amount', 'oe-ambassador' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'oe-ambassador' ); ?></th>
                        <th><?php esc_html_e( 'Paid', 'oe-ambassador' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $payouts['items'] as $pay ) : ?>
                    <tr>
                        <td><?php echo esc_html( gmdate( 'd M', strtotime( $pay->period_start ) ) . ' – ' . gmdate( 'd M Y', strtotime( $pay->period_end ) ) ); ?></td>
                        <td class="num"><?php echo (int) $pay->total_sales; ?></td>
                        <td class="num"><?php echo number_format( (float) $pay->tier_pct, 1 ); ?>%</td>
                        <td class="num commission"><?php echo number_format( (float) $pay->payout_amount, 2 ); ?> <?php echo esc_html( $pay->currency ); ?></td>
                        <td><span class="oe-amb-com-status status-<?php echo esc_attr( $pay->status ); ?>"><?php echo esc_html( ucfirst( $pay->status ) ); ?></span></td>
                        <td><?php echo $pay->paid_at ? esc_html( gmdate( 'd M Y', strtotime( $pay->paid_at ) ) ) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

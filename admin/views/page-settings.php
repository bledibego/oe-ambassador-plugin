<?php
/**
 * Settings page view.
 *
 * @package OE_Ambassador
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$s     = get_option( 'oe_amb_settings', [] );
$tiers = OE_Ambassador::get_tiers();
$pages = get_pages();

$statuses = [
    'completed'  => __( 'Completed', 'oe-ambassador' ),
    'processing' => __( 'Processing', 'oe-ambassador' ),
];
?>
<div class="wrap oe-amb-wrap">
<h1><?php esc_html_e( 'Ambassador Settings', 'oe-ambassador' ); ?></h1>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'oe_amb_save_settings' ); ?>
    <input type="hidden" name="action" value="oe_amb_save_settings">

    <div class="oe-amb-row" style="align-items:flex-start">

        <!-- General settings -->
        <div class="oe-amb-card" style="flex:1">
            <div class="oe-amb-card-header"><h2><?php esc_html_e( 'General', 'oe-ambassador' ); ?></h2></div>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Default Customer Discount', 'oe-ambassador' ); ?></th>
                    <td>
                        <input type="number" name="customer_coupon_pct" value="<?php echo esc_attr( $s['customer_coupon_pct'] ?? 10 ); ?>" min="1" max="100" style="width:70px"> %
                        <p class="description"><?php esc_html_e( 'Default % off for customers using an ambassador code.', 'oe-ambassador' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Default Self-Purchase Discount', 'oe-ambassador' ); ?></th>
                    <td>
                        <input type="number" name="self_purchase_pct" value="<?php echo esc_attr( $s['self_purchase_pct'] ?? 20 ); ?>" min="1" max="100" style="width:70px"> %
                        <p class="description"><?php esc_html_e( "Default % off for the ambassador's own purchases.", 'oe-ambassador' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Commission Trigger', 'oe-ambassador' ); ?></th>
                    <td>
                        <select name="commission_trigger">
                            <?php foreach ( $statuses as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( ( $s['commission_trigger'] ?? 'completed' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Order status that triggers commission recording.', 'oe-ambassador' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Currency', 'oe-ambassador' ); ?></th>
                    <td>
                        <input type="text" name="currency" value="<?php echo esc_attr( $s['currency'] ?? get_option('woocommerce_currency','SEK') ); ?>" style="width:80px">
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Auto-Approve Commissions', 'oe-ambassador' ); ?></th>
                    <td>
                        <input type="number" name="auto_approve_days" value="<?php echo esc_attr( $s['auto_approve_days'] ?? 0 ); ?>" min="0" max="90" style="width:70px"> <?php esc_html_e( 'days', 'oe-ambassador' ); ?>
                        <p class="description"><?php esc_html_e( 'Auto-approve pending commissions after N days. 0 = manual only.', 'oe-ambassador' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Pages & Email -->
        <div style="flex:1">
            <div class="oe-amb-card">
                <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Pages', 'oe-ambassador' ); ?></h2></div>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Application Page', 'oe-ambassador' ); ?></th>
                        <td>
                            <select name="apply_page_id">
                                <option value="0"><?php esc_html_e( '— Select page —', 'oe-ambassador' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo (int) $page->ID; ?>" <?php selected( (int)( $s['apply_page_id'] ?? 0 ), $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Page with [oe_amb_apply] shortcode.', 'oe-ambassador' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Ambassador Portal Page', 'oe-ambassador' ); ?></th>
                        <td>
                            <select name="portal_page_id">
                                <option value="0"><?php esc_html_e( '— Select page —', 'oe-ambassador' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo (int) $page->ID; ?>" <?php selected( (int)( $s['portal_page_id'] ?? 0 ), $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Page with [oe_amb_portal] shortcode.', 'oe-ambassador' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Terms &amp; Conditions URL', 'oe-ambassador' ); ?></th>
                        <td>
                            <input type="url" name="terms_page_url" value="<?php echo esc_url( $s['terms_page_url'] ?? '' ); ?>" style="width:360px" placeholder="https://yoursite.com/terms">
                            <p class="description"><?php esc_html_e( 'URL linked in the ambassador application consent checkbox.', 'oe-ambassador' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="oe-amb-card" style="margin-top:16px">
                <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Email', 'oe-ambassador' ); ?></h2></div>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'From Name', 'oe-ambassador' ); ?></th>
                        <td><input type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ?? get_bloginfo('name') ); ?>" style="width:240px"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'From Email', 'oe-ambassador' ); ?></th>
                        <td><input type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ?? get_option('admin_email') ); ?>" style="width:240px"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Admin Notification Email', 'oe-ambassador' ); ?></th>
                        <td>
                            <input type="email" name="notify_admin_email" value="<?php echo esc_attr( $s['notify_admin_email'] ?? get_option('admin_email') ); ?>" style="width:240px">
                            <p class="description"><?php esc_html_e( 'New application notifications are sent here.', 'oe-ambassador' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Monthly Report Day', 'oe-ambassador' ); ?></th>
                        <td>
                            <?php esc_html_e( 'Day', 'oe-ambassador' ); ?> <input type="number" name="monthly_report_day" value="<?php echo esc_attr( $s['monthly_report_day'] ?? 1 ); ?>" min="1" max="28" style="width:60px"> <?php esc_html_e( 'of each month', 'oe-ambassador' ); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Commission Tiers -->
    <div class="oe-amb-card" style="margin-top:16px">
        <div class="oe-amb-card-header">
            <h2><?php esc_html_e( 'Commission Tiers', 'oe-ambassador' ); ?></h2>
            <button type="button" class="button" id="oe-add-tier"><?php esc_html_e( '+ Add Tier', 'oe-ambassador' ); ?></button>
        </div>
        <p class="description" style="margin:0 0 16px"><?php esc_html_e( 'Tiers are based on total sales made within a calendar month. Set Max = -1 for unlimited (last tier).', 'oe-ambassador' ); ?></p>
        <table class="widefat" id="oe-tiers-table">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th><?php esc_html_e( 'Min Sales', 'oe-ambassador' ); ?></th>
                    <th><?php esc_html_e( 'Max Sales (-1 = unlimited)', 'oe-ambassador' ); ?></th>
                    <th><?php esc_html_e( 'Commission %', 'oe-ambassador' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="oe-tiers-body">
            <?php foreach ( $tiers as $i => $tier ) : ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><input type="number" name="tier_min[]" value="<?php echo (int) $tier['min']; ?>" min="0" style="width:80px"></td>
                    <td><input type="number" name="tier_max[]" value="<?php echo (int) $tier['max']; ?>" min="-1" style="width:80px"></td>
                    <td><input type="number" name="tier_pct[]" value="<?php echo (float) $tier['pct']; ?>" min="0.1" max="100" step="0.1" style="width:80px"> %</td>
                    <td><button type="button" class="button oe-remove-tier" style="color:#c62828"><?php esc_html_e( 'Remove', 'oe-ambassador' ); ?></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p style="margin-top:20px">
        <?php submit_button( __( 'Save Settings', 'oe-ambassador' ), 'primary large', 'submit', false ); ?>
    </p>
</form>

<!-- Preview shortcodes -->
<div class="oe-amb-card" style="margin-top:16px">
    <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Shortcodes', 'oe-ambassador' ); ?></h2></div>
    <table class="widefat">
        <tbody>
            <tr><td><code>[oe_amb_apply]</code></td><td><?php esc_html_e( 'Ambassador application form. Place on the Apply page.', 'oe-ambassador' ); ?></td></tr>
            <tr><td><code>[oe_amb_portal]</code></td><td><?php esc_html_e( 'Ambassador self-service portal. Place on the Portal page (requires login).', 'oe-ambassador' ); ?></td></tr>
        </tbody>
    </table>
</div>
</div>

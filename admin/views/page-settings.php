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
    'completed'  => __( 'Completed', 'oe-brand-ambassador-management' ),
    'processing' => __( 'Processing', 'oe-brand-ambassador-management' ),
];
?>
<div class="wrap oe-amb-wrap">
<h1><?php esc_html_e( 'Ambassador Settings', 'oe-brand-ambassador-management' ); ?></h1>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'oe_amb_save_settings' ); ?>
    <input type="hidden" name="action" value="oe_amb_save_settings">

    <div class="oe-amb-row" style="align-items:flex-start">

        <!-- General settings -->
        <div class="oe-amb-card" style="flex:1">
            <div class="oe-amb-card-header"><h2><?php esc_html_e( 'General', 'oe-brand-ambassador-management' ); ?></h2></div>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Default Customer Discount', 'oe-brand-ambassador-management' ); ?></th>
                    <td>
                        <input type="number" name="customer_coupon_pct" value="<?php echo esc_attr( $s['customer_coupon_pct'] ?? 10 ); ?>" min="1" max="100" style="width:70px"> %
                        <p class="description"><?php esc_html_e( 'Default % off for customers using an ambassador code.', 'oe-brand-ambassador-management' ); ?></p>
                    </td>
                </tr>
                <?php if ( oe_amb_is_pro() ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Default Self-Purchase Discount', 'oe-brand-ambassador-management' ); ?></th>
                    <td>
                        <input type="number" name="self_purchase_pct" value="<?php echo esc_attr( $s['self_purchase_pct'] ?? 20 ); ?>" min="1" max="100" style="width:70px"> %
                        <p class="description"><?php esc_html_e( "Default % off for the ambassador's own purchases.", 'oe-brand-ambassador-management' ); ?></p>
                    </td>
                </tr>
                <?php else : ?>
                <tr>
                    <th><?php esc_html_e( 'Default Self-Purchase Discount', 'oe-brand-ambassador-management' ); ?></th>
                    <td><span class="oe-amb-pro-badge">🔒 <?php esc_html_e( 'Pro', 'oe-brand-ambassador-management' ); ?></span> <a href="<?php echo esc_url( oe_amb_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to unlock', 'oe-brand-ambassador-management' ); ?></a></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e( 'Commission Trigger', 'oe-brand-ambassador-management' ); ?></th>
                    <td>
                        <select name="commission_trigger">
                            <?php foreach ( $statuses as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( ( $s['commission_trigger'] ?? 'completed' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Order status that triggers commission recording.', 'oe-brand-ambassador-management' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Currency', 'oe-brand-ambassador-management' ); ?></th>
                    <td>
                        <input type="text" name="currency" value="<?php echo esc_attr( $s['currency'] ?? get_option('woocommerce_currency','SEK') ); ?>" style="width:80px">
                    </td>
                </tr>
                <?php if ( oe_amb_is_pro() ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Auto-Approve Commissions', 'oe-brand-ambassador-management' ); ?></th>
                    <td>
                        <input type="number" name="auto_approve_days" value="<?php echo esc_attr( $s['auto_approve_days'] ?? 0 ); ?>" min="0" max="90" style="width:70px"> <?php esc_html_e( 'days', 'oe-brand-ambassador-management' ); ?>
                        <p class="description"><?php esc_html_e( 'Auto-approve pending commissions after N days. 0 = manual only.', 'oe-brand-ambassador-management' ); ?></p>
                    </td>
                </tr>
                <?php else : ?>
                <tr>
                    <th><?php esc_html_e( 'Auto-Approve Commissions', 'oe-brand-ambassador-management' ); ?></th>
                    <td><span class="oe-amb-pro-badge">🔒 <?php esc_html_e( 'Pro', 'oe-brand-ambassador-management' ); ?></span> <a href="<?php echo esc_url( oe_amb_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to unlock', 'oe-brand-ambassador-management' ); ?></a></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Pages & Email -->
        <div style="flex:1">
            <div class="oe-amb-card">
                <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Pages', 'oe-brand-ambassador-management' ); ?></h2></div>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Application Page', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <select name="apply_page_id">
                                <option value="0"><?php esc_html_e( '— Select page —', 'oe-brand-ambassador-management' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo (int) $page->ID; ?>" <?php selected( (int)( $s['apply_page_id'] ?? 0 ), $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Page with [oe_amb_apply] shortcode.', 'oe-brand-ambassador-management' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Ambassador Portal Page', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <select name="portal_page_id">
                                <option value="0"><?php esc_html_e( '— Select page —', 'oe-brand-ambassador-management' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo (int) $page->ID; ?>" <?php selected( (int)( $s['portal_page_id'] ?? 0 ), $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Page with [oe_amb_portal] shortcode.', 'oe-brand-ambassador-management' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Terms &amp; Conditions URL', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <input type="url" name="terms_page_url" value="<?php echo esc_url( $s['terms_page_url'] ?? '' ); ?>" style="width:360px" placeholder="https://yoursite.com/terms">
                            <p class="description"><?php esc_html_e( 'URL linked in the ambassador application consent checkbox.', 'oe-brand-ambassador-management' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="oe-amb-card" style="margin-top:16px">
                <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Email', 'oe-brand-ambassador-management' ); ?></h2></div>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'From Name', 'oe-brand-ambassador-management' ); ?></th>
                        <td><input type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ?? get_bloginfo('name') ); ?>" style="width:240px"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'From Email', 'oe-brand-ambassador-management' ); ?></th>
                        <td><input type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ?? get_option('admin_email') ); ?>" style="width:240px"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Admin Notification Email', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <input type="email" name="notify_admin_email" value="<?php echo esc_attr( $s['notify_admin_email'] ?? get_option('admin_email') ); ?>" style="width:240px">
                            <p class="description"><?php esc_html_e( 'New application notifications are sent here.', 'oe-brand-ambassador-management' ); ?></p>
                        </td>
                    </tr>
                    <?php if ( oe_amb_is_pro() ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Monthly Report Day', 'oe-brand-ambassador-management' ); ?></th>
                        <td>
                            <?php esc_html_e( 'Day', 'oe-brand-ambassador-management' ); ?> <input type="number" name="monthly_report_day" value="<?php echo esc_attr( $s['monthly_report_day'] ?? 1 ); ?>" min="1" max="28" style="width:60px"> <?php esc_html_e( 'of each month', 'oe-brand-ambassador-management' ); ?>
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr>
                        <th><?php esc_html_e( 'Monthly Report Day', 'oe-brand-ambassador-management' ); ?></th>
                        <td><span class="oe-amb-pro-badge">🔒 <?php esc_html_e( 'Pro', 'oe-brand-ambassador-management' ); ?></span> <a href="<?php echo esc_url( oe_amb_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to unlock', 'oe-brand-ambassador-management' ); ?></a></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Commission Tiers -->
    <div class="oe-amb-card" style="margin-top:16px">
        <div class="oe-amb-card-header">
            <h2><?php esc_html_e( 'Commission Tiers', 'oe-brand-ambassador-management' ); ?></h2>
            <?php if ( oe_amb_is_pro() ) : ?>
            <button type="button" class="button" id="oe-add-tier"><?php esc_html_e( '+ Add Tier', 'oe-brand-ambassador-management' ); ?></button>
            <?php endif; ?>
        </div>
        <?php if ( oe_amb_is_pro() ) : ?>
        <p class="description" style="margin:0 0 16px"><?php esc_html_e( 'Tiers are based on total sales made within a calendar month. Set Max = -1 for unlimited (last tier).', 'oe-brand-ambassador-management' ); ?></p>
        <table class="widefat" id="oe-tiers-table">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th><?php esc_html_e( 'Min Sales', 'oe-brand-ambassador-management' ); ?></th>
                    <th><?php esc_html_e( 'Max Sales (-1 = unlimited)', 'oe-brand-ambassador-management' ); ?></th>
                    <th><?php esc_html_e( 'Commission %', 'oe-brand-ambassador-management' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="oe-tiers-body">
            <?php foreach ( $tiers as $i => $tier ) : ?>
                <tr>
                    <td><?php echo absint( $i + 1 ); ?></td>
                    <td><input type="number" name="tier_min[]" value="<?php echo (int) $tier['min']; ?>" min="0" style="width:80px"></td>
                    <td><input type="number" name="tier_max[]" value="<?php echo (int) $tier['max']; ?>" min="-1" style="width:80px"></td>
                    <td><input type="number" name="tier_pct[]" value="<?php echo (float) $tier['pct']; ?>" min="0.1" max="100" step="0.1" style="width:80px"> %</td>
                    <td><button type="button" class="button oe-remove-tier" style="color:#c62828"><?php esc_html_e( 'Remove', 'oe-brand-ambassador-management' ); ?></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <div style="padding:16px;background:#fff8e1;border-left:4px solid #c9a96e;border-radius:4px">
            <p style="margin:0"><span class="oe-amb-pro-badge">🔒 <?php esc_html_e( 'Pro Feature', 'oe-brand-ambassador-management' ); ?></span>
            <?php esc_html_e( 'Commission tiers let you reward ambassadors with higher rates as their monthly sales grow.', 'oe-brand-ambassador-management' ); ?>
            <a href="<?php echo esc_url( oe_amb_upgrade_url() ); ?>" target="_blank" style="margin-left:8px;font-weight:600"><?php esc_html_e( 'Upgrade to Pro →', 'oe-brand-ambassador-management' ); ?></a></p>
        </div>
        <?php endif; ?>
    </div>

    <p style="margin-top:20px">
        <?php submit_button( __( 'Save Settings', 'oe-brand-ambassador-management' ), 'primary large', 'submit', false ); ?>
    </p>
</form>

<!-- Preview shortcodes -->
<div class="oe-amb-card" style="margin-top:16px">
    <div class="oe-amb-card-header"><h2><?php esc_html_e( 'Shortcodes', 'oe-brand-ambassador-management' ); ?></h2></div>
    <table class="widefat">
        <tbody>
            <tr><td><code>[oe_amb_apply]</code></td><td><?php esc_html_e( 'Ambassador application form. Place on the Apply page.', 'oe-brand-ambassador-management' ); ?></td></tr>
            <tr><td><code>[oe_amb_portal]</code></td><td><?php esc_html_e( 'Ambassador self-service portal. Place on the Portal page (requires login).', 'oe-brand-ambassador-management' ); ?></td></tr>
        </tbody>
    </table>
</div>
</div>

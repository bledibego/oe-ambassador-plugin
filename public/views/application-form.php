<?php
/**
 * Ambassador application form view.
 *
 * @package OE_Ambassador
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$customer_pct = (int) OE_Ambassador::setting( 'customer_coupon_pct', 10 );
$tiers        = OE_Ambassador::get_tiers();
$site_name    = get_bloginfo( 'name' );
$terms_url    = OE_Ambassador::setting( 'terms_page_url', '' );
// Fallback: find a page with 'terms' or 'privacy' in its slug
if ( ! $terms_url ) {
    $terms_page = get_page_by_path( 'terms-conditions' ) ?: get_page_by_path( 'terms' );
    $terms_url  = $terms_page ? get_permalink( $terms_page ) : home_url( '/' );
}

// Pre-fill from logged-in user
$prefill_first = '';
$prefill_last  = '';
$prefill_email = '';
if ( is_user_logged_in() ) {
    $u             = wp_get_current_user();
    $prefill_first = $u->first_name;
    $prefill_last  = $u->last_name;
    $prefill_email = $u->user_email;
}
?>
<div class="oe-amb-apply-wrap">

    <!-- Hero -->
    <div class="oe-amb-apply-hero">
        <div class="oe-amb-apply-hero-badge">AMBASSADOR PROGRAM</div>
        <h1 class="oe-amb-apply-title"><?php
        /* translators: %s is the site name */
        printf( esc_html__( 'Join the %s Family', 'oe-ambassador' ), esc_html( $site_name ) ); ?></h1>
        <p class="oe-amb-apply-sub"><?php esc_html_e( 'Share what you love. Earn commissions. Get exclusive perks.', 'oe-ambassador' ); ?></p>
    </div>

    <!-- Benefits -->
    <div class="oe-amb-benefits">
        <div class="oe-amb-benefit">
            <div class="oe-amb-benefit-icon">🎁</div>
            <strong><?php
            /* translators: %d is the customer discount percentage */
            printf( esc_html__( '%d%% off for your followers', 'oe-ambassador' ), (int) $customer_pct ); ?></strong>
            <p><?php esc_html_e( 'Your unique code gives your audience an exclusive discount.', 'oe-ambassador' ); ?></p>
        </div>
        <div class="oe-amb-benefit">
            <div class="oe-amb-benefit-icon">💰</div>
            <strong><?php esc_html_e( 'Earn up to 20% commission', 'oe-ambassador' ); ?></strong>
            <p><?php esc_html_e( 'Tiered commissions that grow with your sales volume.', 'oe-ambassador' ); ?></p>
        </div>
        <div class="oe-amb-benefit">
            <div class="oe-amb-benefit-icon">🛍️</div>
            <strong><?php esc_html_e( 'Personal discount on all orders', 'oe-ambassador' ); ?></strong>
            <p><?php esc_html_e( "You get your own code for all your personal purchases.", 'oe-ambassador' ); ?></p>
        </div>
        <div class="oe-amb-benefit">
            <div class="oe-amb-benefit-icon">📊</div>
            <strong><?php esc_html_e( 'Real-time dashboard', 'oe-ambassador' ); ?></strong>
            <p><?php esc_html_e( 'Track your sales, commissions, and monthly reports.', 'oe-ambassador' ); ?></p>
        </div>
    </div>

    <!-- Tier overview -->
    <div class="oe-amb-tier-overview">
        <h3><?php esc_html_e( 'Commission Tiers', 'oe-ambassador' ); ?></h3>
        <div class="oe-amb-tiers">
        <?php foreach ( $tiers as $i => $tier ) :
            $max_label = ( (int) $tier['max'] === -1 ) ? '+' : '–' . $tier['max'];
        ?>
            <div class="oe-amb-tier-card">
                <div class="oe-amb-tier-num">Tier <?php echo absint( $i + 1 ); ?></div>
                <div class="oe-amb-tier-pct"><?php echo (int) $tier['pct']; ?>%</div>
                <div class="oe-amb-tier-range"><?php echo esc_html( $tier['min'] . $max_label . ' sales/mo' ); ?></div>
            </div>
        <?php endforeach; ?>
        </div>
        <p class="oe-amb-tier-note"><?php esc_html_e( 'Commission calculated on net order value (excl. tax & shipping).', 'oe-ambassador' ); ?></p>
    </div>

    <!-- Application form -->
    <div class="oe-amb-apply-form-wrap">
        <h2><?php esc_html_e( 'Apply Now', 'oe-ambassador' ); ?></h2>

        <div id="oe-amb-apply-msg" class="oe-amb-apply-msg" style="display:none"></div>

        <form id="oe-amb-apply-form" class="oe-amb-form">
            <div class="oe-amb-form-row">
                <div class="oe-amb-form-group">
                    <label><?php esc_html_e( 'First Name *', 'oe-ambassador' ); ?></label>
                    <input type="text" name="first_name" value="<?php echo esc_attr( $prefill_first ); ?>" required placeholder="<?php esc_attr_e( 'Jane', 'oe-ambassador' ); ?>">
                </div>
                <div class="oe-amb-form-group">
                    <label><?php esc_html_e( 'Last Name *', 'oe-ambassador' ); ?></label>
                    <input type="text" name="last_name" value="<?php echo esc_attr( $prefill_last ); ?>" required placeholder="<?php esc_attr_e( 'Doe', 'oe-ambassador' ); ?>">
                </div>
            </div>

            <div class="oe-amb-form-row">
                <div class="oe-amb-form-group">
                    <label><?php esc_html_e( 'Email Address *', 'oe-ambassador' ); ?></label>
                    <input type="email" name="email" value="<?php echo esc_attr( $prefill_email ); ?>" required placeholder="jane@example.com">
                </div>
                <div class="oe-amb-form-group">
                    <label><?php esc_html_e( 'Phone', 'oe-ambassador' ); ?></label>
                    <input type="tel" name="phone" placeholder="+46 70 000 00 00">
                </div>
            </div>

            <div class="oe-amb-form-row">
                <div class="oe-amb-form-group">
                    <label><?php esc_html_e( 'Primary Platform *', 'oe-ambassador' ); ?></label>
                    <select name="social_platform" required>
                        <option value=""><?php esc_html_e( '— Select platform —', 'oe-ambassador' ); ?></option>
                        <option value="instagram">Instagram</option>
                        <option value="tiktok">TikTok</option>
                        <option value="youtube">YouTube</option>
                        <option value="facebook">Facebook</option>
                        <option value="twitter">Twitter / X</option>
                        <option value="other"><?php esc_html_e( 'Other', 'oe-ambassador' ); ?></option>
                    </select>
                </div>
                <div class="oe-amb-form-group">
                    <label><?php esc_html_e( 'Handle / Username', 'oe-ambassador' ); ?></label>
                    <input type="text" name="social_handle" placeholder="@yourusername">
                </div>
            </div>

            <div class="oe-amb-form-group">
                <label><?php esc_html_e( 'Website / Blog (optional)', 'oe-ambassador' ); ?></label>
                <input type="url" name="website" placeholder="https://yoursite.com">
            </div>

            <div class="oe-amb-form-group">
                <label><?php esc_html_e( 'Why do you want to be an ambassador? *', 'oe-ambassador' ); ?></label>
                <textarea name="motivation" rows="5" required placeholder="<?php esc_attr_e( 'Tell us about your audience, content style, and why our products are a great fit for you...', 'oe-ambassador' ); ?>"></textarea>
                <small><?php esc_html_e( 'Minimum 30 characters.', 'oe-ambassador' ); ?></small>
            </div>

            <div class="oe-amb-form-group oe-amb-consent">
                <label>
                    <input type="checkbox" name="consent" required>
                    <?php
                    printf(
                        /* translators: %s is the terms and conditions link */
                        esc_html__( 'I agree to the %s and the ambassador program terms.', 'oe-ambassador' ),
                        '<a href="' . esc_url( $terms_url ) . '" target="_blank">' . esc_html__( 'Terms & Conditions', 'oe-ambassador' ) . '</a>'
                    ); ?>
                </label>
            </div>

            <button type="submit" class="oe-amb-btn oe-amb-submit-btn" id="oe-amb-submit">
                <?php esc_html_e( 'Submit Application', 'oe-ambassador' ); ?>
            </button>
        </form>
    </div>
</div>

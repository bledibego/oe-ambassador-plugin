<?php
/**
 * Ambassadors list view.
 *
 * @package OE_Ambassador
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$status_filter = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search        = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$per_page      = 20;
$current_page  = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$offset        = ( $current_page - 1 ) * $per_page;

$result = OE_Amb_DB::get_ambassadors( [
    'status'   => $status_filter,
    'search'   => $search,
    'per_page' => $per_page,
    'offset'   => $offset,
] );

$total_pages = (int) ceil( $result['total'] / $per_page );

$statuses = [
    ''          => __( 'All', 'oe-ambassador' ),
    'pending'   => __( 'Pending', 'oe-ambassador' ),
    'approved'  => __( 'Approved', 'oe-ambassador' ),
    'rejected'  => __( 'Rejected', 'oe-ambassador' ),
    'suspended' => __( 'Suspended', 'oe-ambassador' ),
];

$badge_map = [
    'pending'   => 'oe-amb-badge-warning',
    'approved'  => 'oe-amb-badge-success',
    'rejected'  => 'oe-amb-badge-danger',
    'suspended' => 'oe-amb-badge-muted',
];
?>
<div class="wrap oe-amb-wrap">
<h1 class="wp-heading-inline"><?php esc_html_e( 'Ambassadors', 'oe-ambassador' ); ?></h1>
<hr class="wp-header-end">

<!-- Filter tabs -->
<ul class="subsubsub" style="margin-bottom:12px">
<?php foreach ( $statuses as $key => $label ) :
    $count     = OE_Amb_DB::get_ambassadors( [ 'status' => $key, 'per_page' => 9999 ] )['total'];
    $url       = add_query_arg( [ 'page' => 'oe-ambassador-ambassadors', 'status' => $key ], admin_url( 'admin.php' ) );
    $is_active = ( $key === $status_filter );
?>
    <li><a href="<?php echo esc_url( $url ); ?>" <?php if ( $is_active ) echo 'class="current"'; ?>>
        <?php echo esc_html( $label ); ?> <span class="count">(<?php echo (int) $count; ?>)</span>
    </a> |</li>
<?php endforeach; ?>
</ul>

<!-- Search -->
<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
    <input type="hidden" name="page" value="oe-ambassador-ambassadors">
    <?php if ( $status_filter ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>"><?php endif; ?>
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, code...', 'oe-ambassador' ); ?>" style="width:280px">
        <?php submit_button( __( 'Search', 'oe-ambassador' ), 'secondary', '', false ); ?>
    </p>
</form>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th style="width:200px"><?php esc_html_e( 'Name', 'oe-ambassador' ); ?></th>
            <th><?php esc_html_e( 'Email', 'oe-ambassador' ); ?></th>
            <th><?php esc_html_e( 'Platform', 'oe-ambassador' ); ?></th>
            <th><?php esc_html_e( 'Code', 'oe-ambassador' ); ?></th>
            <th><?php esc_html_e( 'Sales', 'oe-ambassador' ); ?></th>
            <th><?php esc_html_e( 'Commission', 'oe-ambassador' ); ?></th>
            <th><?php esc_html_e( 'Status', 'oe-ambassador' ); ?></th>
            <th><?php esc_html_e( 'Applied', 'oe-ambassador' ); ?></th>
            <th style="width:100px"><?php esc_html_e( 'Actions', 'oe-ambassador' ); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if ( empty( $result['items'] ) ) : ?>
        <tr><td colspan="9" style="text-align:center;padding:32px;color:#888"><?php esc_html_e( 'No ambassadors found.', 'oe-ambassador' ); ?></td></tr>
    <?php else : foreach ( $result['items'] as $row ) :
        $amb_obj = OE_Amb_Ambassador::find( (int) $row->id );
        $stats   = $amb_obj ? $amb_obj->lifetime_stats() : [ 'total_orders' => 0, 'total_commission' => 0 ];
        $detail_url = admin_url( 'admin.php?page=oe-ambassador-ambassadors&action=view&id=' . $row->id );
    ?>
        <tr>
            <td><strong><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $row->first_name . ' ' . $row->last_name ); ?></a></strong></td>
            <td><?php echo esc_html( $row->email ); ?></td>
            <td><?php echo $row->social_platform ? esc_html( ucfirst( $row->social_platform ) . ( $row->social_handle ? ' · @' . $row->social_handle : '' ) ) : '—'; ?></td>
            <td style="font-family:monospace;font-weight:600"><?php echo $row->coupon_code ? esc_html( strtoupper( $row->coupon_code ) ) : '—'; ?></td>
            <td><?php echo (int) $stats['total_orders']; ?></td>
            <td><?php echo number_format( (float) $stats['total_commission'], 0 ); ?></td>
            <td><span class="oe-amb-badge <?php echo esc_attr( $badge_map[ $row->status ] ?? 'oe-amb-badge-muted' ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
            <td><?php echo esc_html( gmdate( 'd M Y', strtotime( $row->applied_at ) ) ); ?></td>
            <td>
                <a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small"><?php esc_html_e( 'View', 'oe-ambassador' ); ?></a>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<?php if ( $total_pages > 1 ) : ?>
<div class="tablenav bottom">
    <div class="tablenav-pages">
        <?php
        echo wp_kses_post( paginate_links( [
            'base'      => add_query_arg( 'paged', '%#%' ),
            'format'    => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total'     => $total_pages,
            'current'   => $current_page,
        ] ) );
        ?>
    </div>
</div>
<?php endif; ?>
</div>

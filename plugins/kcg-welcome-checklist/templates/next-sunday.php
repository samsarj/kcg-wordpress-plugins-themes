<?php
/**
 * Next Sunday service date template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the next Sunday service date
$next_sunday_timestamp = kcg_checklist_get_next_sunday();
$display_date = $next_sunday_timestamp ? date_i18n( 'l jS F', $next_sunday_timestamp ) : 'Unknown';
?>

    <div class="week-display">
        <strong>Next Sunday Service:</strong>
        <?php echo esc_html( $display_date ); ?>
    </div>

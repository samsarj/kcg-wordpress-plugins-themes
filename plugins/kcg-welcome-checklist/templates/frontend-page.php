<?php
/**
 * Frontend page template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$items    = kcg_checklist_get_items();
$week_data = kcg_checklist_get_week_data();

// Define section titles (order matters for display)
$section_titles = [
    'before_sunday' => 'Before Sunday',
    'before_gathering' => 'Before Our Gathering',
    'during_gathering' => 'During Our Gathering',
    'after_gathering' => 'After Our Gathering',
];

// Group items by section
$sections = [];
foreach ( $section_titles as $section_id => $section_title ) {
    $sections[ $section_id ] = [
        'title' => $section_title,
        'items' => [],
    ];
}

foreach ( $items as $item ) {
    $section = isset( $item['section'] ) ? $item['section'] : 'before_gathering';
    if ( isset( $sections[ $section ] ) ) {
        $sections[ $section ]['items'][] = $item;
    }
}
?>

<div class="kcg-checklist-frontend">
    
    <form id="kcg-checklist-form" class="checklist-container">
        <?php foreach ( $sections as $section_key => $section ) : ?>
            <?php if ( ! empty( $section['items'] ) ) : ?>
                <div class="checklist-section" data-section="<?php echo esc_attr( $section_key ); ?>">
                        <h2 class="section-title"><?php echo esc_html( $section['title'] ); ?></h2>
                        <div class="checklist-items">
                            <?php foreach ( $section['items'] as $item ) : ?>
                                <?php
                                $is_checked = isset( $week_data[ $item['id'] ] ) ? $week_data[ $item['id'] ] : false;
                                $is_volunteer_role = in_array( $item['id'], [ 'reader_assigned', 'prayer_assigned' ] );
                                ?>
                                <div class="checklist-item" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
                                    <label class="checklist-label">
                                        <input 
                                            type="checkbox" 
                                            class="checklist-checkbox" 
                                            data-item-id="<?php echo esc_attr( $item['id'] ); ?>"
                                            <?php checked( $is_checked ); ?>
                                        />
                                        <span class="checkbox-custom"></span>
                                        <span class="item-content">
                                            <span class="item-title"><?php echo esc_html( $item['title'] ); ?></span>
                                            <span class="item-description"><?php echo esc_html( $item['description'] ); ?></span>
                                        </span>
                                    </label>
                                    <?php if ( $is_volunteer_role ) : ?>
                                        <div class="volunteer-selector">
                                            <select class="volunteer-dropdown" data-role="<?php echo esc_attr( $item['id'] ); ?>">
                                                <option value="">-- Select Volunteer --</option>
                                            </select>
                                            <span class="item-description">NOTE: Selecting a volunteer here will not add them in Elvanto. Please ensure they are aware!</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </form>
    </div>
</div>

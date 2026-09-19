<?php
/**
 * Plugin Name:       Clickable Cover Card
 * Description:       A cover block that is fully clickable with hover overlay effects.
 * Version:           2.0.0
 * Author:            Sam Sarjudeen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function clickable_cover_card_register_style() {
	wp_register_style(
		'kcg-clickable-cover-card-style',
		plugins_url( 'style.css', __FILE__ ),
		array(),
		'1.0.0'
	);
}
add_action( 'init', 'clickable_cover_card_register_style', 5 );

function clickable_cover_card_enqueue_assets() {
	wp_enqueue_style( 'kcg-clickable-cover-card-style' );
}
add_action( 'enqueue_block_assets', 'clickable_cover_card_enqueue_assets' );

function clickable_cover_card_block_init() {
	register_block_type( 'create-block/clickable-cover-card', array(
		'title'           => __( 'Clickable Cover Card', 'clickable-cover-card' ),
		'description'     => __( 'A cover block that is fully clickable with hover overlay effects.', 'clickable-cover-card' ),
		'attributes'      => array(
			'heading'    => array(
				'type'    => 'string',
				'default' => 'Heading',
			),
			'text'       => array(
				'type'    => 'string',
				'default' => 'Paragraph text',
			),
			'imageUrl'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'linkUrl'    => array(
				'type'    => 'string',
				'default' => '',
			),
		),
		'supports'        => array(
			'autoRegister' => true,
			'color'        => array(
				'text'    => true,
				'heading' => true,
				'custom'  => true,
			),
		),
		'style'           => 'kcg-clickable-cover-card-style',
		'render_callback' => 'clickable_cover_card_render',
	) );
}
add_action( 'init', 'clickable_cover_card_block_init', 10 );


function clickable_cover_card_render( $attributes, $content, $block ) {
	$heading = $attributes['heading'] ?? 'Heading';
	$text    = $attributes['text'] ?? 'Paragraph text';

	// Sanitise dynamic values.
	$image_url = esc_url_raw( $attributes['imageUrl'] ?? '' );
	$link_url  = esc_url( $attributes['linkUrl'] ?? '' );

	// Build the inline style — only add background-image when a URL is present.
	$style = 'background-size: cover; background-position: center;';
	if ( $image_url ) {
		$style = 'background-image: url(' . $image_url . '); ' . $style;
	}

	$wrapper_attributes = get_block_wrapper_attributes( array(
		'class' => 'clickable-cover-card',
	) );

	return '<div ' . $wrapper_attributes . ' style="' . esc_attr( $style ) . '">'
		. '<div class="overlay"></div>'
		. '<div class="cover-content">'
		. '<h3>' . esc_html( $heading ) . '</h3>'
		. '<p>' . esc_html( $text ) . '</p>'
		. '</div>'
		. '<a class="cover-link" href="' . $link_url . '" target="_self" rel="noopener noreferrer" aria-label="' . esc_attr( $heading ) . '"></a>'
		. '</div>';
}
<?php
// This file is generated. Do not modify it manually.
return array(
	'clickable-cover-card' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'create-block/clickable-cover-card',
		'version' => '0.1.0',
		'title' => 'Clickable Cover Card',
		'category' => 'design',
		'icon' => 'format-image',
		'description' => 'A cover block that is fully clickable with hover overlay effects.',
		'attributes' => array(
			'heading' => array(
				'type' => 'string',
				'default' => 'Heading'
			),
			'text' => array(
				'type' => 'string',
				'default' => 'Paragraph text'
			),
			'overlayColor' => array(
				'type' => 'string',
				'default' => '#000000'
			),
			'overlayOpacity' => array(
				'type' => 'number',
				'default' => 0.4
			),
			'overlayHoverOpacity' => array(
				'type' => 'number',
				'default' => 0.7
			),
			'imageUrl' => array(
				'type' => 'string',
				'default' => ''
			),
			'linkUrl' => array(
				'type' => 'string',
				'default' => ''
			),
			'linkTarget' => array(
				'type' => 'string',
				'default' => '_self'
			)
		),
		'supports' => array(
			'color' => array(
				'text' => true,
				'heading' => true
			)
		),
		'textdomain' => 'clickable-cover-card',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	)
);

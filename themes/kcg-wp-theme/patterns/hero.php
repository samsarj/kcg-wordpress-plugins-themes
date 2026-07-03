<?php
/**
 * Title: Hero
 * Slug: kcg-wp-theme/hero
 * Categories: featured
 * Description: A full-height hero section with a background image and text overlay.
 * Keywords: hero, full-height, background image, text overlay, cover
 * Block Types: core/post-content, core/cover
 * Post Types: page
 * Template Types: page
 */
?>
<!-- wp:cover {"dimRatio":80,"overlayColor":"primary","isUserOverlayColor":true,"minHeight":100,"minHeightUnit":"vh","sizeSlug":"full","metadata":{"categories":["featured"],"patternName":"kcg-wp-theme/hero","name":"Hero"},"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}},"heading":{"color":{"text":"var:preset|color|light"}}},"spacing":{"padding":{"top":"20vh"}}},"textColor":"base","fontSize":"medium","layout":{"type":"constrained"}} -->
<div class="wp-block-cover has-base-color has-text-color has-link-color has-medium-font-size" style="padding-top:20vh;min-height:100vh"><span aria-hidden="true" class="wp-block-cover__background has-primary-background-color has-background-dim-80 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-title {"textAlign":"center","level":1} /-->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center">Page description/summary here.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
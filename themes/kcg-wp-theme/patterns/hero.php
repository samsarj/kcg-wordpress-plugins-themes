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
<!-- wp:cover {"dimRatio":80,"overlayColor":"base","isUserOverlayColor":true,"focalPoint":{"x":0.5,"y":0.5},"isDark":false,"sizeSlug":"full","metadata":{"categories":["hero"],"patternName":"kcg-wp-theme/hero","name":"Hero"},"className":"full-height","style":{"elements":{"link":{"color":{"text":"var:preset|color|primary"}},"heading":{"color":{"text":"var:preset|color|light"}}}},"textColor":"primary","fontSize":"medium","layout":{"type":"constrained","contentSize":"800px"}} -->
<div class="wp-block-cover is-light full-height has-primary-color has-text-color has-link-color has-medium-font-size"><span aria-hidden="true" class="wp-block-cover__background has-base-background-color has-background-dim-80 has-background-dim"></span>
    <div class="wp-block-cover__inner-container">
        <!-- wp:group {"layout":{"type":"default"}} -->
        <div class="wp-block-group">
            <!-- wp:post-title {"textAlign":"center","level":1} /-->
            <!-- wp:paragraph {"align":"center"} -->
            <p class="has-text-align-center">Page description/summary here.</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->
    </div>
</div>
<!-- /wp:cover -->
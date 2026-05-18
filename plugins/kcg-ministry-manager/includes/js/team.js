jQuery(document).ready(function($) {
    $('.read-more-link').click(function(e) {
        e.preventDefault();
        var $link = $(this);
        var $moreText = $link.prev('.more-text');
        if ($link.text() === ' Read More') {
            $moreText.show();
            $link.text(' See Less');
        } else {
            $moreText.hide();
            $link.text(' Read More');
        }
    });
});

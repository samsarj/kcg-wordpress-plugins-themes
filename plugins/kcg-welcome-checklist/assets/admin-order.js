(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle move up button
        $(document).on('click', '.kcg-move-up', function(e) {
            e.preventDefault();
            reorderItem($(this), 'up');
        });

        // Handle move down button
        $(document).on('click', '.kcg-move-down', function(e) {
            e.preventDefault();
            reorderItem($(this), 'down');
        });

        function reorderItem($button, direction) {
            var postId = $button.data('post-id');
            var section = $button.data('section');

            // Disable button during request
            $button.prop('disabled', true);

            $.ajax({
                url: kcgChecklistAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kcg_checklist_reorder',
                    post_id: postId,
                    direction: direction,
                    section: section,
                    nonce: kcgChecklistAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated order
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('AJAX error occurred');
                    $button.prop('disabled', false);
                }
            });
        }
    });

})(jQuery);

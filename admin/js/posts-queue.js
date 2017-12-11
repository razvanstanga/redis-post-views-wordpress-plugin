jQuery(function($){
    $(".rpv_sync").each(function(k,v) {
        $(v).click(function(e) {
            e.preventDefault();
            var post_id = $(this).data('post-id');
            $.post(ajaxurl, {'id': post_id, 'action': 'rpv_sync_action'}, function(html){
                $('.views_' + post_id).html(0);
            });
        });
    });
});
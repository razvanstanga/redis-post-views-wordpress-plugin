jQuery(function($){
    function _rpv_ajax() {
        $(".rpv_sync").each(function(k,v) {
            $(v).click(function(e) {
                e.preventDefault();
                var post_id = $(this).data('post-id');
                $.post(ajaxurl, {'id': post_id, 'action': 'rpv_sync_action'}, function(v){
                    $('.views_' + post_id).html(v);
                    $('.views_to_sync_' + post_id).html(0);
                });
            });
        });
        $(".rpv_sync_all").click(function(e){
            e.preventDefault();
            $.post(ajaxurl, {'action': 'rpv_sync_all_action'}, function(){
                $.get(window.location, function(html){
                    $('.posts-queue-tab').html($(html).find('.posts-queue-tab').html());
                    _rpv_ajax();
                });
            });
        });
    }
    _rpv_ajax();
});
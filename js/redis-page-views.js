jQuery(document).ready(function($) {
    var data = {
        'id': _rpv.id
    };
    jQuery.get(_rpv.url, data, function(response) {
        //TODO: window.redis_page_views callbacks
    });
});

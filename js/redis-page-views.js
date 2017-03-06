jQuery(document).ready(function($) {
    var data = {
        'action': 'redis_page_view',
        'id': _rpv.id
    };
    jQuery.get(_rpv.url, data, function(response) {
        //console.log (response);
    });
});
<?php

$config_file = '../../wp-config-rpv.php';
if (file_exists($config_file)) {
    include($config_file);
}

define('RPV_VERSION', 1.7);
define('RPV_PATH', dirname( __FILE__ ));

include(RPV_PATH . '/classes/redis_post_views.php');
include(RPV_PATH . '/classes/redis_post_view.php');

$redis_post_view = new Redis_Post_View();

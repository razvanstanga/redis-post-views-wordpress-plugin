<?php
/*
Plugin Name: Redis Post Views
Plugin URI: http://wordpress.org/extend/plugins/post-views-redis/
Description: Highly optimized post views using Redis
Version: 1.7
Author: Razvan Stanga
Author URI: http://git.razvi.ro/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: redis-post-views
Network: true

Copyright 2017: Razvan Stanga (email: redis-post-views@razvi.ro)
*/
$config_file = '../../wp-config-rpv.php';
if (file_exists($config_file)) {
    include($config_file);
}

define('RPV_VERSION', 1.7);
define('RPV_PATH', dirname( __FILE__ ));

include(RPV_PATH . '/classes/redis_post_views.php');
include(RPV_PATH . '/classes/redis_post_view.php');

$redis_post_view = new Redis_Post_View();

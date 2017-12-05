<?php
/*
Plugin Name: Redis Page Views
Plugin URI: http://wordpress.org/extend/plugins/redis-page-views/
Description: Highly optimized page views using Redis
Version: 1.1
Author: Razvan Stanga
Author URI: http://git.razvi.ro/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: redis-page-views
Network: true

Copyright 2017: Razvan Stanga (email: redis-page-views@razvi.ro)
*/

include('redis-page-views.php');

class Redis_Page_View extends Redis_Page_Views {

    public function __construct()
    {
        parent::__construct();

        $this->page_view();
    }

    public function page_view()
    {
        if (!isset($_GET['id'])) {
            return 'Invalid ID';
        }
        $post_id = intval($_GET['id']);

        $this->connect_redis();
        $views = $this->redis->get("post-" . $post_id);

        if ($views != null) {
            $this->redis->incr("post-" . $post_id);
        } else {
            $this->redis->set("post-" . $post_id, 1);
        }
        $this->redis->sAdd("posts", $post_id);

        echo $views++;
    }
}

$redisPageView = new Redis_Page_View();

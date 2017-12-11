<?php
/*
Plugin Name: Redis Post Views
Plugin URI: http://wordpress.org/extend/plugins/redis-post-views/
Description: Highly optimized post views using Redis
Version: 1.1
Author: Razvan Stanga
Author URI: http://git.razvi.ro/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: redis-post-views
Network: true

Copyright 2017: Razvan Stanga (email: redis-post-views@razvi.ro)
*/

define('ABSPATH', dirname(__FILE__) . '/');
include("../../../wp-config.php");
include("redis-post-views.php");

class Redis_Post_View extends Redis_Post_Views {

    public function __construct()
    {
        parent::__construct();
        $this->post_view();
    }

    /**
     * TODO: Exclude bots
     *
     * @return bool
     */
    public function exclude_bots()
    {
        if (defined("RPV_EXCLUDE_BOTS") && RPV_EXCLUDE_BOTS == true) {
            $bots = array(
                'Google Bot' => 'google'
                ,'MSN' => 'msnbot'
                ,'Alex' => 'ia_archiver'
                ,'Lycos' => 'lycos'
                ,'Ask Jeeves' => 'jeeves'
                ,'Altavista' => 'scooter'
                ,'AllTheWeb' => 'fast-webcrawler'
                ,'Inktomi' => 'slurp@inktomi'
                ,'Turnitin.com' => 'turnitinbot'
                ,'Technorati' => 'technorati'
                ,'Yahoo' => 'yahoo'
                ,'Findexa' => 'findexa'
                ,'NextLinks' => 'findlinks'
                ,'Gais' => 'gaisbo'
                ,'WiseNut' => 'zyborg'
                ,'WhoisSource' => 'surveybot'
                ,'Bloglines' => 'bloglines'
                ,'BlogSearch' => 'blogsearch'
                ,'PubSub' => 'pubsub'
                ,'Syndic8' => 'syndic8'
                ,'RadioUserland' => 'userland'
                ,'Gigabot' => 'gigabot'
                ,'Become.com' => 'become.com'
                ,'Baidu' => 'baiduspider'
                ,'so.com' => '360spider'
                ,'Sogou' => 'spider'
                ,'soso.com' => 'sosospider'
                ,'Yandex' => 'yandex'
            );
            $useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            foreach ($bots as $name => $lookfor) {
                if (!empty($useragent) && (false !== stripos($useragent, $lookfor))) {
                    return true;
                    break;
                }
            }
        }
        return false;
    }

    /**
     * Increment post view count
     *
     * @return void
     */
    public function post_view()
    {
        if (!isset($_POST['id'])) {
            echo 'Invalid ID';
            return;
        }
        if ($this->exclude_bots()) {
            echo 'Bot detected';
            return;
        }
        $post_id = intval($_GET['id']);

        try {
            if ($this->redis_connect()) {
                $views = intval($this->redis->get("post-" . $post_id));

                if ($views != null) {
                    $this->redis->incr("post-" . $post_id);
                } else {
                    $this->redis->set("post-" . $post_id, 1);
                }
                $this->redis->sAdd("posts", $post_id);
                if (defined("RPV_AJAX_RETURN_VIEWS") && constant("RPV_AJAX_RETURN_VIEWS") == true) {
                    $views++; echo $views;
                }
            }
        } catch (RedisException $ex) {}
    }
}

$redisPostView = new Redis_Post_View();

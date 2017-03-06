<?php
/*
Plugin Name: Redis Page Views
Plugin URI: http://wordpress.org/extend/plugins/redis-page-views/
Description: Highly optimized page views using Redis
Version: 1.0
Author: Razvan Stanga
Author URI: http://git.razvi.ro/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: redis-page-views
Network: true

Copyright 2016: Razvan Stanga (email: redis-page-views@razvi.ro)
*/

class Redis_Page_Views {
    private $data = [];

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        return null;
    }

    public function __construct()
    {
        $this->post_meta_key = 'redis_page_views';
        $this->plugin = 'redis-page-views';
        $this->prefix = 'website';
        $this->version = '1.0';

        add_action('init', array($this, 'init'));
    }

    public function init()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_js'));
        add_action('wp_ajax_redis_page_view', array($this, 'page_view'));
    }

    public function connect_redis()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->setOption(Redis::OPT_PREFIX, $this->prefix . ':');
    }

    public function enqueue_js()
    {
        if (is_page() || is_single()) {
            $post_id = get_the_ID();
            wp_enqueue_script($this->plugin, plugins_url('/js/redis-page-views.js', __FILE__), array('jquery'), $this->version);
            wp_add_inline_script($this->plugin, "var _rpv = {id: " . $post_id . ", 'url': '" . admin_url('admin-ajax.php') . "'};");
        }
    }

    public function page_view()
    {
        $post_id = intval($_GET['id']);

        $this->connect_redis();
        $this->redis->incr('post-' . $post_id);
        $this->redis->sAdd('posts', $post_id);

        //echo $this->redis->get('post-' . $post_id);

        wp_die();
    }
}

$redisPageViews = new Redis_Page_Views();

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    include('wp-cli.php');
}

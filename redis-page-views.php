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
        $this->post_meta_key = defined("RPV_POST_META_KEY") ? constant("RPV_POST_META_KEY") : 'redis_pageviews_count';
        $this->plugin = 'redis-page-views';
        $this->version = '1.1';
        $this->redisHost = defined("RPV_REDIS_HOST") ? constant("RPV_REDIS_HOST") : '127.0.0.1';
        $this->redisPort = defined("RPV_REDIS_PORT") ? constant("RPV_REDIS_PORT") : 6379;
        $this->redisPass = defined("RPV_REDIS_PASS") ? constant("RPV_REDIS_PASS") : null;
        $this->redisPrefix = defined("RPV_REDIS_PREFIX") ? constant("RPV_REDIS_PREFIX") : $this->plugin;

        if (function_exists('add_action')) {
            add_action('init', array($this, 'init'));
        }
    }

    public function init()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_js'));
    }

    public function connect_redis()
    {
        $this->redis = new Redis();
        $this->redis->connect($this->redisHost, $this->redisPort);
        if ($this->redisPass) {
            $this->redis->auth($this->redisPass);
        }
        $this->redis->setOption(Redis::OPT_PREFIX, $this->redisPrefix . ':');
    }

    public function enqueue_js()
    {
        if (is_page() || is_single()) {
            $post_id = get_the_ID();
            wp_enqueue_script($this->plugin, plugins_url('/js/redis-page-views.js', __FILE__), array('jquery'), $this->version);
            wp_add_inline_script($this->plugin, "var _rpv = {id: " . $post_id . ", url: '" . plugins_url('/pageview.php', __FILE__) . "'};");
        }
    }
}

$redisPageViews = new Redis_Page_Views();

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    include('wp-cli.php');
}

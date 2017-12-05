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
            if (is_admin()) {
                add_action('admin_menu', array($this, 'add_menu_item'));
                $this->currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'stats';
            }
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

    public function add_menu_item()
    {
        add_menu_page(__('Redis Page Views', $this->plugin), __('Redis Page Views', $this->plugin), 'manage_options', $this->plugin . '-plugin', array($this, 'settings_page'), plugins_url() . '/' . $this->plugin . '/icon.png', 100);
    }

    public function settings_page()
    {
    ?>
        <div class="wrap">
        <h1><?=__('Redis Page Views', $this->plugin)?></h1>

        <h2 class="nav-tab-wrapper">
            <a class="nav-tab <?php if($this->currentTab == 'stats'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=stats"><?=__('Statistics', $this->plugin)?></a>
            <a class="nav-tab <?php if($this->currentTab == 'posts-views'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=posts-views"><?=__('Posts views', $this->plugin)?></a>
            <a class="nav-tab <?php if($this->currentTab == 'conf'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=conf"><?=__('Configuration info', $this->plugin)?></a>
        </h2>

        <?php if($this->currentTab == 'stats'): ?>
            <h2><?= __('Statistics', $this->plugin) ?></h2>

            <div class="wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column"><strong>Key</strong></td>
                            <td class="manage-column"><strong>Value</strong></td>
                            <!--td class="manage-column"><strong>Description</strong></td-->
                        </tr>
                    </thead>
                    <tbody>
                    <?php $this->connect_redis(); ?>
                    <?php foreach($data = $this->redis->info() as $key => $value): ?>
                        <tr>
                            <td><?=$key?></td>
                            <td><?=$value?></td>
                            <!--td></td-->
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif($this->currentTab == 'conf'): ?>
            <h2><?= __('Configuration info', $this->plugin) ?></h2>

            <div class="wrap">
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <td>
                                <?php
                                    echo sprintf('<h4>' . __('You can override in wp-config.php redis default connection data' . '</h4>', $this->plugin));
                                ?>
                                <textarea cols="100" rows="5"><?php
                                    echo __("DEFINE('RPV_POST_META_KEY', 'redis_pageviews_count');", $this->plugin); echo "\r\n";
                                    echo __("DEFINE('RPV_REDIS_HOST', '127.0.0.1');", $this->plugin); echo "\r\n";
                                    echo __("DEFINE('RPV_REDIS_PORT', 6379);", $this->plugin); echo "\r\n";
                                    echo __("DEFINE('RPV_REDIS_PASS', '');", $this->plugin); echo "\r\n";
                                    echo __("DEFINE('RPV_REDIS_PREFIX', 'redis-page-views'); // use custom prefix on all keys", $this->plugin);
                                ?></textarea>
                                <br /><br />
                                <?php
                                    echo __("You can use get_post_meta(\$post_id, RPV_POST_META_KEY, true); to get the post views");
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php elseif($this->currentTab == 'posts'): ?>
            <h2><?= __('Posts views in memory', $this->plugin) ?></h2>

            <div class="wrap">
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <td>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>
    <?php
    }
}

$redisPageViews = new Redis_Page_Views();

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    include('wp-cli.php');
}

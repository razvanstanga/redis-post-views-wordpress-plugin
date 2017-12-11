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

class Redis_Post_Views {
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
        $this->plugin           = 'redis-post-views';
        $this->version          = 1.1;
        $this->post_meta_key    = defined('RPV_POST_META_KEY') ? constant('RPV_POST_META_KEY') : 'redis_post_views_count';
        $this->redis_host       = defined('RPV_REDIS_HOST') ? constant('RPV_REDIS_HOST') : '127.0.0.1';
        $this->redis_port       = defined('RPV_REDIS_PORT') ? constant('RPV_REDIS_PORT') : 6379;
        $this->redis_auth       = defined('RPV_REDIS_AUTH') ? constant('RPV_REDIS_AUTH') : null;
        $this->redis_prefix     = defined('RPV_REDIS_PREFIX') ? constant('RPV_REDIS_PREFIX') : $this->plugin;
        $this->redis_database   = defined('RPV_REDIS_DATABASE') ? constant('RPV_REDIS_DATABASE') : 0;

        $this->redis_connected  = false;
        $this->redis_exception  = false;

        if ( function_exists('add_action') ) { // only in Wordpress ENV
            add_action('init', array($this, 'init'));
            if ( is_admin() ) {
                add_action('admin_menu', array($this, 'add_menu_item'));
                $this->current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'stats';
            }
        }
    }

    public function init()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_js'));
    }

    public function redis_connect()
    {
        $this->redis = new Redis();
        try {
            $this->redis_connected = $this->redis->connect($this->redis_host, $this->redis_port);
            if ( $this->redis_auth ) {
                $this->redis->auth($this->redis_auth);
            }
            if ( $this->redis_database ) {
                $this->redis->select($this->redis_database);
            }
            if ( function_exists('add_action') ) {
                $this->redis->ping(); // only in Wordpress ENV
            }
        } catch(redis_exception $ex) {
            $this->redis_exception = $ex->getMessage();
        }
        if ( $this->redis_exception == false ) {
            $this->redis->setOption(Redis::OPT_PREFIX, $this->redis_prefix . ':');
        }
        return ($this->redis_exception ? false : true);
    }

    protected function redis_info()
    {
        try {
            $this->redisInfo = $this->redis->info();
        } catch(redis_exception $ex) {
            $this->redis_exception = $ex->getMessage();
        }
        return ($this->redis_exception ? false : true);
    }

    public function enqueue_js()
    {
        if ( (is_page() || is_single()) && $post_id = get_the_ID() ) {
            wp_enqueue_script($this->plugin, plugins_url('/js/redis-post-views.js', __FILE__), array('jquery'), $this->version);
            wp_add_inline_script($this->plugin, "var _rpv = {id: " . $post_id . ", url: '" . plugins_url('/post-view.php', __FILE__) . "'};");
        }
    }

    protected function posts_queue()
    {
        $posts = array();
        try {
            foreach ( $this->redis->sMembers('posts') as $post_id ) {
                $posts[$post_id] = $this->redis->get('post-' . $post_id);
            }
            $this->postsQueue = $posts;
        } catch(redis_exception $ex) {
            $this->redis_exception = $ex->getMessage();
        }
        return ($this->redis_exception ? false : true);
    }

    public function add_menu_item()
    {
        add_menu_page(__('Redis Post Views', $this->plugin), __('Redis Post Views', $this->plugin), 'manage_options', $this->plugin . '-plugin', array($this, 'settings_page'), plugins_url() . '/' . $this->plugin . '/icon.png', 100);
    }

    public function settings_page()
    {
    ?>
        <div class="wrap">
        <h1><?php echo __('Redis Post Views', $this->plugin)?></h1>

        <h2 class="nav-tab-wrapper">
            <a class="nav-tab <?php if($this->current_tab == 'stats'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?php echo $this->plugin?>-plugin&amp;tab=stats"><?php echo __('Statistics', $this->plugin)?></a>
            <a class="nav-tab <?php if($this->current_tab == 'posts-queue'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?php echo $this->plugin?>-plugin&amp;tab=posts-queue"><?php echo __('Posts queue', $this->plugin)?></a>
            <a class="nav-tab <?php if($this->current_tab == 'conf'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?php echo $this->plugin?>-plugin&amp;tab=conf"><?php echo __('Configuration info', $this->plugin)?></a>
        </h2>

        <?php if($this->current_tab == 'stats'): ?>
            <h2><?php echo __('Statistics', $this->plugin) ?></h2>

            <div class="wrap">
                <table class="wp-list-table widefat fixed striped">
                    <?php if ( $this->redis_connect() && $this->redis_info() ): ?>
                        <thead>
                            <tr>
                                <td class="manage-column"><strong>Post</strong></td>
                                <td class="manage-column"><strong>Views</strong></td>
                                <!--td class="manage-column"><strong>Description</strong></td-->
                                <!--td class="manage-column"><strong>Sync</strong></td-->
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach( $this->redisInfo as $key => $value ): ?>
                            <tr>
                                <td><?php echo $key; ?></td>
                                <td><?php echo $value; ?></td>
                                <!--td></td-->
                                <!--td>sync via AJAX</td-->
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td>
                                <?php echo $this->redis_exception; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif($this->current_tab == 'conf'): ?>
            <h2><?php echo __('Configuration info', $this->plugin) ?></h2>

            <div class="wrap">
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <td>
                                <?php
                                    echo sprintf('<h4>' . __('You can override in wp-config.php Redis default connection data and other options' . '</h4>', $this->plugin));
                                ?>
                                <textarea cols="100" rows="11">
/**
 * Redis Post Views plugin
 */
<?php
                                    echo __("define('RPV_REDIS_HOST', '127.0.0.1');", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_REDIS_PORT', 6379);", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_REDIS_AUTH', '');", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_REDIS_PREFIX', 'redis-post-views'); // use custom prefix on all keys", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_REDIS_DATABASE', 0); // dbindex, the database number to switch to", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_POST_META_KEY', 'redis_post_views_count');", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_EXCLUDE_BOTS', true); // exclude bots like Google ?", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_AJAX_RETURN_VIEWS', true); // does the AJAX request return post views count ?", $this->plugin);
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
        <?php elseif( $this->current_tab == 'posts-queue' ): ?>
            <h2><?php echo __('Posts queue in Redis', $this->plugin); ?></h2>

            <div class="wrap">
                <table class="wp-list-table widefat fixed striped">
                    <?php if ( $this->redis_connect() && $this->posts_queue() ): ?>
                        <thead>
                            <tr>
                                <td class="manage-column"><strong>Key</strong></td>
                                <td class="manage-column"><strong>Value</strong></td>
                                <!--td class="manage-column"><strong>Description</strong></td-->
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach( $this->postsQueue as $post_id => $viewCount ): ?>
                            <tr>
                                <td><?php echo get_the_title($post_id); ?></td>
                                <td><?php echo $viewCount; ?></td>
                                <!--td></td-->
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td>
                                <?php echo $this->redis_exception; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>
    <?php
    }
}

$redis_post_views = new Redis_Post_Views();

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    include('wp-cli.php');
}

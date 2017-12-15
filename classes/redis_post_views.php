<?php

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
        $this->plugin           = 'post-views-redis';
        $this->version          = RPV_VERSION;
        $this->settings_file    = defined('RPV_REDIS_HOST') ? true : false;
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
        }
    }

    /**
     * Plugin initialization
     *
     * @return void
     */
    public function init()
    {
        $this->plugin_url      = plugins_url('', dirname(__FILE__));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_js'));
        if ( is_admin() ) {
            add_action('admin_menu', array($this, 'add_menu_item'));
            add_action('wp_ajax_rpv_sync_action', array($this, 'sync_action'));
            add_action('wp_ajax_rpv_sync_all_action', array($this, 'sync_all_action'));
            $this->current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'stats';
            $this->redis_main_keys = array(
                'redis_version'          => 'Version',
                'config_file'            => 'Config File',
                'uptime_in_seconds'      => 'Uptime',
                'connected_clients'      => 'Connected Clients',
                'connected_slaves'       => 'Connected Slaves',
                'used_memory_human'      => 'Used Memory',
                'used_memory_peak_human' => 'Peak Used Memory',
                'expired_keys'           => 'Expired Keys',
                'evicted_keys'           => 'Evicted Keys',
                'keyspace_hits'          => 'Keyspace Hits',
                'keyspace_misses'        => 'Keyspace Misses'
            );

            if ( $this->current_tab == "posts-queue" ) {
                wp_enqueue_script($this->plugin, $this->plugin_url . '/admin/js/posts-queue.js', array('jquery'), $this->version);
            } else if ( $this->current_tab == "stats" ) {
                wp_enqueue_script($this->plugin, $this->plugin_url . '/admin/js/Chart.min.js', $this->version);
            }
        }
    }

    /**
     * Connect to Redis
     *
     * @return bool
     */
    public function redis_connect()
    {
        $this->redis = new Redis();
        try {
            $this->redis_connected = $this->redis->connect($this->redis_host, $this->redis_port);
            if ( $this->redis_auth ) {
                $this->redis->auth($this->redis_auth);
            }
            if ( $this->redis_connected && $this->redis_database ) {
                $this->redis->select($this->redis_database);
            }
            if ( function_exists('add_action') ) {
                $this->redis->ping(); // only in Wordpress ENV
            }
        } catch(RedisException $ex) {
            $this->redis_exception = $ex->getMessage();
        }
        if ( $this->redis_connected ) {
            $this->redis->setOption(Redis::OPT_PREFIX, $this->redis_prefix . ':');
        }
        return ($this->redis_exception ? false : true);
    }

    /**
     * Get Redis INFO
     *
     * @return bool
     */
    protected function redis_info()
    {
        $this->redis_connect();
        try {
            $this->redis_info = $this->redis->info();
        } catch(RedisException $ex) {
            $this->redis_exception = $ex->getMessage();
        }
        return ($this->redis_exception ? false : true);
    }

    /**
     *  Fetch posts queue
     *
     * @return bool
     */
    protected function posts_queue()
    {
        $this->redis_connect();
        $posts = array();
        try {
            $redis_posts = $this->redis->sort('posts', array('sort' => 'desc'));
            if ( is_array($redis_posts) ) {
                foreach ( $redis_posts as $post_id ) {
                    $posts[$post_id] = $this->redis->get('post-' . $post_id);
                }
            }
            $this->posts_queue = $posts;
        } catch(RedisException $ex) {
            $this->redis_exception = $ex->getMessage();
        }
        return ($this->redis_exception ? false : true);
    }

    /**
     * Get databases that exist in Redis instance
     *
     * @return array
     */
    public function redis_databases()
    {
        return array_map(function($db) {
            return (int) substr($db, 2);
        }, preg_grep("/^db[0-9]+$/", array_keys($this->redis_info)));
    }

    /**
     * Enqueue JS for Wordpress frontend
     *
     * @return void
     */
    public function enqueue_js()
    {
        if ( (is_page() || is_single()) && $post_id = get_the_ID() ) {
            wp_enqueue_script($this->plugin, $this->plugin_url . '/js/init.js', array('jquery'), $this->version);
            wp_add_inline_script($this->plugin, "var _rpv = {id: " . $post_id . ", url: '" . $this->plugin_url . '/post-view.php' . "'};");
        }
    }

    /**
     * Adds menu for WP-Admin
     *
     * @return void
     */
    public function add_menu_item()
    {
        add_menu_page(__('Redis Post Views', $this->plugin), __('Redis Post Views', $this->plugin), 'manage_options', $this->plugin . '-plugin', array($this, 'admin_page'), $this->plugin_url . '/icon.png', 100);
    }

    /**
     * Sync views to Wordpress database
     *
     * @param  int $post_id post ID
     * @return int total views
     */
    public function sync_views($post_id)
    {
        $old_views = get_post_meta($post_id, $this->post_meta_key, true);
        $new_views = $this->redis->get('post-' . $post_id);
        $this->redis->delete('post-' . $post_id);
        $this->redis->sRem('posts', $post_id);
        if ($old_views) {
            $total_views = intval($old_views) + $new_views;
            update_post_meta($post_id, $this->post_meta_key, $total_views, $old_views);
        } else {
            add_post_meta($post_id, $this->post_meta_key, $new_views, true);
        }
        return $total_views;
    }

    /**
     * AJAX action for wp_ajax_rpv_sync_all_action
     *
     * @return void
     */
    public function sync_all_action()
    {
        if ( $this->redis_connect() ) {
            $posts = $this->redis->sMembers('posts');
            foreach ($posts as $post_id) {
                $this->sync_views($post_id);
            }
            wp_die();
        }
        echo -1;
    }

    /**
     * AJAX action for wp_ajax_rpv_sync_action
     *
     * @return void
     */
    public function sync_action()
    {
        $post_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ( $post_id ) {
            if ( $this->redis_connect() ) {
                echo $this->sync_views($post_id);
                wp_die();
            }
        }
        echo -1;
    }

    /**
     * Get the next chart color
     *
     * @param  int $i
     * @return string
     */
    public function get_chart_color($i)
    {
        if ($i == 0) {
            return '#3392db';
        }
        switch ($i % 4) {
            case 0:
                return '#4D5360';
            case 1:
                return '#F7464A';
            case 2:
                return '#46BFBD';
            default:
                return '#FDB45C';
        }
    }

    /**
     * Callable method from add_menu_item
     *
     * @return void
     */
    public function admin_page()
    {
    ?>
        <div class="wrap">
            <h1><?php echo __('Redis Post Views', $this->plugin)?></h1>

            <h2 class="nav-tab-wrapper">
                <?php if($this->settings_file): ?>
                    <a class="nav-tab <?php if($this->current_tab == 'stats'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?php echo $this->plugin?>-plugin&amp;tab=stats"><?php echo __('Statistics', $this->plugin)?></a>
                    <a class="nav-tab <?php if($this->current_tab == 'posts-queue'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?php echo $this->plugin?>-plugin&amp;tab=posts-queue"><?php echo __('Posts queue', $this->plugin)?></a>
                <?php endif; ?>
                <a class="nav-tab <?php if($this->current_tab == 'conf'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?php echo $this->plugin?>-plugin&amp;tab=conf"><?php echo __('Configuration info', $this->plugin)?></a>
            </h2>

        <?php if($this->current_tab == 'stats'): ?>
            <h2><?php echo __('Statistics', $this->plugin) ?></h2>

            <div class="wrap">
                <?php if ( $this->redis_info() ): ?>
                    <script type="text/javascript">
                        var charts = [];
                    </script>
                    <?php $i = 0;?>
                    <table class="wp-list-table widefat fixed striped">
                        <?php foreach ($this->redis_main_keys as $key => $label): ?>
                        <tr>
                            <th><?php echo __($label, $this->plugin); ?></th>
                            <?php if ($key == 'uptime_in_seconds'): ?>
                            <td><?php printf(
                                '%s day(s) %02d:%02d:%02d',
                                floor($this->redis_info[$key] / 86400),
                                floor($this->redis_info[$key] / 3600) % 24,
                                floor($this->redis_info[$key] / 60) % 60,
                                floor($this->redis_info[$key] % 60)
                            ) ?></td>
                            <?php else: ?>
                            <td><?php echo $this->redis_info[$key] ?></td>
                            <?php endif ?>
                            <?php if ($key == 'redis_version'): ?>
                            <td rowspan="<?php echo count($this->redis_main_keys); ?>">
                                <div class="chart">
                                    <h4><?php echo __('Databases', $this->plugin); ?></h4>
                                    <canvas id="chart-<?php echo ++$i ?>" width="400" height="400"></canvas>
                                </div>
                            </td>
                        <?php endif ?>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <script type="text/javascript">
                        charts[<?php echo $i; ?>] = {
                            datasets: [{data: [], backgroundColor: []}],
                            labels: []
                        };
                        <?php $j = 0; ?>
                        <?php foreach ($this->redis_databases() as $db): ?>
                            <?php $this->redis->select($db); ?>
                            charts[<?php echo $i ?>].datasets[0].data.push(<?php echo $this->redis->dbSize(); ?>);
                            charts[<?php echo $i ?>].datasets[0].backgroundColor.push('<?php echo $this->get_chart_color($i); ?>');
                            charts[<?php echo $i ?>].labels.push('Database <?php echo $db ?>');
                        <?php endforeach ?>
                    </script>

                    <script type="text/javascript">
                        for (var i = 1; i < charts.length; i++) {
                            new Chart(document.getElementById('chart-' + i).getContext('2d'), {
                                type: 'doughnut',
                                data: charts[i],
                                options: []
                            });
                        }
                    </script>

                    <h2><?php echo __('Detailed information', $this->plugin) ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <td class="manage-column"><strong>Variabile</strong></td>
                                    <td class="manage-column"><strong>Value</strong></td>
                                    <td class="manage-column"><strong>Description</strong></td>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach( $this->redis_info as $key => $value ): ?>
                                <tr>
                                    <td><?php echo $key; ?></td>
                                    <td><?php echo $value; ?></td>
                                    <td><?php echo isset($this->redis_main_keys[$key]) ? $this->redis_main_keys[$key] : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <tr>
                            <td>
                                <?php echo __('Redis error', $this->plugin) . ': ' . $this->redis_exception; ?>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
        <?php elseif( $this->current_tab == 'posts-queue' ): ?>
            <h2><?php echo __('Posts queue in Redis', $this->plugin); ?></h2>

            <div class="wrap posts-queue-tab">
                <table class="wp-list-table widefat fixed striped">
                    <?php if ( $this->posts_queue() ): ?>
                        <thead>
                            <tr>
                                <td class="manage-column"><strong>Post</strong></td>
                                <td class="manage-column"><strong>Total views</strong></td>
                                <td class="manage-column"><strong>Views to sync</strong></td>
                                <td class="manage-column"><strong>Sync</strong>
                                    <a href="#" class="rpv_sync_all"><span class="dashicons dashicons-image-rotate"></span></a>
                                </td>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($this->posts_queue) > 0): ?>
                            <?php foreach( $this->posts_queue as $post_id => $view_count ): ?>
                                <tr>
                                    <td><?php echo get_the_title($post_id); ?></td>
                                    <td class="views_<?php echo $post_id; ?>"><?php echo intval(get_post_meta($post_id, $this->post_meta_key, true)); ?></td>
                                    <td class="views_to_sync_<?php echo $post_id; ?>"><?php echo $view_count; ?></td>
                                    <td>
                                        <a href="#" data-post-id="<?php echo $post_id; ?>" class="rpv_sync"><span class="dashicons dashicons-image-rotate"></span></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4"><?php echo __('No posts in queue', $this->plugin); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php else: ?>
                        <tr>
                            <td>
                                <?php echo __('Redis error', $this->plugin) . ': ' . $this->redis_exception; ?>
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
                                    echo sprintf('<h4>' . __('You must create wp-config-rpv.php in WP root containing Redis default connection data and other options' . '</h4>', $this->plugin));
                                ?>
                                <textarea cols="100" rows="10">
<?php
                                    echo '<?php'; echo "\r\n";
                                    echo __("define('RPV_REDIS_HOST', '127.0.0.1');", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_REDIS_PORT', 6379);", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_REDIS_AUTH', '');", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_REDIS_PREFIX', 'redis-post-views'); // use custom prefix on all keys", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_REDIS_DATABASE', 0); // dbindex, the database number to switch to", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_POST_META_KEY', 'redis_post_views_count');", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_EXCLUDE_BOTS', true); // exclude bots like Google ?", $this->plugin); echo "\r\n";
                                    echo __("define('RPV_AJAX_RETURN_VIEWS', true); // does the AJAX request return post views count ?", $this->plugin);
                                ?></textarea>
                                <?php
                                    echo sprintf('<h4>' . __("Then you must include wp-config-rpv.php in wp-config.php after <em>define('ABSPATH', dirname(__FILE__) . '/');</em>" . '</h4>', $this->plugin));
                                ?>
                                <textarea cols="100" rows="5">
/**
 * Redis Post Views plugin
 */
include(ABSPATH . 'wp-config-rpv.php');</textarea>
                                <br /><br />
                                <?php
                                    echo __("You can use get_post_meta(\$post_id, RPV_POST_META_KEY, true); to get the post views");
                                ?>
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
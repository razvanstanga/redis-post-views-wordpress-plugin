<?php

if (!defined('ABSPATH')) {
    die();
}

if (!defined('WP_CLI')) return;

/**
 * Redis Post Views
 */
class WP_CLI_Redis_Post_Views_Purge_Command extends WP_CLI_Command {

    public function __construct()
    {
        $this->rpv = new Redis_Post_Views();
    }

    /**
     * Adds the views to database using add_post_meta / update_post_meta
     *
     * ## EXAMPLES
     *
     *     wp rpv sync
     *
     * @return void
     */
    public function sync()
    {
        $this->rpv->redis_connect();
        $posts = $this->rpv->redis->sMembers('posts');
        foreach ($posts as $post_id) {
            $this->rpv->sync_views($post_id);
        }

        WP_CLI::success(count($posts) . ' posts views synced.');
    }

    /**
     * Remove all keys from all databases.
     *
     * ## EXAMPLES
     *
     *     wp rpv flush
     *
     * @return void
     */
    public function flush()
    {
        $this->rpv->connect_redis();
        $this->rpv->redis->flushDb();

        WP_CLI::success('Redis cache flushed.');
    }

}

WP_CLI::add_command('rpv', 'WP_CLI_Redis_Post_Views_Purge_Command');

<?php

if (!defined('ABSPATH')) {
    die();
}

if (!defined('WP_CLI')) return;

/**
 * Redis Page Views
 */
class WP_CLI_Redis_Page_Views_Purge_Command extends WP_CLI_Command {

    public function __construct()
    {
        $this->rpv = new Redis_Page_Views();
    }

    /**
     * Adds the views
     *
     * ## EXAMPLES
     *
     *     wp rpv addviews
     *
     */
    public function addviews()
    {
        $this->rpv->connect_redis();
        $posts = $this->rpv->redis->sMembers('posts');
        foreach ($posts as $post_id) {
            $old_views = get_post_meta($post_id, $this->rpv->post_meta_key, true);
            $new_views = $this->rpv->redis->get('post-' . $post_id);
            $this->rpv->redis->delete('post-' . $post_id);
            if ($old_views) {
                $total_views = intval($old_views) + $new_views;
                update_post_meta($post_id, $this->rpv->post_meta_key, $total_views, $old_views);
            } else {
                add_post_meta($post_id, $this->rpv->post_meta_key, $new_views, true);
            }
        }

        WP_CLI::success(count($posts) . ' posts views recalculated.');
    }

    public function flush()
    {
        $this->rpv->connect_redis();
        $this->rpv->redis->flushAll();
    }

}

WP_CLI::add_command('rpv', 'WP_CLI_Redis_Page_Views_Purge_Command');

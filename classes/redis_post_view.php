<?php

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
        if ( defined('RPV_EXCLUDE_BOTS') && RPV_EXCLUDE_BOTS == true ) {
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
            $useragent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
            foreach ( $bots as $name => $pattern ) {
                if ( !empty($useragent) && ( false !== stripos($useragent, $pattern) ) ) {
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
        if ( !isset($_POST['id']) ) {
            echo 'Invalid ID';
            return;
        }
        if ( $this->exclude_bots() ) {
            echo 'Bot detected';
            return;
        }
        $post_id = intval($_POST['id']);

        try {
            if ( $this->redis_connect() ) {
                $views = intval($this->redis->get("post-" . $post_id));

                if ( $views != null ) {
                    $this->redis->incr("post-" . $post_id);
                } else {
                    $this->redis->set("post-" . $post_id, 1);
                }
                $this->redis->sAdd("posts", $post_id);
                if ( defined("RPV_AJAX_RETURN_VIEWS") && constant("RPV_AJAX_RETURN_VIEWS") == true ) {
                    $views++; echo $views;
                }
            }
        } catch (RedisException $ex) {}
    }
}
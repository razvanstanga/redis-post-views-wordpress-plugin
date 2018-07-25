=== Redis Post Views ===
Donate link: https://www.paypal.me/razvanstanga
Contributors: razvanstanga
Tags: postviews, redis, cache, caching, optimization, performance, traffic
Requires at least: 4.5
Tested up to: 4.9
Requires PHP: 5.2.4
Stable tag: 1.7
License: GPLv2 or later

Highly optimized post views using Redis

== Description ==

Imagine a high traffic website that needs post views as an algorithm to display posts on the homepage.
This website also uses <a href="https://wordpress.org/plugins/vcaching/" target="_blank">Varnish Caching</a>. So we need an AJAX based post view counter.
Now imagine a minimum 5000 concurrent users browsing the website, so we can't use the default "AJAX in Plugins" as live updates on the backend
using update_post_meta will be very painful for the backend.

So what can we do ?

What if all these post views counts will be done in memory using Redis ?
Then we run a cornjob using WP-CLI to sync the post views count in Redis to the Wordpress database.

Redis Post Views was born.

== Installation ==

* You must install Redis on your server(s)

== Frequently Asked Questions ==

= How can I display the post views in a template? =

You can do this with get_post_meta(get_the_ID(), RPV_POST_META_KEY, true); php function

== Changelog ==

= 1.7 =
* optimizations

= 1.6 =
* moved wp-config-rpv.php wo wp-config for improved compatibility

= 1.5 =
* WP-Admin sync all option
* optimizations

= 1.4 =
* show total views on Posts Queue tab
* optimizations

= 1.3 =
* improved stats using Chart.js

= 1.2 =
* posibility to sync the post views in Posts Queue tab

= 1.1 =
* added wp-admin backend including stats
* added posibility to override settings in wp-config.php

= 1.0 =
* in production extensive testing done

== Screenshots ==

1. Statistics admin panel
2. Posts queue admin panel
3. Configuration info admin panel

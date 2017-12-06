=== Redis Post Views ===
Donate link: https://www.paypal.me/razvanstanga
Contributors: razvanstanga
Tags: postviews, redis, cache, caching, optimization, performance, traffic
Requires at least: 4.5
Tested up to: 4.9
Requires PHP: 5.2.4
Stable tag: 1.1
License: GPLv2 or later

Highly optimized post views using Redis

== Description ==

This plugin was born out of necessity. Imagine a high traffic website that needs post views as an algorithm to display posts on the homepage.
This website also uses <a href="https://wordpress.org/plugins/vcaching/" target="_blank">Varnish Caching</a>. So we need an AJAX based post view counter.
Now imagine a minimum 5000 concurrent users browsing the website, so we can't use the default "AJAX in Plugins" as live updates on the backend
using update_post_meta will be very painful for the backend.

So what can we do ?

What if all these increments will be done in memory using Redis ?
Then we run a cornjob using WP-CLI to sync the post views count in Redis to the Wordpress database.

Redis Post Views is born.

== Installation ==

* You must install Redis on your server(s)

== Frequently Asked Questions ==

= How can I display the post views in a template? =

You can do this with get_post_meta(get_the_ID(), RPV_POST_META_KEY, true); php function

== Changelog ==

= 1.1 =
* added wp-admin backend including stats
* added posibility to override settings in wp-config.php

= 1.0 =
* in production testing done

== Screenshots ==

1. Settings admin panel
2. Stats admin panel
3. Posts queue admin panel
4. Configuration info admin panel

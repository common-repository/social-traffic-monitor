=== Social Traffic Monitor ===
Contributors: cfinke
Donate link: http://www.chrisfinke.com/wordpress/plugins/social-traffic/
Tags: stats, statistics, bookmarking, socialnews
Requires at least: 2.5
Tested up to: 2.6.2
Stable tag: 1.3.1

Social Traffic Monitor is a plugin for Wordpress blogs that monitors your blog traffic for activity coming from social news or bookmarking sites.

== Description ==

Social Traffic Monitor is a plugin for Wordpress blogs that monitors your blog traffic for activity coming from social news or bookmarking sites.

When someone clicks on a link to your blog on one of the major social news or bookmarking sites (currently Digg, Netscape, Reddit, Newsvine, Fark, Slashdot, Del.icio.us, and StumbleUpon), the plugin begins to log the visits to that page. This data can then be displayed in the form of a graph of visitors per hour.

Each vertical line in the graph represents one hour, and you can customize how many hours the graph shows. You can also choose to display and link to the top referrers below the graph - a great way to prompt other users to return to those sites and vote for your story.

Note: this plugin will only display the graph and/or referrers on single-post pages, and it won't display anything unless that post has been accessed from one of the social news sites.

== Installation ==

To install, copy social-traffic-monitor.php to the wp-content/plugins/ directory of your blog. Activate the plugin from your Administration panel, and then, using the theme editor, add this line of code wherever you want to display the chart and/or referrers:

`<?php social_traffic(); ?>`

Alternatively, you can supply some custom HTML to display along with the chart and/or referrers, if they are displayed by calling the plugin like so:

`<php social_traffic('<div>%s</div>'); ?>`

The "%s" will be replaced by the output created by the plugin. In this instance, the div tags will only be output if the plugin has something to display.

== Screenshots ==

1. Graph produced by the Social Traffic Monitor Wordpress plugin


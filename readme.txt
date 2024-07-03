=== Airplane Mode ===
Contributors: rizvi
Website Link: 
Donate link: 
Tags: development, local, airplane-mode, disable-updates, offline
Requires at least: 5.0
Tested up to: 6.0
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Control loading of external files when developing locally

== Description ==

Control loading of external files when developing locally. WP loads certain external files (fonts, gravatar, etc) and makes external HTTP calls. This isn't usually an issue, unless you're working in an environment without a web connection. This plugin removes/unhooks those actions to reduce load time and avoid errors due to missing files.

Features

* removes external JS and CSS files from loading
* replaces all instances of Gravatar with a local image to remove external call
* removes all HTTP requests
* disables all WP update checks for core, themes, and plugins
* includes toggle in admin bar for quick enable/disable

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `airplane-mode` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Toggle Airplane Mode as needed from the admin bar.

== Frequently Asked Questions ==

= Why do I need this? =

Because you are a developer who needs to work without an internet connection.

== Screenshots ==

1. Admin bar toggle to enable/disable Airplane Mode.

== Changelog ==

= 1.0.0 - 2023/07/03 =
* Initial release with updated and modernized codebase.
* Removed unnecessary files and consolidated functions.
* Ensured compatibility with latest WordPress standards.

== Upgrade Notice ==

= 1.0.0 =
Initial release
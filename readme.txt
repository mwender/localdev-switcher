=== LocalDev Switcher ===
Contributors: TheWebist
Tags: development, plugins, local development, plugin management, workflow
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 5.6
Stable tag: 0.6.3
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easily switch between version-controlled plugins and local development versions by toggling in the Plugins screen.

== Description ==

LocalDev Switcher allows you to seamlessly toggle between production plugins and their local development versions.

**Usage:**

1. Place your local dev version of a plugin in:
   
   `wp-content/plugins/localdev-{plugin-slug}`

2. Activate your plugin as usual from the Plugins screen.
3. Use LocalDev Switcher to toggle between the version-controlled and local versions. The toggle UI appears in the plugin meta row.

LocalDev Switcher prevents double-loading and ensures only the desired version is active.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Follow the usage instructions to begin switching between plugin versions.

== Frequently Asked Questions ==

= Does this work with themes? =

Currently, LocalDev Switcher is designed for plugins only.

= What happens if I don't have a `localdev-{plugin-slug}` version? =

LocalDev Switcher will default to using the version-controlled plugin.

== Screenshots ==

1. Adds UI to show which plugins you can switch between VCS and Local.
2. Toggle between VCS and Local versions of your plugins in the Plugins list.

== Changelog ==

= 0.6.3 =
* First public release. Toggle between VCS and local plugin versions via `localdev-{plugin-slug}` pattern.

== Upgrade Notice ==

= 0.6.3 =
Initial release.


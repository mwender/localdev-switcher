<?php
/**
 * Plugin Name: LocalDev Switcher
 * Plugin URI: https://wordpress.org/plugins/localdev-switcher/
 * Description: Toggle between VCS and local development versions of plugins using the localdev-{plugin-slug} pattern. Place local development versions in wp-content/plugins/localdev-{plugin-slug} and use this plugin to toggle between versions from the Plugins screen.
 * Version: 0.6.3
 * Author: Michael Wender
 * Author URI: https://mwender.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Tested up to: 6.8.2
 * Requires PHP: 5.6
 * Tags: development, plugins, local development, workflow
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * LocalDevSwitcher Class
 *
 * Provides functionality to toggle between version-controlled plugins and local development plugins.
 */
class LocalDevSwitcher {

  /**
   * Prefix for localdev plugins.
   *
   * @var string
   */
  private $local_prefix = 'localdev-';

  /**
   * Array of detected local plugin slugs.
   *
   * @var array
   */
  private $local_plugin_slugs = array();

  /**
   * The slug for this plugin.
   *
   * @var string
   */
  private $self_slug = 'localdev-switcher';

  /**
   * Constructor.
   */
  public function __construct() {
    add_action( 'admin_init', array( $this, 'detect_local_plugins' ) );
    add_action( 'admin_init', array( $this, 'handle_toggle_action' ) );
    add_filter( 'plugin_row_meta', array( $this, 'add_local_indicator' ), 10, 2 );
    add_filter( 'all_plugins', array( $this, 'filter_all_plugins' ), 20 );
  }

  /**
   * Detects localdev plugins.
   */
  public function detect_local_plugins() {
    $all_plugins = get_plugins();
    foreach ( $all_plugins as $plugin_file => $plugin_data ) {
      $slug = dirname( $plugin_file );
      if ( strpos( $slug, $this->local_prefix ) === 0 ) {
        $this->local_plugin_slugs[] = substr( $slug, strlen( $this->local_prefix ) );
      }
    }
  }

  /**
   * Handles toggling between localdev and VCS versions.
   */
  public function handle_toggle_action() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
      return;
    }

    if ( isset( $_GET['localdev_toggle'], $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'localdev_toggle' ) ) {
      $plugin_slug = sanitize_text_field( $_GET['localdev_toggle'] );
      $overrides = get_option( 'localdev_switcher_overrides', array() );
      $active_plugins = get_option( 'active_plugins', array() );

      $local_plugin_file = $this->local_prefix . $plugin_slug . '/' . $plugin_slug . '.php';
      $vcs_plugin_file   = $plugin_slug . '/' . $plugin_slug . '.php';

      if ( in_array( $plugin_slug, $overrides, true ) ) {
        // Switch to VCS.
        $overrides = array_diff( $overrides, array( $plugin_slug ) );
        $active_plugins = array_map( function( $plugin ) use ( $local_plugin_file, $vcs_plugin_file ) {
          return $plugin === $local_plugin_file ? $vcs_plugin_file : $plugin;
        }, $active_plugins );
      } else {
        // Switch to Local.
        $overrides[] = $plugin_slug;
        $active_plugins = array_map( function( $plugin ) use ( $local_plugin_file, $vcs_plugin_file ) {
          return $plugin === $vcs_plugin_file ? $local_plugin_file : $plugin;
        }, $active_plugins );
      }

      update_option( 'localdev_switcher_overrides', $overrides );
      update_option( 'active_plugins', $active_plugins );

      wp_redirect( admin_url( 'plugins.php' ) );
      exit;
    }
  }

  /**
   * Adds local indicator and toggle link to plugin meta rows.
   *
   * @param array $links Existing meta links.
   * @param string $file Plugin file.
   * @return array Modified links.
   */
  public function add_local_indicator( $links, $file ) {
    $plugin_slug = dirname( $file );

    if ( $plugin_slug === $this->self_slug ) {
      return $links;
    }

    foreach ( $this->local_plugin_slugs as $base_slug ) {
      if ( $plugin_slug === $base_slug || $plugin_slug === $this->local_prefix . $base_slug ) {
        $overrides = get_option( 'localdev_switcher_overrides', array() );
        $is_local = in_array( $base_slug, $overrides, true );

        $indicator = $is_local ? '<span style="padding:2px 8px; background:#00aa00; color:#fff; border-radius:10px; font-size:11px;">LOCAL ACTIVE</span> ' : '<span style="padding:2px 8px; background:#0073aa; color:#fff; border-radius:10px; font-size:11px;">VCS ACTIVE</span> ';

        $toggle_url = wp_nonce_url( add_query_arg( 'localdev_toggle', $base_slug ), 'localdev_toggle' );
        $toggle_label = $is_local ? 'Switch to VCS' : 'Switch to Local';

        array_unshift( $links, $indicator . '| <a href="' . esc_url( $toggle_url ) . '">' . esc_html( $toggle_label ) . '</a>' );
        break;
      }
    }
    return $links;
  }

  /**
   * Filters the plugins list to prevent double listing.
   *
   * @param array $plugins All plugins.
   * @return array Filtered plugins.
   */
  public function filter_all_plugins( $plugins ) {
    $overrides = get_option( 'localdev_switcher_overrides', array() );

    foreach ( $plugins as $plugin_file => $plugin_data ) {
      $slug = dirname( $plugin_file );

      if ( $slug === $this->self_slug ) {
        continue;
      }

      foreach ( $this->local_plugin_slugs as $base_slug ) {
        if ( $slug === $this->local_prefix . $base_slug ) {
          if ( ! in_array( $base_slug, $overrides, true ) ) {
            unset( $plugins[ $plugin_file ] );
          }
        }
        if ( $slug === $base_slug ) {
          if ( in_array( $base_slug, $overrides, true ) ) {
            unset( $plugins[ $plugin_file ] );
          }
        }
      }
    }
    return $plugins;
  }
}

new LocalDevSwitcher();

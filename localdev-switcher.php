<?php
/**
 * Plugin Name: LocalDev Switcher
 * Plugin URI: https://wordpress.org/plugins/localdev-switcher/
 * Description: Toggle between VCS and local development versions of plugins using the localdev-{plugin-slug} pattern. Place local development versions in wp-content/plugins/localdev-{plugin-slug} and use this plugin to toggle between versions from the Plugins screen.
 * Version: 0.7.0
 * Author: Michael Wender
 * Author URI: https://mwender.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Tested up to: 6.9.0
 * Requires PHP: 8.1
 * Tags: development, plugins, local development, workflow
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class LocalDevSwitcher {

  /**
   * Prefix for localdev plugins/themes.
   *
   * @var string
   */
  private $local_prefix = 'localdev-';

  /**
   * Detected plugin base slugs.
   *
   * @var array
   */
  private $local_plugin_slugs = array();

  /**
   * Detected theme base slugs.
   *
   * @var array
   */
  private $local_theme_slugs = array();

  /**
   * This plugin slug.
   *
   * @var string
   */
  private $self_slug = 'localdev-switcher';

  /**
   * Constructor.
   */
  public function __construct() {
    add_action( 'admin_init', array( $this, 'detect_local_plugins' ) );
    add_action( 'admin_init', array( $this, 'detect_local_themes' ) );
    add_action( 'admin_init', array( $this, 'handle_plugin_toggle' ) );

    add_filter( 'plugin_row_meta', array( $this, 'add_plugin_indicator' ), 10, 2 );
    add_filter( 'all_plugins', array( $this, 'filter_plugins_list' ), 20 );

    // Theme filtering (no UI yet).
    add_filter( 'wp_prepare_themes_for_js', array( $this, 'filter_themes_list' ), 20 );
  }

  /**
   * Get overrides option with defaults.
   *
   * @return array
   */
  private function get_overrides() {
    $overrides = get_option( 'localdev_switcher_overrides', array() );

    return wp_parse_args(
      $overrides,
      array(
        'plugins' => array(),
        'themes'  => array(),
      )
    );
  }

  /**
   * Persist overrides.
   *
   * @param array $overrides Overrides array.
   * @return void
   */
  private function save_overrides( $overrides ) {
    update_option( 'localdev_switcher_overrides', $overrides );
  }

  /**
   * Detect localdev plugins.
   *
   * @return void
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
   * Detect localdev themes.
   *
   * @return void
   */
  public function detect_local_themes() {
    $themes = wp_get_themes();

    foreach ( $themes as $stylesheet => $theme ) {
      if ( strpos( $stylesheet, $this->local_prefix ) === 0 ) {
        $this->local_theme_slugs[] = substr( $stylesheet, strlen( $this->local_prefix ) );
      }
    }
  }

  /**
   * Handle plugin toggle action.
   *
   * @return void
   */
  public function handle_plugin_toggle() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
      return;
    }

    if ( empty( $_GET['localdev_toggle'] ) || empty( $_GET['_wpnonce'] ) ) {
      return;
    }

    $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'localdev_toggle' ) ) {
      return;
    }

    $plugin_slug = sanitize_text_field( wp_unslash( $_GET['localdev_toggle'] ) );
    $overrides   = $this->get_overrides();

    $all_plugins    = get_plugins();
    $active_plugins = get_option( 'active_plugins', array() );

    $local_slug = $this->local_prefix . $plugin_slug;

    $vcs_plugin_file   = '';
    $local_plugin_file = '';

    foreach ( $all_plugins as $file => $data ) {
      if ( dirname( $file ) === $plugin_slug ) {
        $vcs_plugin_file = $file;
      }

      if ( dirname( $file ) === $local_slug ) {
        $local_plugin_file = $file;
      }
    }

    $is_local = in_array( $plugin_slug, $overrides['plugins'], true );

    if ( $is_local ) {
      $overrides['plugins'] = array_diff( $overrides['plugins'], array( $plugin_slug ) );

      $active_plugins = array_map(
        function ( $plugin ) use ( $local_plugin_file, $vcs_plugin_file ) {
          return ( $plugin === $local_plugin_file ) ? $vcs_plugin_file : $plugin;
        },
        $active_plugins
      );
    } else {
      $overrides['plugins'][] = $plugin_slug;

      $active_plugins = array_map(
        function ( $plugin ) use ( $local_plugin_file, $vcs_plugin_file ) {
          return ( $plugin === $vcs_plugin_file ) ? $local_plugin_file : $plugin;
        },
        $active_plugins
      );
    }

    $this->save_overrides( $overrides );
    update_option( 'active_plugins', $active_plugins );

    wp_redirect( admin_url( 'plugins.php' ) );
    exit;
  }

  /**
   * Add plugin indicator + toggle link.
   *
   * @param array  $links Existing links.
   * @param string $file  Plugin file.
   * @return array
   */
  public function add_plugin_indicator( $links, $file ) {
    $plugin_slug = dirname( $file );

    if ( $plugin_slug === $this->self_slug ) {
      return $links;
    }

    foreach ( $this->local_plugin_slugs as $base_slug ) {
      if ( $plugin_slug !== $base_slug && $plugin_slug !== $this->local_prefix . $base_slug ) {
        continue;
      }

      $overrides = $this->get_overrides();
      $is_local  = in_array( $base_slug, $overrides['plugins'], true );

      $indicator = $is_local
        ? '<span style="padding:2px 8px; background:#00aa00; color:#fff; border-radius:10px; font-size:11px;">LOCAL ACTIVE</span> '
        : '<span style="padding:2px 8px; background:#0073aa; color:#fff; border-radius:10px; font-size:11px;">VCS ACTIVE</span> ';

      $toggle_url   = wp_nonce_url( add_query_arg( 'localdev_toggle', $base_slug ), 'localdev_toggle' );
      $toggle_label = $is_local ? 'Switch to VCS' : 'Switch to Local';

      array_unshift(
        $links,
        $indicator . '| <a href="' . esc_url( $toggle_url ) . '">' . esc_html( $toggle_label ) . '</a>'
      );

      break;
    }

    return $links;
  }

  /**
   * Filter plugins list to hide inactive twins.
   *
   * @param array $plugins All plugins.
   * @return array
   */
  public function filter_plugins_list( $plugins ) {
    $overrides = $this->get_overrides();

    foreach ( $plugins as $plugin_file => $plugin_data ) {
      $slug = dirname( $plugin_file );

      if ( $slug === $this->self_slug ) {
        continue;
      }

      foreach ( $this->local_plugin_slugs as $base_slug ) {
        if ( $slug === $this->local_prefix . $base_slug && ! in_array( $base_slug, $overrides['plugins'], true ) ) {
          unset( $plugins[ $plugin_file ] );
        }

        if ( $slug === $base_slug && in_array( $base_slug, $overrides['plugins'], true ) ) {
          unset( $plugins[ $plugin_file ] );
        }
      }
    }

    return $plugins;
  }

  /**
   * Filter themes list to hide inactive twins.
   *
   * @param array $themes Themes prepared for JS.
   * @return array
   */
  public function filter_themes_list( $themes ) {
    $overrides    = $this->get_overrides();
    $active_theme = get_stylesheet();

    foreach ( $themes as $stylesheet => $theme_data ) {
      foreach ( $this->local_theme_slugs as $base_slug ) {
        $local_slug = $this->local_prefix . $base_slug;

        // Hide local theme if VCS is active.
        if (
          $stylesheet === $local_slug &&
          ! in_array( $base_slug, $overrides['themes'], true ) &&
          $active_theme !== $local_slug
        ) {
          unset( $themes[ $stylesheet ] );
        }

        // Hide VCS theme if local is active.
        if (
          $stylesheet === $base_slug &&
          in_array( $base_slug, $overrides['themes'], true ) &&
          $active_theme !== $base_slug
        ) {
          unset( $themes[ $stylesheet ] );
        }
      }
    }

    return $themes;
  }
}

new LocalDevSwitcher();

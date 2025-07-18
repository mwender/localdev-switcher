<?php
/**
 * Plugin Name: LocalDev Switcher
 * Description: Indicates when local development versions of plugins are present in /localdev/plugins/ and allows toggling between VCS and local versions.
 * Version: 0.2.0
 * Author: Wenmark Digital
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class LocalDevSwitcher {

  /**
   * Path to local plugins directory.
   *
   * @var string
   */
  private $local_plugins_dir;

  /**
   * Local plugin slugs.
   *
   * @var array
   */
  private $local_plugin_slugs = array();

  /**
   * Constructor.
   */
  public function __construct() {
    $this->local_plugins_dir = trailingslashit( $_SERVER['DOCUMENT_ROOT'] ) . 'localdev/plugins/';
    
    add_action( 'admin_init', array( $this, 'detect_local_plugins' ) );
    add_action( 'admin_init', array( $this, 'handle_toggle_action' ) );
    add_filter( 'plugin_row_meta', array( $this, 'add_local_indicator' ), 10, 2 );
    add_action( 'admin_notices', array( $this, 'maybe_show_missing_localdev_notice' ) );
    add_filter( 'option_active_plugins', array( $this, 'override_active_plugins' ) );
  }

  /**
   * Scan /localdev/plugins/ for available plugin folders.
   */
  public function detect_local_plugins() {
    if ( ! is_dir( $this->local_plugins_dir ) ) {
      return;
    }

    $folders = scandir( $this->local_plugins_dir );

    foreach ( $folders as $folder ) {
      if ( $folder === '.' || $folder === '..' ) {
        continue;
      }

      if ( is_dir( $this->local_plugins_dir . $folder ) ) {
        $this->local_plugin_slugs[] = $folder;
      }
    }
  }

  /**
   * Handle toggle action from the admin UI.
   */
  public function handle_toggle_action() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
      return;
    }

    if ( isset( $_GET['localdev_toggle'], $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'localdev_toggle' ) ) {
      $plugin_slug = sanitize_text_field( $_GET['localdev_toggle'] );
      $overrides = get_option( 'localdev_switcher_overrides', array() );

      if ( in_array( $plugin_slug, $overrides, true ) ) {
        // Switch to VCS.
        $overrides = array_diff( $overrides, array( $plugin_slug ) );
      } else {
        // Switch to Local.
        $overrides[] = $plugin_slug;
      }

      update_option( 'localdev_switcher_overrides', $overrides );

      wp_redirect( admin_url( 'plugins.php' ) );
      exit;
    }
  }

  /**
   * Add meta row indicator and toggle link for local plugins.
   *
   * @param array  $links Existing plugin meta links.
   * @param string $file  Plugin file.
   * @return array Modified links.
   */
  public function add_local_indicator( $links, $file ) {

    // Extract the plugin slug from the file path.
    $plugin_slug = dirname( $file );

    if ( in_array( $plugin_slug, $this->local_plugin_slugs, true ) ) {

      $overrides = get_option( 'localdev_switcher_overrides', array() );
      $is_local = in_array( $plugin_slug, $overrides, true );

      $indicator = $is_local ? '<span style="padding:2px 8px; background:#00aa00; color:#fff; border-radius:10px; font-size:11px;">LOCAL ACTIVE</span> ' : '<span style="padding:2px 8px; background:#0073aa; color:#fff; border-radius:10px; font-size:11px;">VCS ACTIVE</span> ';

      $path = esc_html( '/localdev/plugins/' . $plugin_slug );

      $toggle_url = wp_nonce_url( add_query_arg( 'localdev_toggle', $plugin_slug ), 'localdev_toggle' );
      $toggle_label = $is_local ? 'Switch to VCS' : 'Switch to Local';

      array_unshift( $links, $indicator . $path . ' | <a href="' . esc_url( $toggle_url ) . '">' . esc_html( $toggle_label ) . '</a>' );
    }

    return $links;
  }

  /**
   * Override active plugins to use local versions if toggled.
   *
   * @param array $plugins Active plugins.
   * @return array Modified active plugins.
   */
  public function override_active_plugins( $plugins ) {
    $overrides = get_option( 'localdev_switcher_overrides', array() );

    foreach ( $plugins as $key => $plugin_file ) {
      $plugin_slug = dirname( $plugin_file );

      if ( in_array( $plugin_slug, $overrides, true ) ) {
        // Override with local version.
        $local_plugin_file = $plugin_slug . '/' . basename( $plugin_file );

        if ( file_exists( $this->local_plugins_dir . $local_plugin_file ) ) {
          $plugins[ $key ] = $local_plugin_file;
        }
      }
    }

    return $plugins;
  }

  /**
   * Display admin notice if /localdev/ folder does not exist.
   */
  public function maybe_show_missing_localdev_notice() {
    $screen = get_current_screen();

    if ( $screen && $screen->id === 'plugins' && ! is_dir( trailingslashit( $_SERVER['DOCUMENT_ROOT'] ) . 'localdev' ) ) {
      echo '<div class="notice notice-info"><p>';
      echo '<strong>LocalDev Switcher:</strong> No <code>/localdev/</code> folder found in your web root directory.<br>'; 
      echo 'To set up local development plugins, create the following folder structure in your project web root:<br>'; 
      echo '<code>/localdev/plugins/</code> and place your development versions of plugins inside.<br>'; 
      echo 'For example: <code>/localdev/plugins/my-plugin/</code>';
      echo '</p></div>';
    }
  }

}

new LocalDevSwitcher();

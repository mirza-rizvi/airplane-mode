<?php
/**
 * Plugin Name: Airplane Mode
 * Plugin URI: https://github.com/mirza-rizvi/airplane-mode
 * Description: Control loading of external files when developing locally.
 * Author: Rizvi
 * Author URI: 
 * Version: 1.0.0
 * Text Domain: airplane-mode
 * Requires WP: 5.0
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/mirza-rizvi/airplane-mode
 * @package airplane-mode
 */

namespace AirplaneMode;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly to prevent direct access to the file
}

// Define constants for plugin paths and version
define( 'AIRMDE_BASE', plugin_basename( __FILE__ ) );
define( 'AIRMDE_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIRMDE_VER', '1.0.0' ); // Updated version to 1.0.0

// Load WP-CLI commands if WP-CLI is defined and available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once AIRMDE_DIR . 'inc/wp-cli.php';
}

// Main plugin class
class Core {

    private static $instance = null; // Singleton instance of the class

    private function __construct() {
        // Hook into various actions and filters
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'toggle_check' ] );
        add_action( 'admin_bar_menu', [ $this, 'admin_bar_toggle' ], 9999 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_toggle_css' ], 9999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_toggle_css' ], 9999 );
        add_filter( 'pre_http_request', [ $this, 'disable_http_reqs' ], 10, 3 );
        add_action( 'admin_init', [ $this, 'remove_update_crons' ] );

        // Register activation and deactivation hooks
        register_activation_hook( __FILE__, [ $this, 'create_setting' ] );
        register_deactivation_hook( __FILE__, [ $this, 'remove_setting' ] );
    }

    // Singleton pattern to get the instance of the class
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Load plugin textdomain for translations
    public function load_textdomain() {
        load_plugin_textdomain( 'airplane-mode', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    // Create default setting on plugin activation
    public function create_setting() {
        add_site_option( 'airplane-mode', 'on' );
    }

    // Remove setting on plugin deactivation
    public function remove_setting() {
        delete_site_option( 'airplane-mode' );
    }

    // Check if Airplane Mode is enabled
    public function enabled() {
        return 'on' === get_site_option( 'airplane-mode', 'on' );
    }

    // Block external assets if Airplane Mode is enabled
    public function block_assets( $src ) {
        return $this->enabled() && ! $this->is_local_url( $src ) ? false : $src;
    }

    // Disable HTTP requests if Airplane Mode is enabled
    public function disable_http_reqs( $preempt, $args, $url ) {
        return $this->enabled() && ! $this->is_local_url( $url ) ? new \WP_Error( 'airplane_mode_enabled', __( 'Airplane Mode is enabled', 'airplane-mode' ) ) : $preempt;
    }

    // Check toggle switch status and update setting
    public function toggle_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $nonce  = isset( $_GET['airmde_nonce'] ) ? sanitize_key( $_GET['airmde_nonce'] ) : '';
        $switch = isset( $_REQUEST['airplane-mode'] ) ? sanitize_key( $_REQUEST['airplane-mode'] ) : '';

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'airmde_nonce' ) ) {
            return;
        }

        if ( empty( $switch ) || ! in_array( $switch, [ 'on', 'off' ] ) ) {
            return;
        }

        update_site_option( 'airplane-mode', $switch );

        wp_redirect( remove_query_arg( [ 'airplane-mode', 'airmde_nonce' ] ) );
        exit;
    }

    // Add toggle switch to the admin bar
    public function admin_bar_toggle( \WP_Admin_Bar $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = $this->enabled();
        $toggle = $status ? 'off' : 'on';
        $link = wp_nonce_url( add_query_arg( 'airplane-mode', $toggle ), 'airmde_nonce', 'airmde_nonce' );

        $wp_admin_bar->add_node( [
            'id'    => 'airplane-mode-toggle',
            'title' => $status ? __( 'Airplane Mode: ON', 'airplane-mode' ) : __( 'Airplane Mode: OFF', 'airplane-mode' ),
            'href'  => $link,
        ] );
    }

    // Enqueue CSS for the toggle switch
    public function enqueue_toggle_css() {
        if ( ! is_admin() && ! is_admin_bar_showing() ) {
            return;
        }

        wp_enqueue_style( 'airplane-mode', plugins_url( 'lib/css/airplane-mode.min.css', __FILE__ ), [], AIRMDE_VER );
    }

    // Remove update cron jobs if Airplane Mode is enabled
    public function remove_update_crons() {
        if ( ! $this->enabled() ) {
            return;
        }

        remove_action( 'load-update-core.php', 'wp_update_themes' );
        remove_action( 'load-themes.php', 'wp_update_themes' );
        remove_action( 'wp_update_themes', 'wp_update_themes' );
        remove_action( 'admin_init', '_maybe_update_themes' );

        remove_action( 'load-update-core.php', 'wp_update_plugins' );
        remove_action( 'load-plugins.php', 'wp_update_plugins' );
        remove_action( 'wp_update_plugins', 'wp_update_plugins' );
        remove_action( 'admin_init', '_maybe_update_plugins' );

        remove_action( 'wp_version_check', 'wp_version_check' );
        remove_action( 'admin_init', '_maybe_update_core' );

        wp_clear_scheduled_hook( 'wp_update_themes' );
        wp_clear_scheduled_hook( 'wp_update_plugins' );
        wp_clear_scheduled_hook( 'wp_version_check' );
        wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
    }

    // Check if the URL is local (localhost or 127.0.0.1)
    private function is_local_url( $url ) {
        $parsed_url = parse_url( $url, PHP_URL_HOST );
        return in_array( $parsed_url, [ 'localhost', '127.0.0.1' ] );
    }
}

// Initialize the plugin
Core::get_instance();
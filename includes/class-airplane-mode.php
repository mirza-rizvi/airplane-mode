<?php
namespace AirplaneMode;

/**
 * Main plugin functionality for Airplane Mode.
 */
class Plugin {
    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Plugin bootstrap. Hooks into WordPress actions and filters.
     */
    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'toggle_check' ] );
        add_action( 'admin_bar_menu', [ $this, 'admin_bar_toggle' ], 9999 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_toggle_css' ], 9999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_toggle_css' ], 9999 );
        add_filter( 'pre_http_request', [ $this, 'disable_http_reqs' ], 10, 3 );
        add_filter( 'style_loader_src', [ $this, 'block_assets' ] );
        add_filter( 'script_loader_src', [ $this, 'block_assets' ] );
        add_filter( 'get_avatar', [ $this, 'replace_gravatar' ], 10, 5 );
        add_action( 'admin_init', [ $this, 'remove_update_crons' ] );

        register_activation_hook( AIRMDE_BASE, [ $this, 'create_setting' ] );
        register_deactivation_hook( AIRMDE_BASE, [ $this, 'remove_setting' ] );
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'airplane-mode', false, dirname( plugin_basename( AIRMDE_BASE ) ) . '/languages/' );
    }

    /**
     * Create default setting on plugin activation.
     */
    public function create_setting() {
        add_site_option( 'airplane-mode', 'on' );
    }

    /**
     * Remove setting on plugin deactivation.
     */
    public function remove_setting() {
        delete_site_option( 'airplane-mode' );
    }

    /**
     * Check if Airplane Mode is enabled.
     *
     * @return bool
     */
    public function enabled() {
        return 'on' === get_site_option( 'airplane-mode', 'on' );
    }

    /**
     * Block external assets if Airplane Mode is enabled.
     *
     * @param string $src Source URL.
     * @return string|false
     */
    public function block_assets( $src ) {
        return $this->enabled() && ! $this->is_local_url( $src ) ? false : $src;
    }

    /**
     * Disable HTTP requests if Airplane Mode is enabled.
     *
     * @param mixed  $preempt Whether to preempt an HTTP request's return value.
     * @param array  $args    HTTP request arguments.
     * @param string $url     URL.
     * @return mixed
     */
    public function disable_http_reqs( $preempt, $args, $url ) {
        return $this->enabled() && ! $this->is_local_url( $url )
            ? new \WP_Error( 'airplane_mode_enabled', __( 'Airplane Mode is enabled', 'airplane-mode' ) )
            : $preempt;
    }

    /**
     * Replace external Gravatar URL with a local placeholder when enabled.
     *
     * @param string          $avatar       HTML for the user's avatar.
     * @param int|object|null $id_or_email  User ID, email, or comment object.
     * @param int             $size         Size of the avatar in pixels.
     * @param string          $default      Default URL if no avatar found.
     * @param string          $alt          Alt text.
     * @return string
     */
    public function replace_gravatar( $avatar, $id_or_email, $size, $default, $alt ) {
        if ( ! $this->enabled() ) {
            return $avatar;
        }

        $src = apply_filters(
            'airplane_mode_default_avatar',
            'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAQAIBRAA7'
        );

        return sprintf(
            '<img src="%s" class="avatar avatar-%d photo" height="%d" width="%d" alt="%s" />',
            esc_url( $src ),
            (int) $size,
            (int) $size,
            (int) $size,
            esc_attr( $alt )
        );
    }

    /**
     * Check toggle switch status and update setting.
     */
    public function toggle_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $nonce  = isset( $_GET['airmde_nonce'] ) ? sanitize_key( $_GET['airmde_nonce'] ) : '';
        $switch = isset( $_REQUEST['airplane-mode'] ) ? sanitize_key( $_REQUEST['airplane-mode'] ) : '';

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'airmde_nonce' ) ) {
            return;
        }

        if ( empty( $switch ) || ! in_array( $switch, [ 'on', 'off' ], true ) ) {
            return;
        }

        update_site_option( 'airplane-mode', $switch );

        wp_safe_redirect( remove_query_arg( [ 'airplane-mode', 'airmde_nonce' ] ) );
        exit;
    }

    /**
     * Add toggle switch to the admin bar.
     *
     * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
     */
    public function admin_bar_toggle( \WP_Admin_Bar $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = $this->enabled();
        $toggle = $status ? 'off' : 'on';
        $link   = wp_nonce_url( add_query_arg( 'airplane-mode', $toggle ), 'airmde_nonce', 'airmde_nonce' );

        $wp_admin_bar->add_node( [
            'id'    => 'airplane-mode-toggle',
            'title' => $status ? __( 'Airplane Mode: ON', 'airplane-mode' ) : __( 'Airplane Mode: OFF', 'airplane-mode' ),
            'href'  => $link,
        ] );
    }

    /**
     * Enqueue CSS for the toggle switch.
     */
    public function enqueue_toggle_css() {
        if ( ! is_admin() && ! is_admin_bar_showing() ) {
            return;
        }

        wp_enqueue_style( 'airplane-mode', plugins_url( 'lib/css/airplane-mode.min.css', AIRMDE_BASE ), [], AIRMDE_VER );
    }

    /**
     * Remove update cron jobs if Airplane Mode is enabled.
     */
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

    /**
     * Determine if a given URL is local.
     *
     * @param string $url URL to check.
     * @return bool
     */
    private function is_local_url( $url ) {
        $host = parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return true; // Relative or malformed URLs are treated as local.
        }
        return in_array( $host, [ 'localhost', '127.0.0.1' ], true );
    }
}

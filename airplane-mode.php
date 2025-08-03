<?php
/**
 * Plugin Name: Airplane Mode
 * Plugin URI: https://github.com/mirza-rizvi/airplane-mode
 * Description: Control loading of external files when developing locally.
 * Author: Rizvi
 * Author URI:
 * Version: 1.1.0
 * Text Domain: airplane-mode
 * Requires WP: 5.0
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/mirza-rizvi/airplane-mode
 * @package airplane-mode
 */

namespace AirplaneMode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants for plugin paths and version.
const AIRMDE_BASE = __FILE__;
const AIRMDE_DIR  = __DIR__ . '/';
const AIRMDE_VER  = '1.1.0';

// Load main plugin class.
require_once AIRMDE_DIR . 'includes/class-airplane-mode.php';

Plugin::instance();

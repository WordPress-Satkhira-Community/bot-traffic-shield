<?php
/**
 * Plugin Name:       Bot Traffic Shield
 * Plugin URI:        https://wpsatkhira.com/bot-traffic-shield
 * Description:       Block AI crawlers and malicious scraper bots. Lightweight, configurable, with real-time charts, AI toggles, logging, and CSV export.
 * Version:           1.0.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WordPress Satkhira Community
 * Author URI:        https://wpsatkhira.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bot-traffic-shield
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BTSLD_Bot_Traffic_Shield {

    /**
     * Plugin version.
     */
    const VERSION = '1.0.6';

    /**
     * Singleton instance.
     *
     * @var BTSLD_Bot_Traffic_Shield|null
     */
    private static $_instance = null;

    /**
     * Main Instance.
     *
     * @return BTSLD_Bot_Traffic_Shield
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserializing.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init();
    }

    /**
     * Define plugin constants.
     */
    private function define_constants() {
        define( 'BTSLD_VERSION', self::VERSION );
        define( 'BTSLD_PLUGIN_FILE', __FILE__ );
        define( 'BTSLD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'BTSLD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'BTSLD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once BTSLD_PLUGIN_DIR . 'includes/class-btsld-core.php';
        require_once BTSLD_PLUGIN_DIR . 'includes/class-btsld-admin.php';

        // Load admin bar class if present
        if ( file_exists( BTSLD_PLUGIN_DIR . 'includes/class-btsld-admin-bar.php' ) ) {
            require_once BTSLD_PLUGIN_DIR . 'includes/class-btsld-admin-bar.php';
        }
    }

    /**
     * Initialize core logic and hooks.
     */
    private function init() {
        // Activation hook to set defaults and migrate settings if upgrading.
        register_activation_hook( BTSLD_PLUGIN_FILE, array( 'BTSLD_Core', 'on_activate' ) );

        // Load Core (Protection logic & logging)
        BTSLD_Core::instance();

        // Admin components (Dashboard, Settings, Charts)
        if ( is_admin() ) {
            BTSLD_Admin::instance();
        }

        // Admin bar - initialize when user authentication is available
        add_action( 'init', array( $this, 'init_admin_bar' ) );
    }

    /**
     * Initialize admin bar after WordPress auth is available.
     */
    public function init_admin_bar() {
        if ( class_exists( 'BTSLD_Admin_Bar' ) && is_user_logged_in() ) {
            BTSLD_Admin_Bar::instance();
        }
    }
}

/**
 * Global function to instantiate plugin.
 */
function btsld_run() {
    return BTSLD_Bot_Traffic_Shield::instance();
}
btsld_run();
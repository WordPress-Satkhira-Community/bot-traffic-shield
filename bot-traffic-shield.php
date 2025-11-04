<?php
/**
 * Plugin Name:       Bot Traffic Shield
 * Plugin URI:        https://monarchwp.com/bot-traffic-shield
 * Description:       Block AI crawlers and malicious scraper bots. Lightweight, configurable, with logging and CSV export.
 * Version:           1.0.3
 * Author:            MonarchWP
 * Author URI:        https://monarchwp.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bot-traffic-shield
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BTSLD_Bot_Traffic_Shield {

    const VERSION = '1.0.3';

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init();
    }

    private function define_constants() {
        define( 'BTSLD_VERSION', self::VERSION );
        define( 'BTSLD_PLUGIN_FILE', __FILE__ );
        define( 'BTSLD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'BTSLD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    private function includes() {
        require_once BTSLD_PLUGIN_DIR . 'includes/class-btsld-core.php';
        require_once BTSLD_PLUGIN_DIR . 'includes/class-btsld-admin.php';
        
        // Load admin bar class file
        if ( file_exists( BTSLD_PLUGIN_DIR . 'includes/class-btsld-admin-bar.php' ) ) {
            require_once BTSLD_PLUGIN_DIR . 'includes/class-btsld-admin-bar.php';
        }
    }

    private function init() {
        // Activation hook to set defaults.
        register_activation_hook( BTSLD_PLUGIN_FILE, array( 'BTSLD_Core', 'on_activate' ) );

        // Load core.
        BTSLD_Core::instance();

        // Admin-only components.
        if ( is_admin() ) {
            BTSLD_Admin::instance();
        }

        // Admin bar - Initialize after WordPress loads (when is_user_logged_in() is available)
        add_action( 'init', array( $this, 'init_admin_bar' ) );
    }

    /**
     * Initialize admin bar after WordPress functions are available.
     */
    public function init_admin_bar() {
        if ( class_exists( 'BTSLD_Admin_Bar' ) && is_user_logged_in() ) {
            BTSLD_Admin_Bar::instance();
        }
    }
}

function btsld_run() {
    return BTSLD_Bot_Traffic_Shield::instance();
}
btsld_run();

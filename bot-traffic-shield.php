<?php
/**
 * Plugin Name:       Bot Traffic Shield
 * Plugin URI:        https://delowerhossain.com/plugins/bot-traffic-shield
 * Description:       A powerful and user-friendly plugin to block AI crawlers and malicious data scraper bots, protecting your content and server resources.
 * Version:           1.0.0
 * Author:            Delower Hossain   
 * Author URI:        https://delowerhossain.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bot-traffic-shield
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main Bot Traffic Shield Class.
 *
 * @since 1.0.0
 */
final class Bot_Traffic_Shield {

    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * The single instance of the class.
     *
     * @var Bot_Traffic_Shield
     * @since 1.0.0
     */
    private static $_instance = null;

    /**
     * Ensures only one instance of the class is loaded.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
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
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once BTSLD_PLUGIN_DIR . 'includes/class-btsld-core.php';
        require_once BTSLD_PLUGIN_DIR . 'includes/class-btsld-admin.php';
    }
    
    /**
     * Initialize the plugin.
     */
    private function init() {
        // Activation hook
        register_activation_hook( BTSLD_PLUGIN_FILE, array( 'BTSLD_Core', 'on_activate' ) );
        
        // Load plugin classes
        BTSLD_Core::instance();
        if ( is_admin() ) {
            BTSLD_Admin::instance();
        }
    }
}

/**
 * The main function for returning the plugin instance.
 */
function btsld_run() {
    return Bot_Traffic_Shield::instance();
}

// Get the plugin running.
btsld_run();
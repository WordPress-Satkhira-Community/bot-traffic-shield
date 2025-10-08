<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BTSLD_Admin {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function admin_menu() {
        add_options_page(
            __( 'Bot Traffic Shield', 'bot-traffic-shield' ),
            __( 'Bot Traffic Shield', 'bot-traffic-shield' ),
            'manage_options',
            'bot-traffic-shield',
            array( $this, 'admin_page_html' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_bot-traffic-shield' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'btsld-admin-css', BTSLD_PLUGIN_URL . 'assets/css/btsld-admin.css', array(), BTSLD_VERSION );
        wp_enqueue_script( 'btsld-admin-js', BTSLD_PLUGIN_URL . 'assets/js/btsld-admin.js', array('jquery'), BTSLD_VERSION, true );
    }

    public function register_settings() {
        register_setting( 'btsld_settings_group', 'btsld_settings', array( $this, 'sanitize_settings' ) );
        
        add_settings_section( 'btsld_general_section', __( 'General Settings', 'bot-traffic-shield' ), null, 'bot-traffic-shield' );

        add_settings_field( 'btsld_enabled', __( 'Enable Bot Blocking', 'bot-traffic-shield' ), array( $this, 'render_field_toggle' ), 'bot-traffic-shield', 'btsld_general_section', 
            array( 'id' => 'enabled', 'label' => __( 'Master switch to activate or deactivate all blocking features.', 'bot-traffic-shield' ) ) );

        add_settings_field( 'btsld_log_blocked_bots', __( 'Enable Logging', 'bot-traffic-shield' ), array( $this, 'render_field_toggle' ), 'bot-traffic-shield', 'btsld_general_section', 
            array( 'id' => 'log_blocked_bots', 'label' => __( 'Keep a log and statistics of blocked bot requests.', 'bot-traffic-shield' ) ) );

        add_settings_field( 'btsld_custom_user_agents', __( 'Custom User Agents to Block', 'bot-traffic-shield' ), array( $this, 'render_field_textarea' ), 'bot-traffic-shield', 'btsld_general_section', 
            array( 'id' => 'custom_user_agents', 'label' => __( 'Add your own user agent strings to block, one per line.', 'bot-traffic-shield' ) ) );
    }
    
    public function sanitize_settings( $input ) {
        $sanitized_input = array();
        $sanitized_input['enabled'] = isset( $input['enabled'] ) && '1' === $input['enabled'] ? '1' : '0';
        $sanitized_input['log_blocked_bots'] = isset( $input['log_blocked_bots'] ) && '1' === $input['log_blocked_bots'] ? '1' : '0';

        if ( isset( $input['custom_user_agents'] ) ) {
            $sanitized_input['custom_user_agents'] = sanitize_textarea_field( $input['custom_user_agents'] );
        }
        
        if ( '0' === $sanitized_input['log_blocked_bots'] ) {
            delete_option( 'btsld_blocked_log' );
            delete_option( 'btsld_blocked_count' );
        }
        return $sanitized_input;
    }

    /**
     * Renders the toggle switch field for settings.
     * UPDATED: Now uses the WordPress `checked()` function for security and best practices.
     */
    public function render_field_toggle( $args ) {
        $settings = get_option( 'btsld_settings' );
        $id = $args['id'];
        // Get the current value, defaulting to '0' if it doesn't exist.
        $current_value = isset( $settings[$id] ) ? $settings[$id] : '0';

        echo '<label class="btsld-switch">';
        echo '<input type="checkbox" id="btsld_settings_' . esc_attr( $id ) . '" name="btsld_settings[' . esc_attr( $id ) . ']" value="1" ';
        
        // Use the WordPress 'checked()' function to securely output the 'checked' attribute.
        checked( $current_value, '1' );

        echo ' />';
        echo '<span class="btsld-slider"></span>';
        echo '</label>';
        echo '<p class="description">' . esc_html( $args['label'] ) . '</p>';
    }

    public function render_field_textarea( $args ) {
        $settings = get_option( 'btsld_settings' );
        $id = $args['id'];
        $value = isset( $settings[$id] ) ? $settings[$id] : '';
        echo '<textarea id="btsld_settings_' . esc_attr( $id ) . '" name="btsld_settings[' . esc_attr( $id ) . ']">' . esc_textarea( $value ) . '</textarea>';
        echo '<p class="description">' . esc_html( $args['label'] ) . '</p>';
    }

    public function admin_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap btsld-wrap">
            <h1><?php esc_html_e( 'Bot Traffic Shield', 'bot-traffic-shield' ); ?></h1>
            <p><?php esc_html_e( 'Protect your content from being scraped by AI and data collection bots.', 'bot-traffic-shield' ); ?></p>

            <h2 class="nav-tab-wrapper">
                <a href="#settings" class="nav-tab"><?php esc_html_e( 'Settings', 'bot-traffic-shield' ); ?></a>
                <a href="#log" class="nav-tab"><?php esc_html_e( 'Block Log & Stats', 'bot-traffic-shield' ); ?></a>
                <a href="#blocked-bots" class="nav-tab"><?php esc_html_e( 'Default Blocklist', 'bot-traffic-shield' ); ?></a>
            </h2>

            <div id="settings" class="btsld-tab-content">
                <form action="options.php" method="post">
                    <?php settings_fields( 'btsld_settings_group' ); ?>
                    <?php do_settings_sections( 'bot-traffic-shield' ); ?>
                    <?php submit_button( __( 'Save Settings', 'bot-traffic-shield' ) ); ?>
                </form>
            </div>

            <div id="log" class="btsld-tab-content">
                <div class="btsld-card">
                    <h2><?php esc_html_e( 'Blocking Statistics', 'bot-traffic-shield' ); ?></h2>
                    <p><strong><?php esc_html_e( 'Total Blocked Requests:', 'bot-traffic-shield' ); ?></strong> <?php echo (int) get_option( 'btsld_blocked_count', 0 ); ?></p>
                </div>
                <div class="btsld-card">
                    <h2><?php esc_html_e( 'Recent Blocked Requests', 'bot-traffic-shield' ); ?></h2>
                    <?php $log = get_option( 'btsld_blocked_log', array() ); ?>
                    <?php if ( empty( $log ) ) : ?>
                        <p><?php esc_html_e( 'No bots have been blocked yet, or logging is disabled.', 'bot-traffic-shield' ); ?></p>
                    <?php else : ?>
                        <table class="btsld-log-table">
                            <thead><tr><th><?php esc_html_e( 'Date & Time', 'bot-traffic-shield' ); ?></th><th><?php esc_html_e( 'Blocked Bot', 'bot-traffic-shield' ); ?></th><th><?php esc_html_e( 'Full User Agent', 'bot-traffic-shield' ); ?></th><th><?php esc_html_e( 'IP Address', 'bot-traffic-shield' ); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ( $log as $entry ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ); ?></td>
                                        <td><?php echo esc_html( $entry['bot'] ); ?></td>
                                        <td><?php echo esc_html( $entry['user_agent'] ); ?></td>
                                        <td><?php echo esc_html( $entry['ip'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div id="blocked-bots" class="btsld-tab-content">
                <div class="btsld-card">
                    <h2><?php esc_html_e( 'Default Blocked User Agents', 'bot-traffic-shield' ); ?></h2>
                    <p><?php esc_html_e( 'This plugin blocks any request containing these strings. This list is automatically updated with new plugin versions.', 'bot-traffic-shield' ); ?></p>
                    <ul>
                        <?php foreach ( BTSLD_Core::instance()->get_default_bot_list() as $bot ) : ?>
                            <li><code><?php echo esc_html( $bot ); ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}
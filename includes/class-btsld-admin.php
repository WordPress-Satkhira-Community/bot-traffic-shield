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

        // CSV Export handler (admin-post)
        add_action( 'admin_post_btsld_export_csv', array( $this, 'handle_export_csv' ) );

        // Clear logs AJAX handler
        add_action( 'wp_ajax_btsld_clear_logs', array( $this, 'handle_clear_logs' ) );
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
        wp_enqueue_script( 'btsld-admin-js', BTSLD_PLUGIN_URL . 'assets/js/btsld-admin.js', array( 'jquery' ), BTSLD_VERSION, true );

        // Localize script for AJAX
        wp_localize_script( 'btsld-admin-js', 'btsld_admin', array(
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'clear_logs_nonce' => wp_create_nonce( 'btsld_clear_logs_nonce' ),
            'confirm_clear'    => __( 'Are you sure you want to clear all logs? This action cannot be undone.', 'bot-traffic-shield' ),
            'clearing'         => __( 'Clearing...', 'bot-traffic-shield' ),
            'clear_logs'       => __( 'Clear All Logs', 'bot-traffic-shield' ),
            'empty_log_msg'    => __( 'No bots have been blocked yet, or logging is disabled.', 'bot-traffic-shield' ),
            'error_msg'        => __( 'An error occurred. Please try again.', 'bot-traffic-shield' ),
        ) );
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
     * CSV Export handler.
     * Streams a CSV file of the block log, with optional date range filtering.
     */
    public function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to export logs.', 'bot-traffic-shield' ), 403 );
        }

        // Verify nonce.
        $nonce = isset( $_POST['btsld_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['btsld_export_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'btsld_export_csv' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'bot-traffic-shield' ), 403 );
        }

        // Optional: days filter (0 = all).
        $days = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 0;

        // Get logs.
        $log = get_option( 'btsld_blocked_log', array() );
        if ( ! is_array( $log ) ) {
            $log = array();
        }

        // Filter by days if requested.
        if ( $days > 0 ) {
            $cutoff = time() - ( $days * DAY_IN_SECONDS );
            $log    = array_filter(
                $log,
                static function ( $entry ) use ( $cutoff ) {
                    $t = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
                    return $t >= $cutoff;
                }
            );
        }

        // Prepare headers.
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        $filename = sprintf(
            'btsld-block-log-%s-%s.csv',
            gmdate( 'Ymd-His' ),
            $days > 0 ? ( $days . 'd' ) : 'all'
        );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        // Optional BOM to help Excel read UTF-8 correctly.
        echo "\xEF\xBB\xBF";

        // Open output stream and write CSV.
        $out = fopen( 'php://output', 'w' );
        if ( false === $out ) {
            wp_die( esc_html__( 'Unable to open output stream.', 'bot-traffic-shield' ), 500 );
        }

        // Header row.
        fputcsv(
            $out,
            array(
                'Date/Time',
                'Bot',
                'IP',
                'User Agent',
            )
        );

        // Data rows.
        $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        foreach ( $log as $entry ) {
            $time       = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
            $bot        = isset( $entry['bot'] ) ? (string) $entry['bot'] : '';
            $ip         = isset( $entry['ip'] ) ? (string) $entry['ip'] : '';
            $user_agent = isset( $entry['user_agent'] ) ? (string) $entry['user_agent'] : '';

            $date_str = $time > 0 ? date_i18n( $date_format, $time ) : '';

            fputcsv(
                $out,
                array(
                    $date_str,
                    $bot,
                    $ip,
                    $user_agent,
                )
            );
        }

        exit;
    }

    /**
     * AJAX handler for clearing logs.
     * Deletes all blocked log entries and resets the counter.
     */
    public function handle_clear_logs() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'btsld_clear_logs_nonce' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'bot-traffic-shield' ),
            ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to clear logs.', 'bot-traffic-shield' ),
            ) );
        }

        // Clear the log and reset counter
        delete_option( 'btsld_blocked_log' );
        update_option( 'btsld_blocked_count', 0 );

        wp_send_json_success( array(
            'message' => __( 'All logs have been cleared successfully.', 'bot-traffic-shield' ),
        ) );
    }

    /**
     * Renders the toggle switch field for settings.
     */
    public function render_field_toggle( $args ) {
        $settings = get_option( 'btsld_settings' );
        $id = $args['id'];
        $current_value = isset( $settings[$id] ) ? $settings[$id] : '0';

        echo '<label class="btsld-switch">';
        echo '<input type="checkbox" id="btsld_settings_' . esc_attr( $id ) . '" name="btsld_settings[' . esc_attr( $id ) . ']" value="1" ';
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

    /**
     * Get paginated logs
     *
     * @param int $per_page Number of logs per page
     * @param int $page_number Current page number
     * @return array
     */
    private function get_paginated_logs( $per_page = 10, $page_number = 1 ) {
        $log = get_option( 'btsld_blocked_log', array() );
        
        if ( ! is_array( $log ) || empty( $log ) ) {
            return array();
        }

        $offset = ( $page_number - 1 ) * $per_page;
        return array_slice( $log, $offset, $per_page );
    }

    /**
     * Get total count of logs
     *
     * @return int
     */
    private function get_logs_count() {
        $log = get_option( 'btsld_blocked_log', array() );
        return is_array( $log ) ? count( $log ) : 0;
    }

    /**
     * Display pagination links
     *
     * @param int $total_items Total number of items
     * @param int $per_page Items per page
     * @param int $current_page Current page number
     */
    private function display_pagination( $total_items, $per_page, $current_page ) {
        $total_pages = ceil( $total_items / $per_page );
        
        if ( $total_pages <= 1 ) {
            return;
        }

        $page_links = paginate_links( array(
            'base'         => add_query_arg( 'log_page', '%#%' ),
            'format'       => '',
            'prev_text'    => '&laquo; ' . __( 'Previous', 'bot-traffic-shield' ),
            'next_text'    => __( 'Next', 'bot-traffic-shield' ) . ' &raquo;',
            'total'        => $total_pages,
            'current'      => $current_page,
            'type'         => 'plain',
            'add_fragment' => '#log',
        ) );

        if ( $page_links ) {
            echo '<div class="btsld-pagination">';
            
            echo '<span class="btsld-displaying-num">';
            printf(
                /* translators: %s: number of items */
                esc_html( _n( '%s item', '%s items', $total_items, 'bot-traffic-shield' ) ),
                esc_html( number_format_i18n( $total_items ) )
            );
            echo '</span>';
            
            echo '<span class="btsld-pagination-links">';
            echo wp_kses_post( $page_links );
            echo '</span>';
            
            echo '</div>';
        }
    }

    public function admin_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter
        $current_page = isset( $_GET['log_page'] ) ? max( 1, absint( $_GET['log_page'] ) ) : 1;
        $per_page = 20;
        
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
                    <p>
                        <strong><?php esc_html_e( 'Total Blocked Requests:', 'bot-traffic-shield' ); ?></strong> 
                        <span id="btsld-blocked-count"><?php echo (int) get_option( 'btsld_blocked_count', 0 ); ?></span>
                    </p>
                    <p class="btsld-clear-logs-wrapper">
                        <button type="button" id="btsld-clear-logs-btn" class="button button-secondary">
                            <?php esc_html_e( 'Clear All Logs', 'bot-traffic-shield' ); ?>
                        </button>
                        <span id="btsld-clear-logs-message" class="btsld-message"></span>
                    </p>
                </div>

                <div class="btsld-card" id="btsld-log-card">
                    <h2><?php esc_html_e( 'Recent Blocked Requests', 'bot-traffic-shield' ); ?></h2>
                    <?php 
                    $total_logs = $this->get_logs_count();
                    $paginated_logs = $this->get_paginated_logs( $per_page, $current_page );
                    ?>
                    <?php if ( empty( $paginated_logs ) ) : ?>
                        <p id="btsld-empty-log-message"><?php esc_html_e( 'No bots have been blocked yet, or logging is disabled.', 'bot-traffic-shield' ); ?></p>
                    <?php else : ?>
                        
                        <!-- Top Pagination -->
                        <?php $this->display_pagination( $total_logs, $per_page, $current_page ); ?>

                        <table class="btsld-log-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Date & Time', 'bot-traffic-shield' ); ?></th>
                                    <th><?php esc_html_e( 'Blocked Bot', 'bot-traffic-shield' ); ?></th>
                                    <th><?php esc_html_e( 'Full User Agent', 'bot-traffic-shield' ); ?></th>
                                    <th><?php esc_html_e( 'IP Address', 'bot-traffic-shield' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $paginated_logs as $entry ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ); ?></td>
                                        <td><?php echo esc_html( $entry['bot'] ); ?></td>
                                        <td><?php echo esc_html( $entry['user_agent'] ); ?></td>
                                        <td><?php echo esc_html( $entry['ip'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Bottom Pagination -->
                        <?php $this->display_pagination( $total_logs, $per_page, $current_page ); ?>
                        
                    <?php endif; ?>
                </div>

                <div class="btsld-card">
                    <h2><?php esc_html_e( 'Export Logs (CSV)', 'bot-traffic-shield' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="btsld_export_csv" />
                        <?php wp_nonce_field( 'btsld_export_csv', 'btsld_export_nonce' ); ?>

                        <label for="btsld_export_days"><strong><?php esc_html_e( 'Date range', 'bot-traffic-shield' ); ?>:</strong></label>
                        <select id="btsld_export_days" name="days">
                            <option value="30"><?php esc_html_e( 'Last 30 days', 'bot-traffic-shield' ); ?></option>
                            <option value="7"><?php esc_html_e( 'Last 7 days', 'bot-traffic-shield' ); ?></option>
                            <option value="0"><?php esc_html_e( 'All time', 'bot-traffic-shield' ); ?></option>
                        </select>
                        <?php submit_button( __( 'Export CSV', 'bot-traffic-shield' ), 'secondary', 'submit', false ); ?>
                        <p class="description"><?php esc_html_e( 'Downloads a CSV of recent blocked bot entries. Use All time to export the complete log (up to 100 most recent entries).', 'bot-traffic-shield' ); ?></p>
                    </form>
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
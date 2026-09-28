<?php
/**
 * Admin management, settings, charts, and dashboard interface.
 *
 * @package BotTrafficShield
 * @version 1.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BTSLD_Admin {

    /**
     * Singleton instance.
     *
     * @var BTSLD_Admin|null
     */
    private static $_instance = null;

    /**
     * Main Instance.
     *
     * @return BTSLD_Admin
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
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // CSV Export handler
        add_action( 'admin_post_btsld_export_csv', array( $this, 'handle_export_csv' ) );

        // Clear logs AJAX handler
        add_action( 'wp_ajax_btsld_clear_logs', array( $this, 'handle_clear_logs' ) );
    }

    /**
     * Register Admin Menu.
     */
    public function admin_menu() {
        add_options_page(
            __( 'Bot Traffic Shield', 'bot-traffic-shield' ),
            __( 'Bot Traffic Shield', 'bot-traffic-shield' ),
            'manage_options',
            'bot-traffic-shield',
            array( $this, 'admin_page_html' )
        );
    }

    /**
     * Enqueue Admin CSS and JS (including local bundled Chart.js).
     *
     * @param string $hook Page hook suffix.
     */
    public function enqueue_assets( $hook ) {
        if ( 'settings_page_bot-traffic-shield' !== $hook ) {
            return;
        }

        // Chart.js library (bundled locally for WordPress.org compliance)
        wp_enqueue_script(
            'btsld-chartjs',
            BTSLD_PLUGIN_URL . 'assets/js/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Plugin Admin Styles
        wp_enqueue_style(
            'btsld-admin-css',
            BTSLD_PLUGIN_URL . 'assets/css/btsld-admin.css',
            array(),
            BTSLD_VERSION
        );

        // Plugin Admin Script
        wp_enqueue_script(
            'btsld-admin-js',
            BTSLD_PLUGIN_URL . 'assets/js/btsld-admin.js',
            array( 'jquery', 'btsld-chartjs' ),
            BTSLD_VERSION,
            true
        );

        // Prepare localized datasets for 7, 14, and 30 day chart views
        $chart_data_7  = BTSLD_Core::get_chart_data( 7 );
        $chart_data_14 = BTSLD_Core::get_chart_data( 14 );
        $chart_data_30 = BTSLD_Core::get_chart_data( 30 );

        wp_localize_script( 'btsld-admin-js', 'btsld_admin', array(
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'clear_logs_nonce' => wp_create_nonce( 'btsld_clear_logs_nonce' ),
            'confirm_clear'    => __( 'Are you sure you want to clear all logs and statistics? This action cannot be undone.', 'bot-traffic-shield' ),
            'clearing'         => __( 'Clearing...', 'bot-traffic-shield' ),
            'clear_logs'       => __( 'Clear All Logs', 'bot-traffic-shield' ),
            'empty_log_msg'    => __( 'No bots have been blocked yet, or logging is disabled.', 'bot-traffic-shield' ),
            'error_msg'        => __( 'An error occurred. Please try again.', 'bot-traffic-shield' ),
            /* translators: %s: search engine / crawler name */
            'confirm_disable_se' => __( 'Disabling %s will block its crawler from indexing your site — only do this if you do not need that traffic. Continue?', 'bot-traffic-shield' ),
            'chartData'        => array(
                '7'  => $chart_data_7,
                '14' => $chart_data_14,
                '30' => $chart_data_30,
            ),
        ) );
    }

    /**
     * Register Settings.
     */
    public function register_settings() {
        register_setting( 'btsld_settings_group', 'btsld_settings', array( $this, 'sanitize_settings' ) );

        // Section: General Settings
        add_settings_section(
            'btsld_general_section',
            __( 'General Configuration', 'bot-traffic-shield' ),
            '__return_null',
            'bot-traffic-shield-general'
        );

        add_settings_field(
            'btsld_enabled',
            __( 'Shield Status', 'bot-traffic-shield' ),
            array( $this, 'render_field_toggle' ),
            'bot-traffic-shield-general',
            'btsld_general_section',
            array(
                'id'    => 'enabled',
                'label' => __( 'Master switch to activate or deactivate all bot blocking rules.', 'bot-traffic-shield' ),
            )
        );

        add_settings_field(
            'btsld_log_blocked_bots',
            __( 'Activity Logging', 'bot-traffic-shield' ),
            array( $this, 'render_field_toggle' ),
            'bot-traffic-shield-general',
            'btsld_general_section',
            array(
                'id'    => 'log_blocked_bots',
                'label' => __( 'Record blocked crawler requests and generate real-time analytics.', 'bot-traffic-shield' ),
            )
        );

        add_settings_field(
            'btsld_custom_user_agents',
            __( 'Custom Blocklist (User-Agents)', 'bot-traffic-shield' ),
            array( $this, 'render_field_textarea' ),
            'bot-traffic-shield-general',
            'btsld_general_section',
            array(
                'id'    => 'custom_user_agents',
                'label' => __( 'Enter additional user agents or scraper strings to block, one per line.', 'bot-traffic-shield' ),
            )
        );
    }

    /**
     * Sanitize settings on save.
     *
     * @param array $input Raw form data.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = array();

        $sanitized_input['enabled']          = ( isset( $input['enabled'] ) && '1' === (string) $input['enabled'] ) ? '1' : '0';
        $sanitized_input['log_blocked_bots'] = ( isset( $input['log_blocked_bots'] ) && '1' === (string) $input['log_blocked_bots'] ) ? '1' : '0';

        if ( isset( $input['custom_user_agents'] ) ) {
            $sanitized_input['custom_user_agents'] = sanitize_textarea_field( $input['custom_user_agents'] );
        } else {
            $sanitized_input['custom_user_agents'] = '';
        }

        // Process granular AI crawler toggles
        $registered_bots   = BTSLD_Core::get_registered_ai_bots();
        $sanitized_toggles = array();

        if ( isset( $input['ai_toggles'] ) && is_array( $input['ai_toggles'] ) ) {
            foreach ( $registered_bots as $bot_key => $bot_meta ) {
                $sanitized_toggles[ $bot_key ] = ( isset( $input['ai_toggles'][ $bot_key ] ) && '1' === (string) $input['ai_toggles'][ $bot_key ] ) ? '1' : '0';
            }
        } else {
            foreach ( $registered_bots as $bot_key => $bot_meta ) {
                $sanitized_toggles[ $bot_key ] = '0';
            }
        }

        $sanitized_input['ai_toggles'] = $sanitized_toggles;

        // Process regional search engine toggles (ON = allow, OFF = block)
        $regional_engines     = BTSLD_Core::get_regional_search_engines();
        $sanitized_se_toggles = array();

        if ( isset( $input['search_engine_toggles'] ) && is_array( $input['search_engine_toggles'] ) ) {
            foreach ( $regional_engines as $se_key => $se_meta ) {
                $sanitized_se_toggles[ $se_key ] = ( isset( $input['search_engine_toggles'][ $se_key ] ) && '1' === (string) $input['search_engine_toggles'][ $se_key ] ) ? '1' : '0';
            }
        } else {
            foreach ( $regional_engines as $se_key => $se_meta ) {
                $sanitized_se_toggles[ $se_key ] = '0';
            }
        }

        $sanitized_input['search_engine_toggles'] = $sanitized_se_toggles;

        return $sanitized_input;
    }

    /**
     * Render toggle field.
     *
     * @param array $args Field arguments.
     */
    public function render_field_toggle( $args ) {
        $settings      = get_option( 'btsld_settings', array() );
        $id            = $args['id'];
        $current_value = isset( $settings[ $id ] ) ? $settings[ $id ] : '1';

        echo '<label class="btsld-switch">';
        echo '<input type="checkbox" id="btsld_settings_' . esc_attr( $id ) . '" name="btsld_settings[' . esc_attr( $id ) . ']" value="1" ' . checked( $current_value, '1', false ) . ' />';
        echo '<span class="btsld-slider"></span>';
        echo '</label>';
        echo '<p class="description">' . esc_html( $args['label'] ) . '</p>';
    }

    /**
     * Render textarea field.
     *
     * @param array $args Field arguments.
     */
    public function render_field_textarea( $args ) {
        $settings = get_option( 'btsld_settings', array() );
        $id       = $args['id'];
        $value    = isset( $settings[ $id ] ) ? $settings[ $id ] : '';

        echo '<textarea id="btsld_settings_' . esc_attr( $id ) . '" name="btsld_settings[' . esc_attr( $id ) . ']" rows="5" class="large-text code" placeholder="ExampleBot' . "\n" . 'ScraperAgent">' . esc_textarea( $value ) . '</textarea>';
        echo '<p class="description">' . esc_html( $args['label'] ) . '</p>';
    }

    /**
     * Handle CSV Export.
     */
    public function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to export logs.', 'bot-traffic-shield' ), 403 );
        }

        $nonce = isset( $_POST['btsld_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['btsld_export_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'btsld_export_csv' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'bot-traffic-shield' ), 403 );
        }

        $days = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 0;
        $log  = get_option( 'btsld_blocked_log', array() );
        if ( ! is_array( $log ) ) {
            $log = array();
        }

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

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        $filename = sprintf(
            'btsld-blocked-bots-%s-%s.csv',
            gmdate( 'Ymd-His' ),
            $days > 0 ? ( $days . 'd' ) : 'all'
        );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        // BOM for UTF-8 Excel compatibility
        echo "\xEF\xBB\xBF";

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Direct output stream for CSV download.
        $out = fopen( 'php://output', 'w' );
        if ( false === $out ) {
            wp_die( esc_html__( 'Unable to open output stream.', 'bot-traffic-shield' ), 500 );
        }

        fputcsv( $out, array( 'Timestamp', 'Date/Time (UTC)', 'Blocked Crawler/Bot', 'IP Address', 'User Agent' ) );

        foreach ( $log as $entry ) {
            $time       = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
            $bot        = isset( $entry['bot'] ) ? (string) $entry['bot'] : '';
            $ip         = isset( $entry['ip'] ) ? (string) $entry['ip'] : '';
            $user_agent = isset( $entry['user_agent'] ) ? (string) $entry['user_agent'] : '';

            $date_str = $time > 0 ? gmdate( 'Y-m-d H:i:s', $time ) : '';

            fputcsv( $out, array( $time, $date_str, $bot, $ip, $user_agent ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing direct output stream after CSV download.
        fclose( $out );
        exit;
    }

    /**
     * AJAX handler for clearing logs.
     */
    public function handle_clear_logs() {
        check_ajax_referer( 'btsld_clear_logs_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bot-traffic-shield' ) ) );
        }

        delete_option( 'btsld_blocked_log' );
        delete_option( 'btsld_stats_daily' );
        update_option( 'btsld_blocked_count', 0 );

        wp_send_json_success( array(
            'message' => __( 'All activity logs and statistics have been reset.', 'bot-traffic-shield' ),
        ) );
    }

    /**
     * Get paginated logs.
     *
     * @param int $per_page Page limit.
     * @param int $page_number Current page.
     * @return array
     */
    private function get_paginated_logs( $per_page = 20, $page_number = 1 ) {
        $log = get_option( 'btsld_blocked_log', array() );
        if ( ! is_array( $log ) || empty( $log ) ) {
            return array();
        }
        $offset = ( $page_number - 1 ) * $per_page;
        return array_slice( $log, $offset, $per_page );
    }

    /**
     * Display pagination links.
     *
     * @param int $total_items Total number of items.
     * @param int $per_page Items per page.
     * @param int $current_page Current page number.
     */
    private function display_pagination( $total_items, $per_page, $current_page ) {
        $total_pages = ceil( $total_items / $per_page );
        if ( $total_pages <= 1 ) {
            return;
        }

        $page_links = paginate_links( array(
            'base'         => add_query_arg( 'log_page', '%#%' ),
            'format'       => '',
            'prev_text'    => '&laquo; ' . __( 'Prev', 'bot-traffic-shield' ),
            'next_text'    => __( 'Next', 'bot-traffic-shield' ) . ' &raquo;',
            'total'        => $total_pages,
            'current'      => $current_page,
            'type'         => 'plain',
            'add_fragment' => '#tab-log',
        ) );

        if ( $page_links ) {
            echo '<div class="btsld-pagination">';
            echo '<span class="btsld-displaying-num">';
            printf(
                /* translators: %s: number of total log items */
                esc_html__( '%s total events', 'bot-traffic-shield' ),
                esc_html( number_format_i18n( $total_items ) )
            );
            echo '</span>';
            echo '<span class="btsld-pagination-links">' . wp_kses_post( $page_links ) . '</span>';
            echo '</div>';
        }
    }

    /**
     * Main Admin Page HTML.
     */
    public function admin_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings         = get_option( 'btsld_settings', array() );
        $registered_bots  = BTSLD_Core::get_registered_ai_bots();
        $ai_toggles       = isset( $settings['ai_toggles'] ) && is_array( $settings['ai_toggles'] ) ? $settings['ai_toggles'] : array();
        $regional_engines = BTSLD_Core::get_regional_search_engines();
        $se_toggles       = isset( $settings['search_engine_toggles'] ) && is_array( $settings['search_engine_toggles'] ) ? $settings['search_engine_toggles'] : array();
        $core_engines     = BTSLD_Core::get_core_search_engines();

        $total_blocked = (int) get_option( 'btsld_blocked_count', 0 );
        $daily_stats   = get_option( 'btsld_stats_daily', array() );
        $today_key     = gmdate( 'Y-m-d' );
        $blocked_today = isset( $daily_stats[ $today_key ]['total'] ) ? (int) $daily_stats[ $today_key ]['total'] : 0;

        // Count active AI filters
        $active_ai_count = 0;
        foreach ( $registered_bots as $k => $v ) {
            if ( ! isset( $ai_toggles[ $k ] ) || '1' === (string) $ai_toggles[ $k ] ) {
                $active_ai_count++;
            }
        }

        // Find top scraper
        $top_bot_name = 'None';
        if ( ! empty( $daily_stats ) ) {
            $aggregate_bots = array();
            foreach ( $daily_stats as $day ) {
                if ( ! empty( $day['by_bot'] ) ) {
                    foreach ( $day['by_bot'] as $b_name => $b_count ) {
                        $aggregate_bots[ $b_name ] = ( isset( $aggregate_bots[ $b_name ] ) ? $aggregate_bots[ $b_name ] : 0 ) + $b_count;
                    }
                }
            }
            if ( ! empty( $aggregate_bots ) ) {
                arsort( $aggregate_bots );
                $top_bot_name = key( $aggregate_bots );
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination query parameter.
        $current_page   = isset( $_GET['log_page'] ) ? max( 1, absint( wp_unslash( $_GET['log_page'] ) ) ) : 1;
        $per_page       = 20;
        $all_logs       = get_option( 'btsld_blocked_log', array() );
        $total_logs     = is_array( $all_logs ) ? count( $all_logs ) : 0;
        $paginated_logs = $this->get_paginated_logs( $per_page, $current_page );
        ?>

        <div class="wrap btsld-wrap">
            <header class="btsld-header">
                <div class="btsld-header-left">
                    <div class="btsld-logo-badge">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <div>
                        <h1><?php esc_html_e( 'Bot Traffic Shield', 'bot-traffic-shield' ); ?> <span class="btsld-version-pill">v<?php echo esc_html( BTSLD_VERSION ); ?></span></h1>
                        <p class="btsld-subtitle"><?php esc_html_e( 'Block unauthorized AI scrapers, protect bandwidth, and monitor traffic in real-time.', 'bot-traffic-shield' ); ?></p>
                    </div>
                </div>
                <div class="btsld-header-right">
                    <span class="btsld-status-badge <?php echo ( isset( $settings['enabled'] ) && '1' === (string) $settings['enabled'] ) ? 'status-active' : 'status-inactive'; ?>">
                        <span class="status-dot"></span>
                        <?php echo ( isset( $settings['enabled'] ) && '1' === (string) $settings['enabled'] ) ? esc_html__( 'Shield Active', 'bot-traffic-shield' ) : esc_html__( 'Shield Paused', 'bot-traffic-shield' ); ?>
                    </span>
                </div>
            </header>

            <!-- Navigation Tabs -->
            <nav class="nav-tab-wrapper btsld-tabs-nav">
                <a href="#tab-dashboard" class="nav-tab nav-tab-active">
                    <span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Dashboard & Charts', 'bot-traffic-shield' ); ?>
                </a>
                <a href="#tab-ai-crawlers" class="nav-tab">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'AI Crawler Rules', 'bot-traffic-shield' ); ?>
                </a>
                <a href="#tab-search-engines" class="nav-tab">
                    <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Search Engine Rules', 'bot-traffic-shield' ); ?>
                </a>
                <a href="#tab-settings" class="nav-tab">
                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'General Settings', 'bot-traffic-shield' ); ?>
                </a>
                <a href="#tab-log" class="nav-tab">
                    <span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Activity Log', 'bot-traffic-shield' ); ?>
                </a>
            </nav>

            <!-- TAB 1: DASHBOARD & CHARTS -->
            <div id="tab-dashboard" class="btsld-tab-content btsld-tab-active">
                <!-- KPI Metrics Cards -->
                <div class="btsld-kpi-grid">
                    <div class="btsld-kpi-card">
                        <div class="btsld-kpi-icon icon-purple"><span class="dashicons dashicons-shield"></span></div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Total Blocked Requests', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val" id="btsld-blocked-count"><?php echo esc_html( number_format_i18n( $total_blocked ) ); ?></span>
                        </div>
                    </div>

                    <div class="btsld-kpi-card">
                        <div class="btsld-kpi-icon icon-blue"><span class="dashicons dashicons-clock"></span></div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Blocked Today (24h)', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val"><?php echo esc_html( number_format_i18n( $blocked_today ) ); ?></span>
                        </div>
                    </div>

                    <div class="btsld-kpi-card">
                        <div class="btsld-kpi-icon icon-green"><span class="dashicons dashicons-yes-alt"></span></div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Active AI Rules', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val"><?php echo esc_html( $active_ai_count . ' / ' . count( $registered_bots ) ); ?></span>
                        </div>
                    </div>

                    <div class="btsld-kpi-card">
                        <div class="btsld-kpi-icon icon-orange"><span class="dashicons dashicons-warning"></span></div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Top AI Scraper', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val btsld-kpi-val-sm"><?php echo esc_html( $top_bot_name ); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="btsld-charts-grid">
                    <div class="btsld-card btsld-chart-card btsld-chart-main">
                        <div class="btsld-card-header">
                            <div>
                                <h2><?php esc_html_e( 'Blocked Requests Trend', 'bot-traffic-shield' ); ?></h2>
                                <p class="btsld-card-desc"><?php esc_html_e( 'Historical view of blocked AI crawler requests.', 'bot-traffic-shield' ); ?></p>
                            </div>
                            <div class="btsld-timeframe-selector">
                                <button type="button" class="btsld-tf-btn active" data-range="7"><?php esc_html_e( '7 Days', 'bot-traffic-shield' ); ?></button>
                                <button type="button" class="btsld-tf-btn" data-range="14"><?php esc_html_e( '14 Days', 'bot-traffic-shield' ); ?></button>
                                <button type="button" class="btsld-tf-btn" data-range="30"><?php esc_html_e( '30 Days', 'bot-traffic-shield' ); ?></button>
                            </div>
                        </div>
                        <div class="btsld-chart-wrapper">
                            <canvas id="btsldTrafficChart"></canvas>
                        </div>
                    </div>

                    <div class="btsld-card btsld-chart-card btsld-chart-donut">
                        <div class="btsld-card-header">
                            <h2><?php esc_html_e( 'Bot Traffic Breakdown', 'bot-traffic-shield' ); ?></h2>
                        </div>
                        <div class="btsld-chart-wrapper doughnut-wrapper">
                            <canvas id="btsldDonutChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: AI CRAWLER RULES -->
            <div id="tab-ai-crawlers" class="btsld-tab-content">
                <form action="options.php" method="post">
                    <?php settings_fields( 'btsld_settings_group' ); ?>
                    <input type="hidden" name="btsld_settings[enabled]" value="<?php echo esc_attr( isset( $settings['enabled'] ) ? $settings['enabled'] : '1' ); ?>" />
                    <input type="hidden" name="btsld_settings[log_blocked_bots]" value="<?php echo esc_attr( isset( $settings['log_blocked_bots'] ) ? $settings['log_blocked_bots'] : '1' ); ?>" />
                    <input type="hidden" name="btsld_settings[custom_user_agents]" value="<?php echo esc_textarea( isset( $settings['custom_user_agents'] ) ? $settings['custom_user_agents'] : '' ); ?>" />
                    <?php foreach ( $regional_engines as $se_key => $se_info ) :
                        $is_allowed = isset( $se_toggles[ $se_key ] ) ? ( '1' === (string) $se_toggles[ $se_key ] ) : true;
                    ?>
                        <input type="hidden" name="btsld_settings[search_engine_toggles][<?php echo esc_attr( $se_key ); ?>]" value="<?php echo esc_attr( $is_allowed ? '1' : '0' ); ?>" />
                    <?php endforeach; ?>

                    <div class="btsld-card">
                        <div class="btsld-card-header">
                            <div>
                                <h2><?php esc_html_e( 'Manage AI Crawlers & Scrapers', 'bot-traffic-shield' ); ?></h2>
                                <p class="btsld-card-desc"><?php esc_html_e( 'Enable or disable protection against specific AI crawlers and data brokers below.', 'bot-traffic-shield' ); ?></p>
                            </div>
                            <div class="btsld-bulk-actions">
                                <button type="button" id="btsld-select-all-ai" class="button button-secondary"><?php esc_html_e( 'Select All', 'bot-traffic-shield' ); ?></button>
                                <button type="button" id="btsld-deselect-all-ai" class="button button-secondary"><?php esc_html_e( 'Deselect All', 'bot-traffic-shield' ); ?></button>
                            </div>
                        </div>

                        <div class="btsld-ai-grid">
                            <?php foreach ( $registered_bots as $bot_key => $bot_info ) : 
                                $is_active = isset( $ai_toggles[ $bot_key ] ) ? ( '1' === (string) $ai_toggles[ $bot_key ] ) : true;
                            ?>
                                <div class="btsld-ai-card">
                                    <div class="btsld-ai-card-top">
                                        <span class="btsld-ai-provider-badge"><?php echo esc_html( $bot_info['provider'] ); ?></span>
                                        <label class="btsld-switch">
                                            <input type="checkbox" class="btsld-ai-checkbox" name="btsld_settings[ai_toggles][<?php echo esc_attr( $bot_key ); ?>]" value="1" <?php checked( $is_active, true ); ?> />
                                            <span class="btsld-slider"></span>
                                        </label>
                                    </div>
                                    <h3 class="btsld-ai-title"><?php echo esc_html( $bot_info['label'] ); ?></h3>
                                    <p class="btsld-ai-desc"><?php echo esc_html( $bot_info['description'] ); ?></p>
                                    <div class="btsld-ai-agents">
                                        <?php foreach ( $bot_info['agents'] as $agent ) : ?>
                                            <code><?php echo esc_html( $agent ); ?></code>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="btsld-card-footer">
                            <?php submit_button( __( 'Save AI Crawler Rules', 'bot-traffic-shield' ), 'primary', 'submit', false ); ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB: SEARCH ENGINE RULES -->
            <div id="tab-search-engines" class="btsld-tab-content">
                <form action="options.php" method="post">
                    <?php settings_fields( 'btsld_settings_group' ); ?>
                    <input type="hidden" name="btsld_settings[enabled]" value="<?php echo esc_attr( isset( $settings['enabled'] ) ? $settings['enabled'] : '1' ); ?>" />
                    <input type="hidden" name="btsld_settings[log_blocked_bots]" value="<?php echo esc_attr( isset( $settings['log_blocked_bots'] ) ? $settings['log_blocked_bots'] : '1' ); ?>" />
                    <input type="hidden" name="btsld_settings[custom_user_agents]" value="<?php echo esc_textarea( isset( $settings['custom_user_agents'] ) ? $settings['custom_user_agents'] : '' ); ?>" />
                    <?php foreach ( $registered_bots as $bot_key => $bot_info ) :
                        $is_active = isset( $ai_toggles[ $bot_key ] ) ? ( '1' === (string) $ai_toggles[ $bot_key ] ) : true;
                    ?>
                        <input type="hidden" name="btsld_settings[ai_toggles][<?php echo esc_attr( $bot_key ); ?>]" value="<?php echo esc_attr( $is_active ? '1' : '0' ); ?>" />
                    <?php endforeach; ?>

                    <div class="btsld-card">
                        <div class="btsld-card-header">
                            <div>
                                <h2><?php esc_html_e( 'Core Search Engines (Always Protected)', 'bot-traffic-shield' ); ?></h2>
                                <p class="btsld-card-desc"><?php esc_html_e( 'Google, Bing, and Yahoo are always allowed. There is no toggle so your SEO cannot be broken by accident.', 'bot-traffic-shield' ); ?></p>
                            </div>
                        </div>
                        <div class="btsld-ai-grid">
                            <?php
                            $core_groups = array(
                                'Google' => array( 'Googlebot', 'Googlebot-Image', 'Googlebot-Video', 'Googlebot-News', 'Storebot-Google', 'Google-InspectionTool', 'GoogleOther' ),
                                'Bing'   => array( 'bingbot', 'msnbot', 'BingPreview', 'adidxbot' ),
                                'Yahoo'  => array( 'Slurp' ),
                            );
                            foreach ( $core_groups as $group_label => $tokens ) :
                            ?>
                                <div class="btsld-ai-card btsld-core-se-card">
                                    <div class="btsld-ai-card-top">
                                        <span class="btsld-ai-provider-badge"><?php echo esc_html( $group_label ); ?></span>
                                        <span class="btsld-core-lock" title="<?php esc_attr_e( 'Always allowed', 'bot-traffic-shield' ); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                            <?php esc_html_e( 'Always on', 'bot-traffic-shield' ); ?>
                                        </span>
                                    </div>
                                    <h3 class="btsld-ai-title"><?php echo esc_html( $group_label ); ?></h3>
                                    <p class="btsld-ai-desc"><?php esc_html_e( 'Protected permanently. Cannot be disabled.', 'bot-traffic-shield' ); ?></p>
                                    <div class="btsld-ai-agents">
                                        <?php foreach ( $tokens as $token ) : ?>
                                            <code><?php echo esc_html( $token ); ?></code>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="btsld-card" style="margin-top: 20px;">
                        <div class="btsld-card-header">
                            <div>
                                <h2><?php esc_html_e( 'Regional & Other Crawlers', 'bot-traffic-shield' ); ?></h2>
                                <p class="btsld-card-desc"><?php esc_html_e( 'Toggle individual regional search engines and social/SEO crawlers. Disabling a crawler blocks it from indexing your site.', 'bot-traffic-shield' ); ?></p>
                            </div>
                            <div class="btsld-bulk-actions">
                                <button type="button" id="btsld-select-all-se" class="button button-secondary"><?php esc_html_e( 'Allow All', 'bot-traffic-shield' ); ?></button>
                                <button type="button" id="btsld-deselect-all-se" class="button button-secondary"><?php esc_html_e( 'Block All', 'bot-traffic-shield' ); ?></button>
                            </div>
                        </div>

                        <div class="btsld-ai-grid">
                            <?php foreach ( $regional_engines as $se_key => $se_info ) :
                                $is_allowed = isset( $se_toggles[ $se_key ] ) ? ( '1' === (string) $se_toggles[ $se_key ] ) : true;
                            ?>
                                <div class="btsld-ai-card">
                                    <div class="btsld-ai-card-top">
                                        <span class="btsld-ai-provider-badge"><?php echo esc_html( $se_info['provider'] ); ?></span>
                                        <label class="btsld-switch">
                                            <input type="checkbox"
                                                class="btsld-se-checkbox"
                                                name="btsld_settings[search_engine_toggles][<?php echo esc_attr( $se_key ); ?>]"
                                                value="1"
                                                data-se-label="<?php echo esc_attr( $se_info['label'] ); ?>"
                                                <?php checked( $is_allowed, true ); ?> />
                                            <span class="btsld-slider"></span>
                                        </label>
                                    </div>
                                    <h3 class="btsld-ai-title"><?php echo esc_html( $se_info['label'] ); ?></h3>
                                    <p class="btsld-ai-desc"><?php echo esc_html( $se_info['description'] ); ?></p>
                                    <div class="btsld-ai-agents">
                                        <?php foreach ( $se_info['agents'] as $agent ) : ?>
                                            <code><?php echo esc_html( $agent ); ?></code>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="btsld-card-footer">
                            <?php submit_button( __( 'Save Search Engine Rules', 'bot-traffic-shield' ), 'primary', 'submit', false ); ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB 3: GENERAL SETTINGS -->
            <div id="tab-settings" class="btsld-tab-content">
                <form action="options.php" method="post">
                    <?php settings_fields( 'btsld_settings_group' ); ?>
                    
                    <?php foreach ( $registered_bots as $bot_key => $bot_info ) : 
                        $is_active = isset( $ai_toggles[ $bot_key ] ) ? ( '1' === (string) $ai_toggles[ $bot_key ] ) : ( $bot_info['default'] ? '1' : '0' );
                    ?>
                        <input type="hidden" name="btsld_settings[ai_toggles][<?php echo esc_attr( $bot_key ); ?>]" value="<?php echo esc_attr( $is_active ? '1' : '0' ); ?>" />
                    <?php endforeach; ?>
                    <?php foreach ( $regional_engines as $se_key => $se_info ) :
                        $is_allowed = isset( $se_toggles[ $se_key ] ) ? ( '1' === (string) $se_toggles[ $se_key ] ) : true;
                    ?>
                        <input type="hidden" name="btsld_settings[search_engine_toggles][<?php echo esc_attr( $se_key ); ?>]" value="<?php echo esc_attr( $is_allowed ? '1' : '0' ); ?>" />
                    <?php endforeach; ?>

                    <div class="btsld-card">
                        <h2><?php esc_html_e( 'General Configuration', 'bot-traffic-shield' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Bot Traffic Shield Master Switch', 'bot-traffic-shield' ); ?></th>
                                <td>
                                    <?php $this->render_field_toggle( array( 'id' => 'enabled', 'label' => __( 'Turn on to actively block matching bots with a 403 Forbidden header and robots.txt disallow rules.', 'bot-traffic-shield' ) ) ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Request Logging & Analytics', 'bot-traffic-shield' ); ?></th>
                                <td>
                                    <?php $this->render_field_toggle( array( 'id' => 'log_blocked_bots', 'label' => __( 'Save records of blocked crawler hits for the dashboard charts and event log.', 'bot-traffic-shield' ) ) ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Custom User Agents to Block', 'bot-traffic-shield' ); ?></th>
                                <td>
                                    <?php $this->render_field_textarea( array( 'id' => 'custom_user_agents', 'label' => __( 'Add custom bot names or partial user agent strings (one per line). Requests matching these strings will be instantly blocked.', 'bot-traffic-shield' ) ) ); ?>
                                </td>
                            </tr>
                        </table>

                        <div class="btsld-card-footer">
                            <?php submit_button( __( 'Save General Settings', 'bot-traffic-shield' ), 'primary', 'submit', false ); ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB 4: ACTIVITY LOG & CSV EXPORT -->
            <div id="tab-log" class="btsld-tab-content">
                <div class="btsld-card" id="btsld-log-card">
                    <div class="btsld-card-header">
                        <div>
                            <h2><?php esc_html_e( 'Recent Blocked Bot Requests', 'bot-traffic-shield' ); ?></h2>
                            <p class="btsld-card-desc"><?php esc_html_e( 'Displays the latest blocked crawler requests with IP, Bot ID, and User Agent details.', 'bot-traffic-shield' ); ?></p>
                        </div>
                        <div>
                            <button type="button" id="btsld-clear-logs-btn" class="button button-secondary btsld-btn-danger">
                                <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear All Logs', 'bot-traffic-shield' ); ?>
                            </button>
                            <span id="btsld-clear-logs-message" class="btsld-message"></span>
                        </div>
                    </div>

                    <?php if ( empty( $paginated_logs ) ) : ?>
                        <div class="btsld-empty-state" id="btsld-empty-log-message">
                            <span class="dashicons dashicons-shield-alt"></span>
                            <p><?php esc_html_e( 'No bots have been blocked yet, or logging is disabled.', 'bot-traffic-shield' ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php $this->display_pagination( $total_logs, $per_page, $current_page ); ?>

                        <div class="btsld-table-responsive">
                            <table class="wp-list-table widefat fixed striped btsld-log-table">
                                <thead>
                                    <tr>
                                        <th style="width: 180px;"><?php esc_html_e( 'Date & Time (UTC)', 'bot-traffic-shield' ); ?></th>
                                        <th style="width: 160px;"><?php esc_html_e( 'Blocked Bot', 'bot-traffic-shield' ); ?></th>
                                        <th style="width: 140px;"><?php esc_html_e( 'IP Address', 'bot-traffic-shield' ); ?></th>
                                        <th><?php esc_html_e( 'Full User Agent String', 'bot-traffic-shield' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $paginated_logs as $entry ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', (int) $entry['time'] ) ); ?></td>
                                            <td><span class="btsld-bot-badge"><?php echo esc_html( $entry['bot'] ); ?></span></td>
                                            <td><code><?php echo esc_html( $entry['ip'] ); ?></code></td>
                                            <td class="btsld-ua-cell" title="<?php echo esc_attr( $entry['user_agent'] ); ?>"><?php echo esc_html( $entry['user_agent'] ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php $this->display_pagination( $total_logs, $per_page, $current_page ); ?>
                    <?php endif; ?>
                </div>

                <!-- Export CSV Card -->
                <div class="btsld-card">
                    <h2><?php esc_html_e( 'Export Log to CSV', 'bot-traffic-shield' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="btsld-export-form">
                        <input type="hidden" name="action" value="btsld_export_csv" />
                        <?php wp_nonce_field( 'btsld_export_csv', 'btsld_export_nonce' ); ?>

                        <div class="btsld-export-row">
                            <label for="btsld_export_days"><strong><?php esc_html_e( 'Date Range:', 'bot-traffic-shield' ); ?></strong></label>
                            <select id="btsld_export_days" name="days" class="regular-text" style="max-width: 200px;">
                                <option value="7"><?php esc_html_e( 'Last 7 days', 'bot-traffic-shield' ); ?></option>
                                <option value="14"><?php esc_html_e( 'Last 14 days', 'bot-traffic-shield' ); ?></option>
                                <option value="30"><?php esc_html_e( 'Last 30 days', 'bot-traffic-shield' ); ?></option>
                                <option value="0"><?php esc_html_e( 'All time', 'bot-traffic-shield' ); ?></option>
                            </select>
                            <?php submit_button( __( 'Download CSV Export', 'bot-traffic-shield' ), 'secondary', 'submit', false ); ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
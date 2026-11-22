<?php
/**
 * Admin management, settings, charts, and dashboard interface.
 *
 * @package BotTrafficShield
 * @version 2.1.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BTSLD_Admin {

    /**
     * Slug of the top-level menu and the dashboard page.
     */
    const MENU_SLUG = 'bot-traffic-shield';

    /**
     * Singleton instance.
     *
     * @var BTSLD_Admin|null
     */
    private static $_instance = null;

    /**
     * Hook suffix of the dashboard page.
     *
     * @var string
     */
    private $page_hook = '';

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
        add_action( 'admin_init', array( $this, 'redirect_legacy_settings_url' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // CSV Export handler
        add_action( 'admin_post_btsld_export_csv', array( $this, 'handle_export_csv' ) );

        // Clear logs AJAX handler
        add_action( 'wp_ajax_btsld_clear_logs', array( $this, 'handle_clear_logs' ) );

        // Live dashboard refresh AJAX handler
        add_action( 'wp_ajax_btsld_refresh_dashboard', array( $this, 'handle_refresh_dashboard' ) );
    }

    /**
     * Register Admin Menu.
     */
    public function admin_menu() {
        add_menu_page(
            __( 'Bot Traffic Shield', 'bot-traffic-shield' ),
            __( 'Bot Shield', 'bot-traffic-shield' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'admin_page_html' ),
            'dashicons-shield-alt',
            76
        );

        // Premium submenus are added after this one by BTSLD_Premium.
        $this->page_hook = add_submenu_page(
            self::MENU_SLUG,
            __( 'Bot Traffic Shield', 'bot-traffic-shield' ),
            __( 'Dashboard', 'bot-traffic-shield' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'admin_page_html' )
        );
    }

    /**
     * Redirect the old Settings > Bot Traffic Shield URL to the top-level menu.
     */
    public function redirect_legacy_settings_url() {
        global $pagenow;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing.
        if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && self::MENU_SLUG === $_GET['page'] ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
            exit;
        }
    }

    /**
     * Enqueue Admin CSS and JS (including local bundled Chart.js).
     *
     * @param string $hook Page hook suffix.
     */
    public function enqueue_assets( $hook ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Used only to identify the current admin screen.
        $page          = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $is_dashboard  = $this->page_hook === $hook;
        $is_policies   = 'bot-traffic-shield-policies' === $page;

        if ( ! $is_dashboard && ! $is_policies ) {
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
            'refresh_nonce'    => wp_create_nonce( 'btsld_refresh_dashboard_nonce' ),
            'confirm_clear'    => __( 'Are you sure you want to clear all logs and statistics? This action cannot be undone.', 'bot-traffic-shield' ),
            'clearing'         => __( 'Clearing...', 'bot-traffic-shield' ),
            'clear_logs'       => __( 'Clear All Logs', 'bot-traffic-shield' ),
            'empty_log_msg'    => __( 'No bots have been blocked yet, or logging is disabled.', 'bot-traffic-shield' ),
            'error_msg'        => __( 'An error occurred. Please try again.', 'bot-traffic-shield' ),
            'refreshing'       => __( 'Refreshing...', 'bot-traffic-shield' ),
            'refreshed'        => __( 'Updated Just Now', 'bot-traffic-shield' ),
            'label_requests'   => __( 'Blocked Requests', 'bot-traffic-shield' ),
            'label_bandwidth'  => __( 'Bandwidth Saved (MB)', 'bot-traffic-shield' ),
            'unit_requests'    => __( 'bots blocked', 'bot-traffic-shield' ),
            'unit_bandwidth'   => __( 'MB saved', 'bot-traffic-shield' ),
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
     * Render a free rule screen inside the Rules & Policies menu group.
     *
     * @param string $key Rule screen key.
     */
    public function render_free_rules_page( $key ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings         = get_option( 'btsld_settings', array() );
        $free_bots        = BTSLD_Core::get_free_ai_bots();
        $pro_bots         = BTSLD_Core::get_pro_ai_bots();
        $is_pro           = class_exists( 'BTSLD_Premium' ) && method_exists( 'BTSLD_Premium', 'is_pro_active' ) ? BTSLD_Premium::is_pro_active() : defined( 'BTSLD_PRO_VERSION' );
        $registered_bots  = BTSLD_Core::get_registered_ai_bots();
        $ai_toggles       = isset( $settings['ai_toggles'] ) && is_array( $settings['ai_toggles'] ) ? $settings['ai_toggles'] : array();
        $regional_engines = BTSLD_Core::get_regional_search_engines();
        $se_toggles       = isset( $settings['search_engine_toggles'] ) && is_array( $settings['search_engine_toggles'] ) ? $settings['search_engine_toggles'] : array();

        settings_errors();

        if ( 'ai-policies' === $key ) :
            ?>
            <div class="btsld-section-heading">
                <h2><?php esc_html_e( 'AI Crawler Policies', 'bot-traffic-shield' ); ?></h2>
                <p><?php esc_html_e( 'Granular content protection rules to control LLM training crawlers, generative search engines, and automated data extractors.', 'bot-traffic-shield' ); ?></p>
            </div>
            <form action="options.php" method="post">
                <?php settings_fields( 'btsld_settings_group' ); ?>
                <input type="hidden" name="btsld_settings[enabled]" value="<?php echo esc_attr( isset( $settings['enabled'] ) ? $settings['enabled'] : '1' ); ?>" />
                <input type="hidden" name="btsld_settings[log_blocked_bots]" value="<?php echo esc_attr( isset( $settings['log_blocked_bots'] ) ? $settings['log_blocked_bots'] : '1' ); ?>" />
                <input type="hidden" name="btsld_settings[activity_log_enabled]" value="<?php echo esc_attr( isset( $settings['activity_log_enabled'] ) ? $settings['activity_log_enabled'] : '1' ); ?>" />
                <input type="hidden" name="btsld_settings[log_max_entries]" value="<?php echo esc_attr( isset( $settings['log_max_entries'] ) ? $settings['log_max_entries'] : 150 ); ?>" />
                <input type="hidden" name="btsld_settings[admin_bar_enabled]" value="<?php echo esc_attr( isset( $settings['admin_bar_enabled'] ) ? $settings['admin_bar_enabled'] : '1' ); ?>" />
                <input type="hidden" name="btsld_settings[custom_user_agents]" value="<?php echo esc_textarea( isset( $settings['custom_user_agents'] ) ? $settings['custom_user_agents'] : '' ); ?>" />
                <?php foreach ( $regional_engines as $se_key => $se_info ) :
                    $is_allowed = isset( $se_toggles[ $se_key ] ) ? ( '1' === (string) $se_toggles[ $se_key ] ) : true;
                    ?>
                    <input type="hidden" name="btsld_settings[search_engine_toggles][<?php echo esc_attr( $se_key ); ?>]" value="<?php echo esc_attr( $is_allowed ? '1' : '0' ); ?>" />
                <?php endforeach; ?>

                <div class="btsld-card">
                    <div class="btsld-card-header">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <h2><?php esc_html_e( 'Free AI Crawler Policies', 'bot-traffic-shield' ); ?></h2>
                                <span class="btsld-badge-free"><?php esc_html_e( '15 Crawlers Included', 'bot-traffic-shield' ); ?></span>
                            </div>
                            <p class="btsld-card-desc"><?php esc_html_e( 'Enable or disable protection against standard AI crawlers and data brokers below.', 'bot-traffic-shield' ); ?></p>
                        </div>
                        <div class="btsld-bulk-actions">
                            <button type="button" id="btsld-select-all-ai" class="button button-secondary"><?php esc_html_e( 'Select All', 'bot-traffic-shield' ); ?></button>
                            <button type="button" id="btsld-deselect-all-ai" class="button button-secondary"><?php esc_html_e( 'Deselect All', 'bot-traffic-shield' ); ?></button>
                        </div>
                    </div>
                    <div class="btsld-ai-grid">
                        <?php foreach ( $free_bots as $bot_key => $bot_info ) :
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

                <!-- Pro AI Crawler Policies (under free AI policies) -->
                <div class="btsld-card btsld-card-pro-policies" style="margin-top: 24px;">
                    <div class="btsld-card-header btsld-pro-card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 24px;">
                        <div class="btsld-pro-header-left" style="flex: 1; min-width: 0;">
                            <div class="btsld-pro-title-wrap" style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Pro AI Crawler Policies', 'bot-traffic-shield' ); ?></h2>
                                <?php if ( $is_pro ) : ?>
                                    <span class="btsld-badge-pro-status is-active"><?php esc_html_e( 'PRO UNLOCKED', 'bot-traffic-shield' ); ?></span>
                                <?php else : ?>
                                    <span class="btsld-badge-pro-status is-locked"><?php esc_html_e( 'PRO', 'bot-traffic-shield' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ( $is_pro ) : ?>
                                <p class="btsld-card-desc" style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.5;">
                                    <?php esc_html_e( 'Extended protection against frontier LLMs, conversational search bots, code scrapers, and multimodal AI crawlers.', 'bot-traffic-shield' ); ?>
                                </p>
                            <?php else : ?>
                                <p class="btsld-card-desc btsld-pro-header-desc" style="margin: 0; font-size: 13px; color: #475569; line-height: 1.5; max-width: 820px;">
                                    <?php esc_html_e( 'The policies below protect against frontier LLMs, search scrapers, and specialized AI data extractors (DeepSeek, Grok xAI, Mistral AI, OAI-SearchBot, Phind, and more). They are visible as a preview and automatically unlock when you activate Bot Traffic Shield Pro.', 'bot-traffic-shield' ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="btsld-pro-header-right" style="flex-shrink: 0; display: flex; align-items: center;">
                            <?php if ( $is_pro ) : ?>
                                <div class="btsld-bulk-actions">
                                    <button type="button" id="btsld-select-all-pro-ai" class="button button-secondary"><?php esc_html_e( 'Select All', 'bot-traffic-shield' ); ?></button>
                                    <button type="button" id="btsld-deselect-all-pro-ai" class="button button-secondary"><?php esc_html_e( 'Deselect All', 'bot-traffic-shield' ); ?></button>
                                </div>
                            <?php else : ?>
                                <a href="<?php echo esc_url( BTSLD_Premium::get_upgrade_url() ); ?>" class="button button-primary btsld-upgrade-btn" target="_blank" rel="noopener">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <span><?php esc_html_e( 'Upgrade to Pro', 'bot-traffic-shield' ); ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="btsld-ai-grid <?php echo $is_pro ? '' : 'btsld-ai-grid-locked'; ?>">
                        <?php foreach ( $pro_bots as $bot_key => $bot_info ) :
                            $is_active = isset( $ai_toggles[ $bot_key ] ) ? ( '1' === (string) $ai_toggles[ $bot_key ] ) : true;
                            ?>
                            <div class="btsld-ai-card <?php echo $is_pro ? 'btsld-pro-card-unlocked' : 'btsld-pro-card-locked'; ?>">
                                <div class="btsld-ai-card-top">
                                    <span class="btsld-ai-provider-badge"><?php echo esc_html( $bot_info['provider'] ); ?></span>
                                    <?php if ( $is_pro ) : ?>
                                        <label class="btsld-switch">
                                            <input type="checkbox" class="btsld-ai-checkbox btsld-pro-ai-checkbox" name="btsld_settings[ai_toggles][<?php echo esc_attr( $bot_key ); ?>]" value="1" <?php checked( $is_active, true ); ?> />
                                            <span class="btsld-slider"></span>
                                        </label>
                                    <?php else : ?>
                                        <span class="btsld-pro-lock-pill" title="<?php esc_attr_e( 'Requires Bot Traffic Shield Pro', 'bot-traffic-shield' ); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                            <span><?php esc_html_e( 'PRO', 'bot-traffic-shield' ); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="btsld-ai-title">
                                    <?php echo esc_html( $bot_info['label'] ); ?>
                                </h3>
                                <p class="btsld-ai-desc"><?php echo esc_html( $bot_info['description'] ); ?></p>
                                <div class="btsld-ai-agents">
                                    <?php foreach ( $bot_info['agents'] as $agent ) : ?>
                                        <code><?php echo esc_html( $agent ); ?></code>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ( ! $is_pro ) : ?>
                                    <div class="btsld-pro-card-lock-footer">
                                        <span class="btsld-pro-lock-notice">
                                            <span class="dashicons dashicons-lock"></span>
                                            <a href="<?php echo esc_url( BTSLD_Premium::get_upgrade_url() ); ?>" class="btsld-pro-unlock-link"><?php esc_html_e( 'Unlock with Pro', 'bot-traffic-shield' ); ?> &rarr;</a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="btsld-card-footer">
                        <?php submit_button( $is_pro ? __( 'Save All AI Policies', 'bot-traffic-shield' ) : __( 'Save AI Crawler Rules', 'bot-traffic-shield' ), 'primary', 'submit', false ); ?>
                    </div>
                </div>
            </form>
            <?php
            return;
        endif;

        if ( 'search-engines' !== $key ) {
            return;
        }
        ?>
        <div class="btsld-section-heading">
            <h2><?php esc_html_e( 'Search Engine Rules', 'bot-traffic-shield' ); ?></h2>
            <p><?php esc_html_e( 'Choose how regional and other search engine crawlers are handled.', 'bot-traffic-shield' ); ?></p>
        </div>
        <form action="options.php" method="post">
            <?php settings_fields( 'btsld_settings_group' ); ?>
            <input type="hidden" name="btsld_settings[enabled]" value="<?php echo esc_attr( isset( $settings['enabled'] ) ? $settings['enabled'] : '1' ); ?>" />
            <input type="hidden" name="btsld_settings[log_blocked_bots]" value="<?php echo esc_attr( isset( $settings['log_blocked_bots'] ) ? $settings['log_blocked_bots'] : '1' ); ?>" />
            <input type="hidden" name="btsld_settings[activity_log_enabled]" value="<?php echo esc_attr( isset( $settings['activity_log_enabled'] ) ? $settings['activity_log_enabled'] : '1' ); ?>" />
            <input type="hidden" name="btsld_settings[log_max_entries]" value="<?php echo esc_attr( isset( $settings['log_max_entries'] ) ? $settings['log_max_entries'] : 150 ); ?>" />
            <input type="hidden" name="btsld_settings[admin_bar_enabled]" value="<?php echo esc_attr( isset( $settings['admin_bar_enabled'] ) ? $settings['admin_bar_enabled'] : '1' ); ?>" />
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
                                    <input type="checkbox" class="btsld-se-checkbox" name="btsld_settings[search_engine_toggles][<?php echo esc_attr( $se_key ); ?>]" value="1" data-se-label="<?php echo esc_attr( $se_info['label'] ); ?>" <?php checked( $is_allowed, true ); ?> />
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
        <?php
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
            'btsld_activity_log_enabled',
            __( 'Activity Log', 'bot-traffic-shield' ),
            array( $this, 'render_field_toggle' ),
            'bot-traffic-shield-general',
            'btsld_general_section',
            array(
                'id'    => 'activity_log_enabled',
                'label' => __( 'Store and display detailed blocked-request entries.', 'bot-traffic-shield' ),
            )
        );

        add_settings_field(
            'btsld_log_max_entries',
            __( 'Maximum Activity Log Entries', 'bot-traffic-shield' ),
            array( $this, 'render_field_number' ),
            'bot-traffic-shield-general',
            'btsld_general_section',
            array(
                'id'    => 'log_max_entries',
                'label' => __( 'Maximum number of recent entries to retain (1–10,000).', 'bot-traffic-shield' ),
            )
        );

        add_settings_field(
            'btsld_admin_bar_enabled',
            __( 'Admin Bar Menu', 'bot-traffic-shield' ),
            array( $this, 'render_field_toggle' ),
            'bot-traffic-shield-general',
            'btsld_general_section',
            array(
                'id'    => 'admin_bar_enabled',
                'label' => __( 'Show the Bot Traffic Shield menu in the WordPress admin bar.', 'bot-traffic-shield' ),
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

        $sanitized_input['enabled']              = ( isset( $input['enabled'] ) && '1' === (string) $input['enabled'] ) ? '1' : '0';
        $sanitized_input['log_blocked_bots']     = ( isset( $input['log_blocked_bots'] ) && '1' === (string) $input['log_blocked_bots'] ) ? '1' : '0';
        $sanitized_input['activity_log_enabled'] = ( isset( $input['activity_log_enabled'] ) && '1' === (string) $input['activity_log_enabled'] ) ? '1' : '0';
        $sanitized_input['admin_bar_enabled']    = ( isset( $input['admin_bar_enabled'] ) && '1' === (string) $input['admin_bar_enabled'] ) ? '1' : '0';
        $sanitized_input['log_max_entries']      = min( 10000, max( 1, absint( isset( $input['log_max_entries'] ) ? $input['log_max_entries'] : 150 ) ) );

        $log = get_option( 'btsld_blocked_log', array() );
        if ( is_array( $log ) && count( $log ) > $sanitized_input['log_max_entries'] ) {
            update_option( 'btsld_blocked_log', array_slice( $log, 0, $sanitized_input['log_max_entries'] ), false );
        }

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
     * Render number field.
     *
     * @param array $args Field arguments.
     */
    public function render_field_number( $args ) {
        $settings = get_option( 'btsld_settings', array() );
        $id       = $args['id'];
        $value    = isset( $settings[ $id ] ) ? absint( $settings[ $id ] ) : 150;
        $value    = min( 10000, max( 1, $value ) );

        echo '<input type="number" id="btsld_settings_' . esc_attr( $id ) . '" name="btsld_settings[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" min="1" max="10000" class="small-text" />';
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
     * AJAX handler for live dashboard metrics and chart refresh.
     */
    public function handle_refresh_dashboard() {
        check_ajax_referer( 'btsld_refresh_dashboard_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bot-traffic-shield' ) ) );
        }

        $total_blocked  = (int) get_option( 'btsld_blocked_count', 0 );
        $daily_stats    = get_option( 'btsld_stats_daily', array() );
        $today_key      = gmdate( 'Y-m-d' );
        $blocked_today  = isset( $daily_stats[ $today_key ]['total'] ) ? (int) $daily_stats[ $today_key ]['total'] : 0;

        $bw_total_bytes = BTSLD_Core::get_bandwidth_saved_bytes( $total_blocked );
        $bw_today_bytes = BTSLD_Core::get_bandwidth_saved_bytes( $blocked_today );
        $queries_saved  = BTSLD_Core::get_queries_saved( $total_blocked );

        $top_bot_name   = 'None';
        $top_bot_count  = 0;
        $total_scrapes  = 0;
        $aggregate_bots = array();
        if ( ! empty( $daily_stats ) ) {
            foreach ( $daily_stats as $day ) {
                if ( ! empty( $day['by_bot'] ) ) {
                    foreach ( $day['by_bot'] as $b_name => $b_count ) {
                        $aggregate_bots[ $b_name ] = ( isset( $aggregate_bots[ $b_name ] ) ? $aggregate_bots[ $b_name ] : 0 ) + $b_count;
                        $total_scrapes += $b_count;
                    }
                }
            }
            if ( ! empty( $aggregate_bots ) ) {
                arsort( $aggregate_bots );
                $top_bot_name  = key( $aggregate_bots );
                $top_bot_count = current( $aggregate_bots );
            }
        }

        $registered_bots = BTSLD_Core::get_registered_ai_bots();
        $settings        = get_option( 'btsld_settings', array() );
        $ai_toggles      = isset( $settings['ai_toggles'] ) && is_array( $settings['ai_toggles'] ) ? $settings['ai_toggles'] : array();
        $active_ai_count = 0;
        foreach ( $registered_bots as $k => $v ) {
            if ( ! isset( $ai_toggles[ $k ] ) || '1' === (string) $ai_toggles[ $k ] ) {
                $active_ai_count++;
            }
        }

        $all_logs      = get_option( 'btsld_blocked_log', array() );
        $recent_events = array();
        if ( is_array( $all_logs ) && ! empty( $all_logs ) ) {
            $slice = array_slice( $all_logs, 0, 5 );
            foreach ( $slice as $item ) {
                $t = isset( $item['time'] ) ? (int) $item['time'] : time();
                $b = isset( $item['bot'] ) ? $item['bot'] : '';
                $recent_events[] = array(
                    'time_ago'   => human_time_diff( $t, time() ) . ' ' . __( 'ago', 'bot-traffic-shield' ),
                    'time_exact' => gmdate( 'Y-m-d H:i:s UTC', $t ),
                    'bot'        => $b,
                    'provider'   => BTSLD_Core::get_bot_provider( $b ),
                    'ip'         => isset( $item['ip'] ) ? $item['ip'] : '',
                    'ua'         => isset( $item['user_agent'] ) ? $item['user_agent'] : '',
                );
            }
        }

        // Leaderboard for top bots
        $leaderboard = array();
        $top_slice   = array_slice( $aggregate_bots, 0, 5, true );
        foreach ( $top_slice as $b_name => $b_count ) {
            $pct = $total_scrapes > 0 ? round( ( $b_count / $total_scrapes ) * 100, 1 ) : 0;
            $leaderboard[] = array(
                'name'     => $b_name,
                'count'    => number_format_i18n( $b_count ),
                'percent'  => $pct,
                'provider' => BTSLD_Core::get_bot_provider( $b_name ),
            );
        }

        wp_send_json_success( array(
            'total_blocked'         => number_format_i18n( $total_blocked ),
            'blocked_today'         => number_format_i18n( $blocked_today ),
            'bandwidth_saved_total' => BTSLD_Core::format_bandwidth( $bw_total_bytes ),
            'bandwidth_saved_today' => BTSLD_Core::format_bandwidth( $bw_today_bytes ),
            'queries_saved_total'   => BTSLD_Core::format_compact_number( $queries_saved ),
            'active_ai_count'       => $active_ai_count,
            'registered_bots_total' => count( $registered_bots ),
            'top_bot_name'          => $top_bot_name,
            'top_bot_provider'      => BTSLD_Core::get_bot_provider( $top_bot_name ),
            'top_bot_percent'       => $total_scrapes > 0 ? round( ( $top_bot_count / $total_scrapes ) * 100, 1 ) : 0,
            'chart_data'            => array(
                '7'  => BTSLD_Core::get_chart_data( 7 ),
                '14' => BTSLD_Core::get_chart_data( 14 ),
                '30' => BTSLD_Core::get_chart_data( 30 ),
            ),
            'recent_events'         => $recent_events,
            'leaderboard'           => $leaderboard,
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

        $settings             = get_option( 'btsld_settings', array() );
        $is_shield_enabled    = ! isset( $settings['enabled'] ) || '1' === (string) $settings['enabled'];
        $activity_log_enabled = ! isset( $settings['activity_log_enabled'] ) || '1' === (string) $settings['activity_log_enabled'];
        $registered_bots      = BTSLD_Core::get_registered_ai_bots();
        $ai_toggles           = isset( $settings['ai_toggles'] ) && is_array( $settings['ai_toggles'] ) ? $settings['ai_toggles'] : array();
        $regional_engines     = BTSLD_Core::get_regional_search_engines();
        $se_toggles           = isset( $settings['search_engine_toggles'] ) && is_array( $settings['search_engine_toggles'] ) ? $settings['search_engine_toggles'] : array();

        $total_blocked        = (int) get_option( 'btsld_blocked_count', 0 );
        $daily_stats          = get_option( 'btsld_stats_daily', array() );
        $today_key            = gmdate( 'Y-m-d' );
        $blocked_today        = isset( $daily_stats[ $today_key ]['total'] ) ? (int) $daily_stats[ $today_key ]['total'] : 0;

        $bw_total_bytes       = BTSLD_Core::get_bandwidth_saved_bytes( $total_blocked );
        $bw_today_bytes       = BTSLD_Core::get_bandwidth_saved_bytes( $blocked_today );
        $queries_saved        = BTSLD_Core::get_queries_saved( $total_blocked );

        // Count active AI filters
        $active_ai_count = 0;
        foreach ( $registered_bots as $k => $v ) {
            if ( ! isset( $ai_toggles[ $k ] ) || '1' === (string) $ai_toggles[ $k ] ) {
                $active_ai_count++;
            }
        }

        // Aggregate bots and determine top scraper
        $top_bot_name   = 'None';
        $top_bot_count  = 0;
        $total_scrapes  = 0;
        $aggregate_bots = array();
        if ( ! empty( $daily_stats ) ) {
            foreach ( $daily_stats as $day ) {
                if ( ! empty( $day['by_bot'] ) ) {
                    foreach ( $day['by_bot'] as $b_name => $b_count ) {
                        $aggregate_bots[ $b_name ] = ( isset( $aggregate_bots[ $b_name ] ) ? $aggregate_bots[ $b_name ] : 0 ) + $b_count;
                        $total_scrapes += $b_count;
                    }
                }
            }
            if ( ! empty( $aggregate_bots ) ) {
                arsort( $aggregate_bots );
                $top_bot_name  = key( $aggregate_bots );
                $top_bot_count = current( $aggregate_bots );
            }
        }

        $top_bot_percent = $total_scrapes > 0 ? round( ( $top_bot_count / $total_scrapes ) * 100, 1 ) : 0;
        $top_leaderboard = array_slice( $aggregate_bots, 0, 5, true );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination query parameter.
        $current_page   = isset( $_GET['log_page'] ) ? max( 1, absint( wp_unslash( $_GET['log_page'] ) ) ) : 1;
        $per_page       = 20;
        $all_logs       = get_option( 'btsld_blocked_log', array() );
        $total_logs     = is_array( $all_logs ) ? count( $all_logs ) : 0;
        $paginated_logs = $this->get_paginated_logs( $per_page, $current_page );
        $recent_feed    = is_array( $all_logs ) ? array_slice( $all_logs, 0, 5 ) : array();

        // 7-day data for summary cards
        $initial_7d = BTSLD_Core::get_chart_data( 7 );
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
                    <span class="btsld-status-badge <?php echo $is_shield_enabled ? 'status-active' : 'status-inactive'; ?>" id="btsld-header-status-badge">
                        <span class="status-dot"></span>
                        <?php echo $is_shield_enabled ? esc_html__( 'Shield Active', 'bot-traffic-shield' ) : esc_html__( 'Shield Paused', 'bot-traffic-shield' ); ?>
                    </span>
                </div>
            </header>
            <hr class="wp-header-end">

            <?php
            // Outside Settings, WordPress no longer prints the "Settings saved" notice for us.
            settings_errors();
            ?>

            <!-- Navigation Tabs -->
            <nav class="nav-tab-wrapper btsld-tabs-nav">
                <a href="#tab-dashboard" class="nav-tab nav-tab-active">
                    <span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Dashboard & Charts', 'bot-traffic-shield' ); ?>
                </a>
                <a href="#tab-settings" class="nav-tab">
                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'General Settings', 'bot-traffic-shield' ); ?>
                </a>
                <?php if ( $activity_log_enabled ) : ?>
                <a href="#tab-log" class="nav-tab">
                    <span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Activity Log', 'bot-traffic-shield' ); ?>
                </a>
                <?php endif; ?>
                <?php if ( class_exists( 'BTSLD_Premium' ) && ! BTSLD_Premium::is_active() ) : ?>
                    <a href="<?php echo esc_url( BTSLD_Premium::get_upgrade_url() ); ?>" class="button btsld-upgrade-tab-btn" target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span> <?php esc_html_e( 'Upgrade to Pro', 'bot-traffic-shield' ); ?>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- TAB 1: DASHBOARD & CHARTS -->
            <div id="tab-dashboard" class="btsld-tab-content btsld-tab-active">

                <!-- System Health & Status Bar -->
                <div class="btsld-health-ribbon">
                    <div class="btsld-health-ribbon-left">
                        <span class="btsld-ribbon-item">
                            <span class="btsld-status-indicator <?php echo $is_shield_enabled ? 'status-online' : 'status-offline'; ?>"></span>
                            <strong><?php esc_html_e( 'Protection:', 'bot-traffic-shield' ); ?></strong>
                            <span class="btsld-ribbon-val"><?php echo $is_shield_enabled ? esc_html__( 'Active & Blocking', 'bot-traffic-shield' ) : esc_html__( 'Paused', 'bot-traffic-shield' ); ?></span>
                        </span>
                        <span class="btsld-ribbon-divider">&bull;</span>
                        <span class="btsld-ribbon-item">
                            <span class="dashicons dashicons-yes-alt btsld-icon-success"></span>
                            <strong><?php esc_html_e( 'SEO Safety:', 'bot-traffic-shield' ); ?></strong>
                            <span class="btsld-ribbon-val"><?php esc_html_e( 'Google & Bing Safe', 'bot-traffic-shield' ); ?></span>
                        </span>
                        <span class="btsld-ribbon-divider">&bull;</span>
                        <span class="btsld-ribbon-item">
                            <span class="dashicons dashicons-cloud-saved btsld-icon-emerald"></span>
                            <strong><?php esc_html_e( 'Bandwidth Saved:', 'bot-traffic-shield' ); ?></strong>
                            <span class="btsld-ribbon-val" id="btsld-ribbon-bw"><?php echo esc_html( BTSLD_Core::format_bandwidth( $bw_total_bytes ) ); ?></span>
                        </span>
                        <span class="btsld-ribbon-divider">&bull;</span>
                        <span class="btsld-ribbon-item">
                            <span class="dashicons dashicons-database btsld-icon-cyan"></span>
                            <strong><?php esc_html_e( 'DB Queries Spared:', 'bot-traffic-shield' ); ?></strong>
                            <span class="btsld-ribbon-val" id="btsld-ribbon-queries"><?php echo esc_html( BTSLD_Core::format_compact_number( $queries_saved ) ); ?></span>
                        </span>
                    </div>
                    <div class="btsld-health-ribbon-right">
                        <button type="button" id="btsld-refresh-stats-btn" class="button btsld-btn-refresh" title="<?php esc_attr_e( 'Refresh dashboard statistics', 'bot-traffic-shield' ); ?>">
                            <span class="dashicons dashicons-update"></span>
                            <span class="btsld-refresh-label"><?php esc_html_e( 'Refresh Stats', 'bot-traffic-shield' ); ?></span>
                        </button>
                    </div>
                </div>

                <!-- 6 Modern KPI Metrics Cards -->
                <div class="btsld-kpi-grid">
                    <!-- 1. Total Blocked Requests -->
                    <div class="btsld-kpi-card btsld-kpi-card-purple">
                        <div class="btsld-kpi-top">
                            <div class="btsld-kpi-icon icon-purple"><span class="dashicons dashicons-shield"></span></div>
                            <span class="btsld-trend-badge badge-purple" id="btsld-trend-today">
                                +<?php echo esc_html( number_format_i18n( $blocked_today ) ); ?> <?php esc_html_e( 'today', 'bot-traffic-shield' ); ?>
                            </span>
                        </div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Total Blocked Requests', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val" id="btsld-blocked-count"><?php echo esc_html( number_format_i18n( $total_blocked ) ); ?></span>
                            <span class="btsld-kpi-hint"><?php esc_html_e( 'Lifetime crawlers intercepted', 'bot-traffic-shield' ); ?></span>
                        </div>
                    </div>

                    <!-- 2. Bandwidth Saved (NEW FEATURE) -->
                    <div class="btsld-kpi-card btsld-kpi-card-emerald">
                        <div class="btsld-kpi-top">
                            <div class="btsld-kpi-icon icon-emerald"><span class="dashicons dashicons-cloud-saved"></span></div>
                            <span class="btsld-trend-badge badge-emerald" id="btsld-bw-today-badge">
                                +<?php echo esc_html( BTSLD_Core::format_bandwidth( $bw_today_bytes, 1 ) ); ?> <?php esc_html_e( 'today', 'bot-traffic-shield' ); ?>
                            </span>
                        </div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Est. Bandwidth Saved', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val" id="btsld-bw-total"><?php echo esc_html( BTSLD_Core::format_bandwidth( $bw_total_bytes ) ); ?></span>
                            <span class="btsld-kpi-hint"><?php esc_html_e( '~2 MB spared per crawler request', 'bot-traffic-shield' ); ?></span>
                        </div>
                    </div>

                    <!-- 3. Server Database Queries Spared (NEW FEATURE) -->
                    <div class="btsld-kpi-card btsld-kpi-card-cyan">
                        <div class="btsld-kpi-top">
                            <div class="btsld-kpi-icon icon-cyan"><span class="dashicons dashicons-database"></span></div>
                            <span class="btsld-trend-badge badge-cyan"><?php esc_html_e( 'Server Spared', 'bot-traffic-shield' ); ?></span>
                        </div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'DB Queries Spared', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val" id="btsld-queries-saved"><?php echo esc_html( BTSLD_Core::format_compact_number( $queries_saved ) ); ?></span>
                            <span class="btsld-kpi-hint"><?php esc_html_e( 'Bypasses full PHP & MySQL cycles', 'bot-traffic-shield' ); ?></span>
                        </div>
                    </div>

                    <!-- 4. Blocked Today (24h) -->
                    <div class="btsld-kpi-card btsld-kpi-card-blue">
                        <div class="btsld-kpi-top">
                            <div class="btsld-kpi-icon icon-blue"><span class="dashicons dashicons-clock"></span></div>
                            <span class="btsld-trend-badge badge-live">
                                <span class="btsld-pulse-dot"></span> <?php esc_html_e( 'Live 24h', 'bot-traffic-shield' ); ?>
                            </span>
                        </div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Blocked Today (24h)', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val" id="btsld-blocked-today"><?php echo esc_html( number_format_i18n( $blocked_today ) ); ?></span>
                            <span class="btsld-kpi-hint"><?php esc_html_e( 'Hits stopped in current window', 'bot-traffic-shield' ); ?></span>
                        </div>
                    </div>

                    <!-- 5. Active Protection Rules -->
                    <div class="btsld-kpi-card btsld-kpi-card-green">
                        <div class="btsld-kpi-top">
                            <div class="btsld-kpi-icon icon-green"><span class="dashicons dashicons-yes-alt"></span></div>
                            <span class="btsld-trend-badge badge-green" id="btsld-rules-pct">
                                <?php echo esc_html( round( ( $active_ai_count / max( 1, count( $registered_bots ) ) ) * 100 ) ); ?>% <?php esc_html_e( 'Armed', 'bot-traffic-shield' ); ?>
                            </span>
                        </div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Active AI Rules', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val" id="btsld-active-rules"><?php echo esc_html( $active_ai_count . ' / ' . count( $registered_bots ) ); ?></span>
                            <span class="btsld-kpi-hint"><?php esc_html_e( 'AI crawlers & scrapers blocked', 'bot-traffic-shield' ); ?></span>
                        </div>
                    </div>

                    <!-- 6. Top AI Scraper -->
                    <div class="btsld-kpi-card btsld-kpi-card-orange">
                        <div class="btsld-kpi-top">
                            <div class="btsld-kpi-icon icon-orange"><span class="dashicons dashicons-warning"></span></div>
                            <span class="btsld-trend-badge badge-orange" id="btsld-top-bot-provider">
                                <?php echo esc_html( BTSLD_Core::get_bot_provider( $top_bot_name ) ); ?>
                            </span>
                        </div>
                        <div class="btsld-kpi-content">
                            <span class="btsld-kpi-title"><?php esc_html_e( 'Top Scraper Threat', 'bot-traffic-shield' ); ?></span>
                            <span class="btsld-kpi-val btsld-kpi-val-sm" id="btsld-top-bot"><?php echo esc_html( $top_bot_name ); ?></span>
                            <span class="btsld-kpi-hint" id="btsld-top-bot-share">
                                <?php echo $top_bot_percent > 0 ? esc_html( $top_bot_percent . '% ' . __( 'of blocked traffic', 'bot-traffic-shield' ) ) : esc_html__( 'No crawler hits recorded', 'bot-traffic-shield' ); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Professional Charts Grid -->
                <div class="btsld-charts-grid">
                    <!-- Main Trend Chart -->
                    <div class="btsld-card btsld-chart-card btsld-chart-main">
                        <div class="btsld-card-header btsld-chart-header-enhanced">
                            <div class="btsld-chart-header-title">
                                <h2><?php esc_html_e( 'Crawler Traffic & Savings Trend', 'bot-traffic-shield' ); ?></h2>
                                <p class="btsld-card-desc"><?php esc_html_e( 'Visual analytics of intercepted crawler requests and estimated bandwidth spared.', 'bot-traffic-shield' ); ?></p>
                            </div>
                            <div class="btsld-chart-controls">
                                <!-- Metric Switcher -->
                                <div class="btsld-metric-selector" role="group" aria-label="<?php esc_attr_e( 'Metric Selector', 'bot-traffic-shield' ); ?>">
                                    <button type="button" class="btsld-metric-btn active" data-metric="requests">
                                        <span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Requests', 'bot-traffic-shield' ); ?>
                                    </button>
                                    <button type="button" class="btsld-metric-btn" data-metric="bandwidth">
                                        <span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Bandwidth Saved', 'bot-traffic-shield' ); ?>
                                    </button>
                                </div>
                                <!-- Timeframe Selector -->
                                <div class="btsld-timeframe-selector" role="group" aria-label="<?php esc_attr_e( 'Timeframe Selector', 'bot-traffic-shield' ); ?>">
                                    <button type="button" class="btsld-tf-btn active" data-range="7"><?php esc_html_e( '7 Days', 'bot-traffic-shield' ); ?></button>
                                    <button type="button" class="btsld-tf-btn" data-range="14"><?php esc_html_e( '14 Days', 'bot-traffic-shield' ); ?></button>
                                    <button type="button" class="btsld-tf-btn" data-range="30"><?php esc_html_e( '30 Days', 'bot-traffic-shield' ); ?></button>
                                </div>
                            </div>
                        </div>

                        <!-- Period Stats Summary Ribbon -->
                        <div class="btsld-chart-summary-bar">
                            <div class="btsld-summary-pill">
                                <span class="summary-label"><?php esc_html_e( 'Period Total:', 'bot-traffic-shield' ); ?></span>
                                <strong class="summary-val" id="btsld-sum-total"><?php echo esc_html( number_format_i18n( $initial_7d['total_period_requests'] ) ); ?> <?php esc_html_e( 'requests', 'bot-traffic-shield' ); ?></strong>
                            </div>
                            <div class="btsld-summary-pill">
                                <span class="summary-label"><?php esc_html_e( 'Daily Average:', 'bot-traffic-shield' ); ?></span>
                                <strong class="summary-val" id="btsld-sum-avg"><?php echo esc_html( $initial_7d['daily_avg_requests'] ); ?> / <?php esc_html_e( 'day', 'bot-traffic-shield' ); ?></strong>
                            </div>
                            <div class="btsld-summary-pill">
                                <span class="summary-label"><?php esc_html_e( 'Bandwidth Spared:', 'bot-traffic-shield' ); ?></span>
                                <strong class="summary-val summary-emerald" id="btsld-sum-bw"><?php echo esc_html( $initial_7d['total_period_bandwidth'] ); ?></strong>
                            </div>
                            <div class="btsld-summary-pill">
                                <span class="summary-label"><?php esc_html_e( 'Peak Day:', 'bot-traffic-shield' ); ?></span>
                                <strong class="summary-val" id="btsld-sum-peak">
                                    <?php echo ! empty( $initial_7d['peak_day_label'] ) ? esc_html( $initial_7d['peak_day_label'] . ' (' . number_format_i18n( $initial_7d['peak_day_count'] ) . ')' ) : esc_html__( 'None', 'bot-traffic-shield' ); ?>
                                </strong>
                            </div>
                        </div>

                        <div class="btsld-chart-wrapper">
                            <canvas id="btsldTrafficChart"></canvas>
                        </div>
                    </div>

                    <!-- Donut & Top Scrapers Breakdown -->
                    <div class="btsld-card btsld-chart-card btsld-chart-donut">
                        <div class="btsld-card-header">
                            <div>
                                <h2><?php esc_html_e( 'Bot Traffic Breakdown', 'bot-traffic-shield' ); ?></h2>
                                <p class="btsld-card-desc"><?php esc_html_e( 'Share of blocked crawler attempts by bot.', 'bot-traffic-shield' ); ?></p>
                            </div>
                        </div>
                        <div class="btsld-chart-wrapper doughnut-wrapper">
                            <canvas id="btsldDonutChart"></canvas>
                        </div>

                        <!-- Top Scrapers Leaderboard -->
                        <div class="btsld-leaderboard-section">
                            <h3 class="btsld-leaderboard-title"><?php esc_html_e( 'Top Scrapers Leaderboard', 'bot-traffic-shield' ); ?></h3>
                            <div class="btsld-leaderboard-list" id="btsld-leaderboard-list">
                                <?php if ( empty( $top_leaderboard ) ) : ?>
                                    <p class="btsld-no-data-hint"><?php esc_html_e( 'No scraper activity recorded yet.', 'bot-traffic-shield' ); ?></p>
                                <?php else : ?>
                                    <?php
                                    $rank = 1;
                                    foreach ( $top_leaderboard as $b_name => $b_count ) :
                                        $pct = $total_scrapes > 0 ? round( ( $b_count / $total_scrapes ) * 100, 1 ) : 0;
                                        ?>
                                        <div class="btsld-leaderboard-row">
                                            <div class="btsld-leaderboard-meta">
                                                <span class="btsld-leaderboard-rank">#<?php echo esc_html( $rank++ ); ?></span>
                                                <span class="btsld-leaderboard-name" title="<?php echo esc_attr( $b_name ); ?>"><?php echo esc_html( $b_name ); ?></span>
                                                <span class="btsld-provider-pill"><?php echo esc_html( BTSLD_Core::get_bot_provider( $b_name ) ); ?></span>
                                                <span class="btsld-leaderboard-count"><?php echo esc_html( number_format_i18n( $b_count ) ); ?> (<?php echo esc_html( $pct ); ?>%)</span>
                                            </div>
                                            <div class="btsld-progress-track">
                                                <div class="btsld-progress-bar bar-rank-<?php echo esc_attr( min( 5, $rank - 1 ) ); ?>" style="width: <?php echo esc_attr( $pct ); ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NEW FEATURE: Live Threats Feed (Recent Blocked Bots) -->
                <div class="btsld-card btsld-recent-threats-card">
                    <div class="btsld-card-header">
                        <div>
                            <h2>
                                <span class="dashicons dashicons-shield-alt"></span>
                                <?php esc_html_e( 'Recent Intercepted Bots (Live Feed)', 'bot-traffic-shield' ); ?>
                            </h2>
                            <p class="btsld-card-desc"><?php esc_html_e( 'Real-time activity of crawler and scraper requests stopped before loading WordPress.', 'bot-traffic-shield' ); ?></p>
                        </div>
                        <?php if ( $activity_log_enabled ) : ?>
                            <div>
                                <a href="#tab-log" class="button button-secondary btsld-nav-to-log">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <?php esc_html_e( 'View Full Activity Log', 'bot-traffic-shield' ); ?> (<?php echo esc_html( number_format_i18n( $total_logs ) ); ?>) &rarr;
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="btsld-table-responsive" id="btsld-recent-feed-container">
                        <?php if ( empty( $recent_feed ) ) : ?>
                            <div class="btsld-empty-state">
                                <span class="dashicons dashicons-shield"></span>
                                <h4><?php esc_html_e( 'No Bot Traffic Detected Yet', 'bot-traffic-shield' ); ?></h4>
                                <p><?php esc_html_e( 'Your website is actively protected. Incoming bot and scraper requests will appear here in real-time as they are intercepted.', 'bot-traffic-shield' ); ?></p>
                            </div>
                        <?php else : ?>
                            <table class="wp-list-table widefat fixed striped btsld-modern-table">
                                <thead>
                                    <tr>
                                        <th style="width: 140px;"><?php esc_html_e( 'Time', 'bot-traffic-shield' ); ?></th>
                                        <th style="width: 180px;"><?php esc_html_e( 'Bot & Provider', 'bot-traffic-shield' ); ?></th>
                                        <th style="width: 140px;"><?php esc_html_e( 'IP Address', 'bot-traffic-shield' ); ?></th>
                                        <th><?php esc_html_e( 'User Agent Snippet', 'bot-traffic-shield' ); ?></th>
                                        <th style="width: 110px; text-align: center;"><?php esc_html_e( 'Action', 'bot-traffic-shield' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="btsld-recent-feed-tbody">
                                    <?php foreach ( $recent_feed as $item ) :
                                        $item_time = isset( $item['time'] ) ? (int) $item['time'] : time();
                                        $item_bot  = isset( $item['bot'] ) ? $item['bot'] : '';
                                        $provider  = BTSLD_Core::get_bot_provider( $item_bot );
                                        $item_ip   = isset( $item['ip'] ) ? $item['ip'] : '';
                                        $item_ua   = isset( $item['user_agent'] ) ? $item['user_agent'] : '';
                                        ?>
                                        <tr>
                                            <td class="btsld-time-col">
                                                <span class="btsld-time-ago"><?php echo esc_html( human_time_diff( $item_time, time() ) . ' ' . __( 'ago', 'bot-traffic-shield' ) ); ?></span>
                                                <span class="btsld-time-exact"><?php echo esc_html( gmdate( 'H:i:s UTC', $item_time ) ); ?></span>
                                            </td>
                                            <td>
                                                <span class="btsld-bot-badge"><?php echo esc_html( $item_bot ); ?></span>
                                                <span class="btsld-provider-pill"><?php echo esc_html( $provider ); ?></span>
                                            </td>
                                            <td>
                                                <code class="btsld-ip-code"><?php echo esc_html( $item_ip ); ?></code>
                                            </td>
                                            <td class="btsld-ua-col" title="<?php echo esc_attr( $item_ua ); ?>">
                                                <span class="btsld-ua-text"><?php echo esc_html( $item_ua ); ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="btsld-action-pill status-blocked"><?php esc_html_e( '403 Blocked', 'bot-traffic-shield' ); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- NEW FEATURE: Security & Resource Insights Cards -->
                <div class="btsld-insights-grid">
                    <div class="btsld-insight-card">
                        <div class="btsld-insight-icon icon-green"><span class="dashicons dashicons-search"></span></div>
                        <div class="btsld-insight-content">
                            <h3><?php esc_html_e( 'Zero SEO Risk Guarantee', 'bot-traffic-shield' ); ?></h3>
                            <p><?php esc_html_e( 'Googlebot, Bingbot, and Yahoo crawlers are hard-coded in the core whitelist and can never be blocked, keeping your organic search rankings 100% safe.', 'bot-traffic-shield' ); ?></p>
                        </div>
                    </div>

                    <div class="btsld-insight-card">
                        <div class="btsld-insight-icon icon-emerald"><span class="dashicons dashicons-performance"></span></div>
                        <div class="btsld-insight-content">
                            <h3><?php esc_html_e( 'Resource & Server Savings', 'bot-traffic-shield' ); ?></h3>
                            <p><?php esc_html_e( 'Crawlers are terminated immediately at priority 1 with a lightweight header (~300 bytes), avoiding database queries, PHP execution, and theme rendering.', 'bot-traffic-shield' ); ?></p>
                        </div>
                    </div>

                    <div class="btsld-insight-card">
                        <div class="btsld-insight-icon icon-blue"><span class="dashicons dashicons-media-spreadsheet"></span></div>
                        <div class="btsld-insight-content">
                            <h3><?php esc_html_e( 'Audit & Export Reports', 'bot-traffic-shield' ); ?></h3>
                            <p><?php esc_html_e( 'Need crawler data for executive reporting or security audits? Download UTF-8 Excel-ready CSV exports anytime from the Activity Log tab.', 'bot-traffic-shield' ); ?></p>
                            <?php if ( $activity_log_enabled ) : ?>
                                <a href="#tab-log" class="btsld-link-arrow btsld-nav-to-log"><?php esc_html_e( 'Go to CSV Export', 'bot-traffic-shield' ); ?> &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- TAB 2: GENERAL SETTINGS -->
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
                                    <?php $this->render_field_toggle( array( 'id' => 'log_blocked_bots', 'label' => __( 'Record blocked crawler requests and update dashboard analytics.', 'bot-traffic-shield' ) ) ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Activity Log', 'bot-traffic-shield' ); ?></th>
                                <td>
                                    <?php $this->render_field_toggle( array( 'id' => 'activity_log_enabled', 'label' => __( 'Store and display detailed blocked-request entries.', 'bot-traffic-shield' ) ) ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Maximum Activity Log Entries', 'bot-traffic-shield' ); ?></th>
                                <td>
                                    <?php $this->render_field_number( array( 'id' => 'log_max_entries', 'label' => __( 'Keep between 1 and 10,000 of the most recent entries.', 'bot-traffic-shield' ) ) ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Admin Bar Menu', 'bot-traffic-shield' ); ?></th>
                                <td>
                                    <?php $this->render_field_toggle( array( 'id' => 'admin_bar_enabled', 'label' => __( 'Show the Bot Traffic Shield menu in the WordPress admin bar.', 'bot-traffic-shield' ) ) ); ?>
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
            <?php if ( $activity_log_enabled ) : ?>
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
            <?php endif; ?>
        </div>
        <?php
    }
}